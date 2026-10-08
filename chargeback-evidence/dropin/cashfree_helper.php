<?php
/**
 * cashfree_helper.php
 *
 * Shared Cashfree PG (API version 2023-08-01) helpers.
 * Used by: process_checkout.php, payment_callback.php, webhook.php, cron_release_reservations.php
 *
 * Load admin/config.php (PDO + CASHFREE_* constants) BEFORE this file.
 * Compatible with PHP 7.4+.
 */

if (!defined('CASHFREE_APP_ID')) {
    http_response_code(403);
    exit('Forbidden');
}

/* ------------------------------------------------------------------
 * Status values written to YOUR tables. Each one can be overridden in admin/config.php BEFORE this
 * file loads, e.g.  define('CF_ORDER_PAID', 'processing');  to match what your admin dashboard expects.
 * ------------------------------------------------------------------ */
foreach ([
    'CF_PAY_PENDING'   => 'pending',    // orders.payment_status + payments.payment_status
    'CF_PAY_PAID'      => 'paid',
    'CF_PAY_FAILED'    => 'failed',
    'CF_ORDER_PENDING' => 'pending',    // orders.order_status while unpaid
    'CF_ORDER_PAID'    => 'processing', // orders.order_status after payment: the admin dashboard's fulfilment queue is order_status='processing' + payment_status='paid'
    'CF_ORDER_FAILED'  => 'cancelled',  // orders.order_status after a failed / abandoned payment
    'CF_ORDER_MINUTES' => 30,           // Cashfree order (and number reservation) lifetime
    'CF_PAYMENT_LABEL' => 'cashfree',   // orders.payment_method
    'CF_PAYMENT_ROW_PAID' => 'success', // payments.payment_status for a paid order (admin sales reports read 'success')
] as $__k => $__v) {
    if (!defined($__k)) {
        define($__k, $__v);
    }
}
unset($__k, $__v);

// mbstring is normally present; these keep the alerts working if it is not.
if (!function_exists('mb_strlen')) {
    function mb_strlen($s) { return strlen(utf8_decode((string) $s)); }
}
if (!function_exists('mb_substr')) {
    function mb_substr($s, $start, $len = null) { return $len === null ? substr($s, $start) : substr($s, $start, $len); }
}


/* ------------------------------------------------------------------
 * Logging / low-level API client
 * ------------------------------------------------------------------ */
function cf_log(string $message, array $context = []): void
{
    error_log('[Cashfree] ' . $message . ($context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : ''));
}

/**
 * @return array{ok:bool,http:int,data:array,error:?string}
 */
function cf_api(string $method, string $path, ?array $body = null): array
{
    $ch = curl_init(CASHFREE_API_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => [
            'x-client-id: ' . CASHFREE_APP_ID,
            'x-client-secret: ' . CASHFREE_SECRET_KEY,
            'x-api-version: ' . CASHFREE_API_VERSION,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    // Cashfree sends `x-deprecated-at` when the API version in use has a sunset date.
    $deprecatedAt = null;
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($curl, string $line) use (&$deprecatedAt) {
        if (stripos($line, 'x-deprecated-at:') === 0) {
            $deprecatedAt = trim(substr($line, 16));
        }
        return strlen($line);
    });

    $raw      = curl_exec($ch);
    $http     = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr  = $raw === false ? curl_error($ch) : null;
    $data     = is_string($raw) ? json_decode($raw, true) : null;
    $isArray  = is_array($data);
    $ok       = $curlErr === null && $http >= 200 && $http < 300 && $isArray;

    static $warned = false;
    if ($deprecatedAt !== null && $deprecatedAt !== '' && !$warned) {
        $warned = true;                                   // once per PHP request
        cf_log('API version ' . CASHFREE_API_VERSION . ' has a deprecation date - upgrade x-api-version', ['x-deprecated-at' => $deprecatedAt]);
    }

    $error = null;
    if (!$ok) {
        $error = $curlErr ?? ($isArray && isset($data['message']) ? (string) $data['message'] : 'HTTP ' . $http);
    }

    return ['ok' => $ok, 'http' => $http, 'data' => $isArray ? $data : [], 'error' => $error];
}

function cf_create_order(array $payload): array        { return cf_api('POST', '/orders', $payload); }
function cf_get_order(string $orderId): array          { return cf_api('GET', '/orders/' . rawurlencode($orderId)); }
function cf_get_order_payments(string $orderId): array { return cf_api('GET', '/orders/' . rawurlencode($orderId) . '/payments'); }
function cf_terminate_order(string $orderId): array    { return cf_api('PATCH', '/orders/' . rawurlencode($orderId), ['order_status' => 'TERMINATED']); }

/** Webhook signature = base64( HMAC-SHA256( timestamp . rawBody, clientSecret ) ) */
function cf_verify_webhook_signature(string $rawBody, string $signature, string $timestamp): bool
{
    if ($signature === '' || $timestamp === '') {
        return false;
    }
    $expected = base64_encode(hash_hmac('sha256', $timestamp . $rawBody, CASHFREE_SECRET_KEY, true));
    return hash_equals($expected, $signature);
}


/* ------------------------------------------------------------------
 * payments table helpers
 * payment_response holds one JSON document:
 *   { "checkout": {coupon, payment_session_id, ...}, "gateway": {...Cashfree data...} }
 * ------------------------------------------------------------------ */
function cf_payment_meta(PDO $pdo, int $orderId, bool $lock = false): ?array
{
    $st = $pdo->prepare('SELECT payment_response FROM payments WHERE order_id = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $st->execute([$orderId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $json = json_decode((string) $row['payment_response'], true);
    return is_array($json) ? $json : [];
}

/** Upsert the single payments row of an order; $patch keys replace the same keys in the stored JSON. */
function cf_save_payment(PDO $pdo, int $orderId, string $txnId, float $amount, string $status, array $patch = []): void
{
    $status = $status === CF_PAY_PAID ? CF_PAYMENT_ROW_PAID : $status;      // payments.payment_status vocabulary
    $existing = cf_payment_meta($pdo, $orderId, true);
    $amountStr = number_format($amount, 2, '.', '');

    if ($existing !== null) {
        $json = json_encode(array_merge($existing, $patch), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $pdo->prepare('UPDATE payments SET transaction_id = ?, amount = ?, payment_status = ?, payment_response = ? WHERE order_id = ?')
            ->execute([$txnId, $amountStr, $status, $json, $orderId]);
    } else {
        $json = json_encode($patch, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $pdo->prepare('INSERT INTO payments (order_id, transaction_id, amount, payment_status, payment_response) VALUES (?, ?, ?, ?, ?)')
            ->execute([$orderId, $txnId, $amountStr, $status, $json]);
    }
}


/* ------------------------------------------------------------------
 * Idempotent settlement (DB side)
 * ------------------------------------------------------------------ */

/**
 * Move an order to 'paid' or 'failed' exactly once. Safe to call from the callback,
 * the webhook and the cron at the same time: the orders row is locked (FOR UPDATE),
 * 'paid' is terminal, and only the call that actually changed state gets transitioned=true.
 *
 * @param string $result CF_PAY_PAID | CF_PAY_FAILED
 * @return array{state:string,transitioned:bool}  state: paid|failed|conflict|not_found
 */
function cf_finalize(PDO $pdo, string $orderNumber, string $result, array $gateway = []): array
{
    for ($attempt = 1;; $attempt++) {
        try {
            return cf_finalize_tx($pdo, $orderNumber, $result, $gateway);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $code = (int) ($e->errorInfo[1] ?? 0);
            if (in_array($code, [1213, 1205], true) && $attempt < 3) { // deadlock / lock wait timeout
                usleep(150000 * $attempt);
                continue;
            }
            throw $e;
        }
    }
}

function cf_finalize_tx(PDO $pdo, string $orderNumber, string $result, array $gateway): array
{
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM orders WHERE order_number = ? LIMIT 1 FOR UPDATE');
        $st->execute([$orderNumber]);
        $order = $st->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            $pdo->rollBack();
            return ['state' => 'not_found', 'transitioned' => false];
        }

        $current = (string) $order['payment_status'];
        $orderId = (int) $order['id'];

        // 'paid' is terminal: a late failed/expired signal must never undo it.
        if ($current === CF_PAY_PAID) {
            $pdo->commit();
            return ['state' => CF_PAY_PAID, 'transitioned' => false];
        }
        if ($current === CF_PAY_FAILED && $result === CF_PAY_FAILED) {
            $pdo->commit();
            return ['state' => CF_PAY_FAILED, 'transitioned' => false];
        }

        // Lock the reserved numbers (ascending id => consistent lock order with checkout).
        $st = $pdo->prepare('SELECT vip_number_id FROM order_items WHERE order_id = ?');
        $st->execute([$orderId]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        sort($ids);
        $numberStatus = [];
        $in = '';
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare("SELECT id, status FROM vip_numbers WHERE id IN ($in) ORDER BY id FOR UPDATE");
            $st->execute($ids);
            $numberStatus = $st->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        $amount = (float) $order['total_amount'];
        $txnId  = (string) ($gateway['transaction_id'] ?? $orderNumber);

        /* ---------------- PAID ---------------- */
        if ($result === CF_PAY_PAID) {
            if ($current === CF_PAY_FAILED) {
                // Money arrived AFTER we released the numbers. Re-claim only if all are still free.
                foreach ($numberStatus as $s) {
                    if ($s !== 'available') {
                        $meta = cf_payment_meta($pdo, $orderId, true) ?? [];
                        $first = empty($meta['needs_refund']);
                        cf_save_payment($pdo, $orderId, $txnId, $amount, CF_PAY_FAILED, [
                            'gateway'      => $gateway,
                            'needs_refund' => true,
                            'note'         => 'Paid after reservation was released; number(s) no longer available.',
                        ]);
                        $pdo->commit();
                        cf_log('CRITICAL: payment received for released order - refund required', ['order' => $orderNumber]);
                        return ['state' => 'conflict', 'transitioned' => $first];
                    }
                }
            }

            if ($ids) {
                $pdo->prepare("UPDATE vip_numbers SET status = 'sold' WHERE id IN ($in)")->execute($ids);
            }
            $pdo->prepare('UPDATE orders SET payment_status = ?, order_status = ? WHERE id = ?')
                ->execute([CF_PAY_PAID, CF_ORDER_PAID, $orderId]);
            cf_save_payment($pdo, $orderId, $txnId, $amount, CF_PAY_PAID, ['gateway' => $gateway, 'needs_refund' => false]);

            // Coupon usage counts only for paid orders. Never let coupon bookkeeping break a paid order.
            $meta = cf_payment_meta($pdo, $orderId) ?? [];
            $code = $meta['checkout']['coupon_code'] ?? '';
            if ($code !== '') {
                try {
                    $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE code = ?')->execute([$code]);
                } catch (Throwable $e) {
                    cf_log('coupon usage update failed', ['order' => $orderNumber, 'error' => $e->getMessage()]);
                }
            }

            require_once __DIR__ . '/evidence.php';
            ev_log($pdo, $orderId, 'payment_success', 'gateway', ev_payment_details($txnId, $amount, $gateway));
            $pdo->commit();
            return ['state' => CF_PAY_PAID, 'transitioned' => true];
        }

        /* ---------------- FAILED ---------------- */
        if ($ids) {
            // Only release numbers that are still 'reserved' (never touch 'sold').
            $pdo->prepare("UPDATE vip_numbers SET status = 'available' WHERE id IN ($in) AND status = 'reserved'")->execute($ids);
        }
        $pdo->prepare('UPDATE orders SET payment_status = ?, order_status = ? WHERE id = ?')
            ->execute([CF_PAY_FAILED, CF_ORDER_FAILED, $orderId]);
        cf_save_payment($pdo, $orderId, $txnId, $amount, CF_PAY_FAILED, ['gateway' => $gateway]);

        $pdo->commit();
        return ['state' => CF_PAY_FAILED, 'transitioned' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}


/* ------------------------------------------------------------------
 * Ask Cashfree (source of truth) and settle
 * ------------------------------------------------------------------ */

/**
 * Verify an order with Cashfree and update our DB accordingly.
 *
 * @param bool $terminateIfUnpaid  If Cashfree still shows the order ACTIVE with no payment in
 *        progress, terminate it (so it can never be paid later) and release the numbers.
 * @param int  $waitSeconds  Cashfree terminates orders asynchronously (TERMINATION_REQUESTED first).
 *        With $waitSeconds > 0 we re-check once per second (max that many times) instead of
 *        returning 'pending' straight away.
 * @param string $closeReason  Why WE close an unpaid order: 'abandoned_or_failed_attempt' (buyer came back / left) raises the
 *        failed alerts, 'superseded_by_new_checkout' (buyer started again) is silent. It is remembered in payments.payment_response
 *        because Cashfree finishes the termination later, possibly seen by another request.
 * @return array{state:string,transitioned:bool,reason?:string}   reason (pending): payment_in_progress | terminating
 *         state: paid | failed | pending | conflict | error | not_found
 */
function cf_settle_order(PDO $pdo, string $orderNumber, bool $terminateIfUnpaid = true, int $waitSeconds = 0, string $closeReason = 'abandoned_or_failed_attempt'): array
{
    $st = $pdo->prepare('SELECT id, order_number, total_amount, payment_status, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_seconds FROM orders WHERE order_number = ? LIMIT 1');
    $st->execute([$orderNumber]);
    $order = $st->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return ['state' => 'not_found', 'transitioned' => false];
    }
    if ($order['payment_status'] === CF_PAY_PAID) {
        return ['state' => CF_PAY_PAID, 'transitioned' => false];
    }

    $res = cf_get_order($orderNumber);
    if (!$res['ok']) {
        // Order never reached Cashfree (e.g. PHP died between our commit and the create call).
        if ($res['http'] === 404 && $order['payment_status'] === CF_PAY_PENDING && (int) $order['age_seconds'] > 120) {
            return cf_apply($pdo, $orderNumber, CF_PAY_FAILED, ['reason' => 'order_not_found_at_cashfree']);
        }
        cf_log('get order failed', ['order' => $orderNumber, 'http' => $res['http'], 'error' => $res['error']]);
        return ['state' => 'error', 'transitioned' => false, 'reason' => 'gateway_unreachable'];
    }

    $cf       = $res['data'];
    $cfStatus = strtoupper((string) ($cf['order_status'] ?? ''));

    $pr       = cf_get_order_payments($orderNumber);
    $payments = $pr['ok'] ? array_values($pr['data']) : [];
    $success  = null;
    $hasPendingPayment = false;
    foreach ($payments as $p) {
        $ps = strtoupper((string) ($p['payment_status'] ?? ''));
        if ($ps === 'SUCCESS' && $success === null) {
            $success = $p;
        } elseif ($ps === 'PENDING') {
            $hasPendingPayment = true;
        }
    }

    // ---- PAID ----
    if ($cfStatus === 'PAID' || $success !== null) {
        $amountOk   = abs((float) ($cf['order_amount'] ?? 0) - (float) $order['total_amount']) <= 0.01;
        $currencyOk = strtoupper((string) ($cf['order_currency'] ?? 'INR')) === 'INR';
        if (!$amountOk || !$currencyOk) {
            cf_log('CRITICAL: amount/currency mismatch', ['order' => $orderNumber, 'cashfree' => $cf['order_amount'] ?? null, 'ours' => $order['total_amount']]);
            return ['state' => 'error', 'transitioned' => false, 'reason' => 'amount_mismatch'];
        }
        return cf_apply($pdo, $orderNumber, CF_PAY_PAID, [
            'transaction_id' => trim((string) ($success['bank_reference'] ?? '')) !== ''
                                    ? trim((string) $success['bank_reference'])                       // UTR / bank reference
                                    : (string) ($success['cf_payment_id'] ?? ($cf['cf_order_id'] ?? $orderNumber)),
            'utr'            => trim((string) ($success['bank_reference'] ?? '')),
            'cf_payment_id'  => $success['cf_payment_id'] ?? null,
            'cf_order_id'    => $cf['cf_order_id'] ?? null,
            'cf_status'      => $cfStatus,
            'payment'        => $success,
        ]);
    }

    // ---- Dead orders ----
    if (in_array($cfStatus, ['EXPIRED', 'TERMINATED'], true)) {
        $asked = (string) ((cf_payment_meta($pdo, (int) $order['id']) ?? [])['close_reason'] ?? '');   // set when WE requested the termination
        return cf_apply($pdo, $orderNumber, CF_PAY_FAILED, [
            'reason'    => ($cfStatus === 'TERMINATED' && $asked !== '') ? $asked : 'cashfree_order_' . strtolower($cfStatus),
            'cf_status' => $cfStatus,
            'payments'  => array_slice($payments, -5),
        ]);
    }

    // ---- Still ACTIVE / TERMINATION_REQUESTED ----
    $wait = static function () use ($pdo, $orderNumber, $waitSeconds, $closeReason) {
        if ($waitSeconds > 0) {
            sleep(1);
            return cf_settle_order($pdo, $orderNumber, false, $waitSeconds - 1, $closeReason);
        }
        return ['state' => 'pending', 'transitioned' => false, 'reason' => 'terminating'];
    };
    if ($hasPendingPayment) {
        return ['state' => 'pending', 'transitioned' => false, 'reason' => 'payment_in_progress'];
    }
    if ($cfStatus === 'TERMINATION_REQUESTED') {
        return $wait();
    }
    if (!$terminateIfUnpaid) {
        return ['state' => 'pending', 'transitioned' => false, 'reason' => 'active'];
    }

    try {
        cf_payment_patch($pdo, (int) $order['id'], ['close_reason' => $closeReason]);
    } catch (Throwable $e) {
        cf_log('could not store close_reason', ['order' => $orderNumber, 'error' => $e->getMessage()]);
    }
    $t        = cf_terminate_order($orderNumber);
    $tStatus  = strtoupper((string) ($t['data']['order_status'] ?? ''));
    if ($tStatus === 'TERMINATED') {
        return cf_apply($pdo, $orderNumber, CF_PAY_FAILED, [
            'reason'    => $closeReason,
            'cf_status' => 'TERMINATED',
            'payments'  => array_slice($payments, -5),
        ]);
    }
    if ($tStatus === 'PAID') {                    // a payment landed while we were terminating
        return cf_settle_order($pdo, $orderNumber, false);
    }
    return $wait();                               // TERMINATION_REQUESTED etc. -> re-check, else cron finishes it
}

/** cf_finalize() + confirmation email / admin alert exactly once. */
function cf_apply(PDO $pdo, string $orderNumber, string $result, array $gateway): array
{
    $fin = cf_finalize($pdo, $orderNumber, $result, $gateway);

    // Only the call that actually changed the state gets transitioned=true => every alert goes out exactly once.
    if ($fin['transitioned'] && $fin['state'] === CF_PAY_PAID) {
        cf_send_confirmation_email($pdo, $orderNumber);
        cf_notify_later('paid', $pdo, $orderNumber);
    }
    if ($fin['transitioned'] && $fin['state'] === CF_PAY_FAILED) {
        // Alert only for a real failure / walk-away, or a gateway problem. A quiet expiry, or an order we closed
        // ourselves because the buyer started again, is not worth an alert.
        if (in_array((string) ($gateway['reason'] ?? ''), ['abandoned_or_failed_attempt', 'cashfree_create_order_failed'], true)) {
            cf_notify_later('failed', $pdo, $orderNumber);
        }
    }
    if ($fin['transitioned'] && $fin['state'] === 'conflict') {
        cf_alert_admin('Refund needed - order ' . $orderNumber,
            'Payment was received for order ' . $orderNumber . ' after its numbers were released and re-sold. Please refund via the Cashfree dashboard.');
        cf_notify_later('refund', $pdo, $orderNumber);
    }
    return $fin;
}

/** queue_email() lives in mail/email_queue.php, order_email_template() in mail/templates/order_template.php. */
function cf_mail_load(): void
{
    if (!function_exists('queue_email') && is_file(__DIR__ . '/mail/email_queue.php')) {
        require_once __DIR__ . '/mail/email_queue.php';
    }
    if (!function_exists('order_email_template') && is_file(__DIR__ . '/mail/templates/order_template.php')) {
        require_once __DIR__ . '/mail/templates/order_template.php';
    }
}

function cf_send_confirmation_email(PDO $pdo, string $orderNumber): void
{
    try {
        cf_mail_load();
        if (!function_exists('order_email_template') || !function_exists('queue_email')) {
            cf_log('email functions missing - require mail/email_queue.php');
            return;
        }
        $o = cf_order_snapshot($pdo, $orderNumber);
        if (!$o || empty($o['email'])) {
            return;
        }
        [$vip, $network] = cf_join_items($o['items']);

        // Keys read by mail/templates/order_template.php
        $data = [
            'name'    => trim($o['first_name'] . ' ' . $o['last_name']),
            'order'   => $o['order_number'],
            'vip'     => $vip,
            'network' => $network,
            'amount'  => cf_plain_amount($o['total_amount']),
        ];
        $body = order_email_template($data);
        try {   // invoice inside the email + link to the customer's own invoice page; never block the confirmation if this fails
            require_once __DIR__ . '/mail/invoice_email.php';
            $body = invoice_email_inject($body, invoice_email_block($pdo, $orderNumber));
        } catch (Throwable $e) {
            cf_log('invoice block failed', ['order' => $orderNumber, 'error' => $e->getMessage()]);
        }
        $queued = queue_email($o['email'], 'Order Confirmation & Invoice - ' . $o['order_number'], $body, json_encode(['invoice_order' => $o['order_number']]));   // the PDF is attached when the mail is sent
        if ($queued) {
            // Send it right now (after the page/webhook response has gone out) - do not wait for a cron. The cron retries if this fails.
            $qid = (int) $pdo->lastInsertId();
            if ($qid > 0) {
                cf_defer(static function () use ($pdo, $qid, $orderNumber) {
                    require_once __DIR__ . '/mail/send_now.php';
                    $r = mail_send_queued_now($pdo, $qid);
                    cf_log('order mail', ['order' => $orderNumber, 'queue_id' => $qid, 'result' => $r]);
                });
            }
        }
    } catch (Throwable $e) {
        cf_log('confirmation email failed', ['order' => $orderNumber, 'error' => $e->getMessage()]);
    }
}

function cf_alert_admin(string $subject, string $message): void
{
    try {
        cf_mail_load();
        if (defined('ADMIN_EMAIL') && function_exists('queue_email')) {
            queue_email(ADMIN_EMAIL, $subject, nl2br(htmlspecialchars($message)), json_encode([]));
        }
    } catch (Throwable $e) {
        cf_log('admin alert failed', ['error' => $e->getMessage()]);
    }
}


/* ------------------------------------------------------------------
 * Branded pages - match vipnumbergallery.com
 * (Tailwind + Poppins + Font Awesome, navy #1e4475, gold #f5be29, orange #ff8318, footer #15325c)
 * Used by process_checkout.php (errors / redirect) and payment_callback.php (result).
 * ------------------------------------------------------------------ */
function cf_esc($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** 1234567.5 -> ₹12,34,567.50 */
function cf_inr(float $n): string
{
    [$int, $dec] = explode('.', number_format($n, 2, '.', ''));
    $last3 = substr($int, -3);
    $rest  = substr($int, 0, -3);
    if ($rest !== '') {
        $rest  = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $last3 = ',' . $last3;
    }
    return '₹' . $rest . $last3 . '.' . $dec;
}

/** Full HTML page with the site's header + footer around $body. $scripts goes before </body>, $headExtra into <head>. */
function cf_page(string $title, string $body, string $scripts = '', string $headExtra = ''): void
{
    $site  = rtrim(SITE_URL, '/');
    $email = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'support@vipnumbergallery.com';
    $wa    = defined('SUPPORT_WHATSAPP') ? SUPPORT_WHATSAPP : '918882143786';
    $phone = defined('SUPPORT_PHONE') ? SUPPORT_PHONE : '8882143786';
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#0f172a">
    <title><?= cf_esc($title) ?> | VIP Number Gallery</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= cf_esc($site) ?>/assets/favicon/favicon-32x32.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: "Poppins", system-ui, -apple-system, "Segoe UI", sans-serif; background: #f2f2f2; color: #1f2937; }
        @media (prefers-reduced-motion: reduce) { .fa-spin { animation: none !important; } }
    </style>
    <?= $headExtra ?>
</head>
<body class="min-h-screen flex flex-col">
    <header class="bg-white border-b border-slate-200 shadow-sm">
        <div class="max-w-5xl mx-auto px-4 h-[74px] flex items-center justify-between gap-4">
            <a href="<?= cf_esc($site) ?>/index.php" class="flex items-center gap-3 shrink-0">
                <span class="w-10 h-8 sm:w-12 sm:h-9 rounded-md bg-[#1e4475] flex items-center justify-center text-[#f5be29] text-lg sm:text-xl shadow-md"><i class="fa-solid fa-sim-card"></i></span>
                <span>
                    <span class="block font-black text-slate-800 text-sm sm:text-lg tracking-wide uppercase leading-tight">VIP Number Gallery</span>
                    <span class="block text-[9px] sm:text-[10px] text-slate-500 font-bold tracking-widest">PREMIUM MOBILE NUMBERS</span>
                </span>
            </a>
            <a href="<?= cf_esc($site) ?>/store.php" class="text-xs sm:text-sm font-semibold text-slate-600 hover:text-[#ff8318] transition">Browse numbers</a>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center px-4 py-8 sm:py-12">
        <?= $body ?>
    </main>

    <footer class="py-6 text-center text-xs" style="background:#15325c;color:#9fb6d4">
        <div class="max-w-5xl mx-auto px-4">
            <p class="flex flex-wrap items-center justify-center gap-x-5 gap-y-1">
                <span>Need help?</span>
                <a class="hover:text-white transition" href="https://wa.me/<?= cf_esc($wa) ?>"><i class="fa-brands fa-whatsapp"></i> WhatsApp <?= cf_esc($phone) ?></a>
                <a class="hover:text-white transition" href="mailto:<?= cf_esc($email) ?>"><i class="fa-regular fa-envelope"></i> <?= cf_esc($email) ?></a>
            </p>
            <p class="mt-2">© <?= date('Y') ?> VIP Shop. All rights reserved.</p>
        </div>
    </footer>
    <?= $scripts ?>
</body>
</html>
<?php
}

/**
 * The white status card used on every page.
 * $o: tone (success|error|wait|warn|info), spin (bool), title, text,
 *     rows [[label, value], ...], numbers [..], numbers_label, numbers_struck (bool),
 *     extra (trusted HTML), buttons [[label, href, 'primary'|'secondary', onclick?], ...],
 *     debug [lines]
 */
function cf_status_card(array $o): string
{
    $tones = [
        'success' => ['fa-circle-check',          '#dcfce7', '#15803d'],
        'error'   => ['fa-circle-xmark',          '#fee2e2', '#dc2626'],
        'wait'    => ['fa-hourglass-half',        '#fef3c7', '#b45309'],
        'warn'    => ['fa-triangle-exclamation',  '#ffedd5', '#ea580c'],
        'info'    => ['fa-circle-info',           '#dbeafe', '#1e4475'],
    ];
    [$icon, $bg, $fg] = $tones[$o['tone'] ?? 'info'] ?? $tones['info'];
    if (!empty($o['spin'])) {
        $icon = 'fa-circle-notch fa-spin';
    }

    $h  = '<section class="w-full max-w-lg bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8" aria-live="polite">';
    $h .= '<div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full text-3xl" style="background:' . $bg . ';color:' . $fg . '"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i></div>';
    $h .= '<h1 class="mt-5 text-center text-xl sm:text-2xl font-bold text-slate-900">' . cf_esc($o['title'] ?? '') . '</h1>';
    if (!empty($o['text'])) {
        $h .= '<p class="mx-auto mt-2 max-w-sm text-center text-sm leading-6 text-slate-600">' . cf_esc($o['text']) . '</p>';
    }

    if (!empty($o['rows']) || !empty($o['numbers'])) {
        $h .= '<dl class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200 text-sm">';
        foreach (($o['rows'] ?? []) as [$label, $value]) {
            $h .= '<div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate-500">' . cf_esc($label) . '</dt><dd class="font-semibold text-right break-all">' . cf_esc($value) . '</dd></div>';
        }
        if (!empty($o['numbers'])) {
            $h .= '<div class="px-4 py-3"><dt class="text-slate-500">' . cf_esc($o['numbers_label'] ?? 'Numbers') . '</dt><dd class="mt-2 flex flex-wrap gap-2">';
            foreach ($o['numbers'] as $n) {
                $h .= '<span class="rounded-lg px-2.5 py-1 font-mono text-sm tracking-wide ' . (!empty($o['numbers_struck']) ? 'line-through text-slate-400' : 'text-[#1e4475]') . '" style="background:#eef3fa">' . cf_esc($n) . '</span>';
            }
            $h .= '</dd></div>';
        }
        $h .= '</dl>';
    }

    if (!empty($o['extra'])) {
        $h .= $o['extra'];
    }

    if (!empty($o['buttons'])) {
        $h .= '<div class="mt-6 flex flex-col sm:flex-row sm:justify-center gap-3">';
        foreach ($o['buttons'] as $b) {
            $primary = ($b[2] ?? 'primary') === 'primary';
            $cls = 'inline-flex items-center justify-center rounded-full px-7 py-2.5 text-[15px] font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#f5be29] focus-visible:ring-offset-2 '
                 . ($primary ? 'bg-[#1e4475] text-white hover:bg-[#2a5b9e]' : 'border border-[#1e4475] text-[#1e4475] hover:bg-slate-50');
            $h .= '<a href="' . cf_esc($b[1]) . '" class="' . $cls . '"' . (!empty($b[3]) ? ' onclick="' . cf_esc($b[3]) . '"' : '') . '>' . cf_esc($b[0]) . '</a>';
        }
        $h .= '</div>';
    }

    if (!empty($o['debug'])) {
        $h .= '<div class="mt-6 rounded-lg bg-slate-900 p-4 text-left text-[11px] leading-5 text-slate-200 font-mono break-words">'
            . '<p class="mb-1 font-sans text-xs font-semibold text-[#f5be29]"><i class="fa-solid fa-bug"></i> Debug details (only visible while CASHFREE_DEBUG is on)</p>';
        foreach ($o['debug'] as $line) {
            $h .= '<p>' . cf_esc($line) . '</p>';
        }
        $h .= '</div>';
    }

    return $h . '</section>';
}


/* ------------------------------------------------------------------
 * Alerts - through the project's own helpers
 *   Admin    -> Telegram : include/telegram_alerts.php  (token + chat id live there)
 *   Customer -> WhatsApp : include/wa_gateway.php       (on/off: define('WA_ALERTS_ENABLED', true|false) in admin/config.php)
 *
 * Kinds: paid | failed | refund.   Reliability:
 *   - sent AFTER the page/webhook response has been delivered (never slows the buyer or Cashfree)
 *   - each channel is sent at most once per order (DB lock + flags kept in payments.payment_response)
 *   - an alert that failed (Telegram/WhatsApp down) is retried by cron_release_reservations.php, up to 10 times
 * ------------------------------------------------------------------ */
function cf_alerts_load(): void
{
    if (!function_exists('send_admin_alert_on_success') && is_file(__DIR__ . '/include/telegram_alerts.php')) {
        require_once __DIR__ . '/include/telegram_alerts.php';
    }
    if (!function_exists('wa_send_payment_success') && is_file(__DIR__ . '/include/wa_gateway.php')) {
        require_once __DIR__ . '/include/wa_gateway.php';
    }
}

function cf_wa_enabled(): bool
{
    cf_alerts_load();
    return function_exists('wa_alerts_enabled') && wa_alerts_enabled();
}

/** Run $fn after the response has been sent to the browser / Cashfree (falls back to end-of-request). */
function cf_defer(callable $fn): void
{
    register_shutdown_function(static function () use ($fn) {
        ignore_user_abort(true);
        if (PHP_SAPI !== 'cli') {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }
        }
        try {
            $fn();
        } catch (Throwable $e) {
            cf_log('deferred task failed', ['order_alert_error' => $e->getMessage()]);
        }
    });
}

/** Merge $patch into payments.payment_response (top-level keys) without touching status/amount. */
function cf_payment_patch(PDO $pdo, int $orderId, array $patch): void
{
    $meta = cf_payment_meta($pdo, $orderId);
    if ($meta === null) {
        return;
    }
    $pdo->prepare('UPDATE payments SET payment_response = ? WHERE order_id = ?')
        ->execute([json_encode(array_merge($meta, $patch), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $orderId]);
}

/** Order + customer + numbers + payment meta: everything an alert / email needs. */
function cf_order_snapshot(PDO $pdo, string $orderNumber): ?array
{
    $st = $pdo->prepare(
        'SELECT o.id, o.order_number, o.total_amount, o.payment_status, o.created_at,
                c.first_name, c.last_name, c.email, c.phone_number, c.whatsapp_number, c.city, c.state, c.pincode
           FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
          WHERE o.order_number = ? LIMIT 1'
    );
    $st->execute([$orderNumber]);
    $o = $st->fetch(PDO::FETCH_ASSOC);
    if (!$o) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT oi.vip_number_id, v.mobile_number, oi.price_at_purchase AS price, oi.network_preference
           FROM order_items oi JOIN vip_numbers v ON v.id = oi.vip_number_id
          WHERE oi.order_id = ? ORDER BY oi.vip_number_id'
    );
    $st->execute([$o['id']]);
    $o['items'] = $st->fetchAll(PDO::FETCH_ASSOC);
    $o['meta']  = cf_payment_meta($pdo, (int) $o['id']) ?? [];
    return $o;
}

/** ['9999911111, 9999922222', 'Jio, Airtel'] */
function cf_join_items(array $items): array
{
    $nums = array_column($items, 'mobile_number');
    $nets = array_values(array_unique(array_filter(array_map('trim', array_column($items, 'network_preference')))));
    return [implode(', ', $nums), $nets ? implode(', ', $nets) : '-'];
}

/** 4500 -> "4,500", 4500.5 -> "4,500.50" */
function cf_plain_amount($n): string
{
    $n = (float) $n;
    return floor($n) == $n ? number_format($n) : number_format($n, 2);
}

/** Data for the Telegram helpers and the WhatsApp templates. @return array{0:array,1:array,2:string} [$tgOrder, $waData, $utr] */
function cf_alert_data(array $o): array
{
    $ck  = $o['meta']['checkout'] ?? [];
    $gw  = $o['meta']['gateway'] ?? [];
    [$vip, $network] = cf_join_items($o['items']);
    $utr = (string) ($gw['utr'] ?? '') !== '' ? (string) $gw['utr'] : (string) ($gw['transaction_id'] ?? '');

    $group = (string) ($gw['payment']['payment_group'] ?? '');
    $reasons = [
        'abandoned_or_failed_attempt'  => 'Customer left the payment page or the payment failed',
        'cashfree_create_order_failed' => 'Could not start the payment (gateway error)',
    ];

    $tg = [
        'first_name' => (string) $o['first_name'], 'last_name' => (string) $o['last_name'],
        'vip_number' => $vip, 'network_preference' => $network,
        'email' => (string) $o['email'], 'order_number' => $o['order_number'], 'total_amount' => (float) $o['total_amount'],
        'phone_number' => (string) $o['phone_number'], 'whatsapp_number' => (string) $o['whatsapp_number'],
        'city' => (string) $o['city'], 'state' => (string) $o['state'], 'pincode' => (string) $o['pincode'],
        'coupon_code' => (string) ($ck['coupon_code'] ?? ''), 'coupon_discount' => (float) ($ck['discount'] ?? 0),
        'store_discount' => (float) ($ck['store_discount'] ?? 0),
        'payment_mode' => $group === '' ? '' : ($group === 'upi' ? 'UPI' : ucwords(str_replace('_', ' ', $group))),
        'reason' => $reasons[(string) ($gw['reason'] ?? '')] ?? '',
    ];
    $wa = [
        'name' => trim($o['first_name'] . ' ' . $o['last_name']) ?: 'Customer',
        'vip_number' => $vip, 'vip_number_id' => (int) ($o['items'][0]['vip_number_id'] ?? 0), 'network' => $network,
        'order_id' => $o['order_number'], 'amount' => cf_plain_amount($o['total_amount']),
        'utr' => $utr !== '' ? $utr : 'N/A', 'email' => (string) $o['email'], 'city' => (string) $o['city'], 'state' => (string) $o['state'],
    ];
    return [$tg, $wa, $utr];
}

function cf_alert_telegram(string $kind, array $o): bool
{
    if (!function_exists('send_admin_alert_on_success')) {
        return false;
    }
    [$tg, , $utr] = cf_alert_data($o);
    $amount = (float) $o['total_amount'];
    if ($kind === 'paid') {
        return (bool) send_admin_alert_on_success($tg, $utr, $amount);
    }
    if ($kind === 'failed') {
        return (bool) send_admin_alert_on_failed($tg, $amount);
    }
    return function_exists('send_admin_alert_refund_needed') ? (bool) send_admin_alert_refund_needed($tg, $amount) : false;
}

/** true = delivered OR nothing to send (no valid number / alert kind not for customers / walked away quietly). */
function cf_alert_whatsapp(string $kind, array $o): bool
{
    if (!in_array($kind, ['paid', 'failed'], true) || !function_exists('wa_send_payment_success')) {
        return true;
    }
    if ($kind === 'failed' && (string) ($o['meta']['gateway']['reason'] ?? '') !== 'abandoned_or_failed_attempt') {
        return true;                                        // gateway-side problem: the customer already saw an error page
    }
    $phone = wa_resolve_phone((string) $o['phone_number'], (string) $o['whatsapp_number']);
    if (strlen($phone) !== 10) {
        return true;
    }
    [, $wa] = cf_alert_data($o);
    $r = $kind === 'paid' ? wa_send_payment_success($phone, $wa) : wa_send_payment_failed($phone, $wa);
    return !empty($r['skipped']) || !empty($r['success']);
}

/** Queue an alert to go out after the response is sent. */
function cf_notify_later(string $kind, PDO $pdo, string $orderNumber): void
{
    cf_defer(static function () use ($kind, $pdo, $orderNumber) {
        cf_notify_event($pdo, $kind, $orderNumber);
    });
}

/** Send the alert for one order (both channels, each at most once); records what was delivered. */
function cf_notify_event(PDO $pdo, string $kind, string $orderNumber): void
{
    cf_alerts_load();
    $lock = 'cf_alert_' . substr(sha1($kind . $orderNumber), 0, 24);
    $got  = false;                                              // true only if we hold the named lock
    try {
        try {
            $got = (int) $pdo->query('SELECT GET_LOCK(' . $pdo->quote($lock) . ', 0)')->fetchColumn() === 1;
            if (!$got) {
                return;                                         // another request is sending it right now
            }
        } catch (Throwable $e) {
            $got = false;                                       // GET_LOCK unavailable: carry on without it
        }

        $o = cf_order_snapshot($pdo, $orderNumber);
        if (!$o) {
            return;
        }
        $expect = ['paid' => CF_PAY_PAID, 'failed' => CF_PAY_FAILED];
        if (isset($expect[$kind]) && $o['payment_status'] !== $expect[$kind]) {
            return;                                             // state changed in the meantime
        }
        $key = 'alerts_' . $kind;
        if (!empty($o['meta'][$key . '_done'])) {
            return;
        }
        $st    = (array) ($o['meta'][$key] ?? []);
        $tries = (int) ($st['tries'] ?? 0);
        if ($tries >= 10) {
            return;
        }

        $tg = !empty($st['tg']);
        $wa = !empty($st['wa']);
        if (!$tg) {
            try { $tg = cf_alert_telegram($kind, $o); } catch (Throwable $e) { cf_log('telegram alert error', ['order' => $orderNumber, 'error' => $e->getMessage()]); }
        }
        $waOn = cf_wa_enabled();
        if ($waOn && !$wa) {
            try { $wa = cf_alert_whatsapp($kind, $o); } catch (Throwable $e) { cf_log('whatsapp alert error', ['order' => $orderNumber, 'error' => $e->getMessage()]); }
        }

        $done = $tg && (!$waOn || $wa);
        cf_payment_patch($pdo, (int) $o['id'], [$key => ['tg' => $tg, 'wa' => $wa, 'tries' => $tries + 1], $key . '_done' => $done]);
        if (!$done) {
            cf_log('alert not fully delivered - cron will retry', ['kind' => $kind, 'order' => $orderNumber, 'telegram' => $tg, 'whatsapp' => $waOn ? $wa : 'off']);
        }
    } catch (Throwable $e) {
        cf_log('alert error', ['kind' => $kind, 'order' => $orderNumber, 'error' => $e->getMessage()]);
    } finally {
        if ($got) {
            try {
                $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote($lock) . ')');
            } catch (Throwable $e) {
            }
        }
    }
}
