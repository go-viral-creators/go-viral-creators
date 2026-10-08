<?php
/**
 * evidence.php - tamper-evident order evidence log.
 * require_once __DIR__ . '/evidence.php';   (admin pages: '../evidence.php')
 *
 * ev_log($pdo, $orderId, 'upc_delivered', 'admin', ['channel' => 'email']);
 * Never throws: evidence logging must not break an order.
 */

function ev_ip(): string
{
    // REMOTE_ADDR only: X-Forwarded-For is client-controlled and would weaken the evidence.
    // If you sit behind Cloudflare, set REMOTE_ADDR from CF-Connecting-IP at the web-server level.
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function ev_log(PDO $pdo, int $orderId, string $type, string $actor = 'system', array $details = []): bool
{
    try {
        $st = $pdo->prepare('SELECT row_hash FROM order_evidence WHERE order_id = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$orderId]);
        $prev = (string) ($st->fetchColumn() ?: str_repeat('0', 64));

        $now  = date('Y-m-d H:i:s');
        $json = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $ip   = ev_ip();
        $ua   = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $hash = hash('sha256', implode('|', [$prev, $orderId, $type, $actor, $ip, $json, $now]));

        $pdo->prepare('INSERT INTO order_evidence (order_id, event_type, actor, ip_address, user_agent, details, prev_hash, row_hash, created_at) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$orderId, $type, $actor, $ip, $ua, $json, $prev, $hash, $now]);
        return true;
    } catch (Throwable $e) {
        error_log('[evidence] ' . $e->getMessage());
        return false;
    }
}

/** Re-computes the chain; returns [bool ok, ?int firstBrokenRowId]. */
function ev_verify(PDO $pdo, int $orderId): array
{
    $st = $pdo->prepare('SELECT * FROM order_evidence WHERE order_id = ? ORDER BY id');
    $st->execute([$orderId]);
    $prev = str_repeat('0', 64);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $calc = hash('sha256', implode('|', [$prev, $r['order_id'], $r['event_type'], $r['actor'], $r['ip_address'], $r['details'], $r['created_at']]));
        if ($r['prev_hash'] !== $prev || $r['row_hash'] !== $calc) {
            return [false, (int) $r['id']];
        }
        $prev = $r['row_hash'];
    }
    return [true, null];
}


/**
 * Called from admin update_upc AFTER the UPC is saved. Stamps delivery time, creates the customer's
 * private link, sends it by email + WhatsApp and logs every step (with provider message ids).
 */
function ev_deliver_upc(PDO $pdo, int $orderId, int $orderItemId): void
{
    global $waApiKey, $waSessionId;   // from admin/config.php
    $token = bin2hex(random_bytes(16));
    $pdo->prepare('UPDATE order_items SET upc_view_token = COALESCE(upc_view_token, ?), upc_delivered_at = NOW() WHERE id = ?')
        ->execute([$token, $orderItemId]);
    $st = $pdo->prepare('SELECT upc_view_token FROM order_items WHERE id = ?');
    $st->execute([$orderItemId]);
    $token = (string) $st->fetchColumn();

    $st = $pdo->prepare('SELECT o.order_number, c.first_name, c.email, c.whatsapp_number, c.phone_number, v.mobile_number
                         FROM orders o JOIN customers c ON c.id = o.customer_id
                         JOIN order_items oi ON oi.order_id = o.id JOIN vip_numbers v ON v.id = oi.vip_number_id
                         WHERE oi.id = ?');
    $st->execute([$orderItemId]);
    $o = $st->fetch(PDO::FETCH_ASSOC);
    if (!$o) { return; }

    $link = rtrim(SITE_URL, '/') . '/upc_view.php?t=' . $token;
    ev_log($pdo, $orderId, 'upc_delivered', 'admin', ['admin_id' => $_SESSION['admin_id'] ?? null, 'mobile' => $o['mobile_number']]);

    if (!empty($o['email']) && function_exists('queue_email')) {
        $body = '<p>Hi ' . htmlspecialchars($o['first_name']) . ',</p><p>Your UPC for <b>' . htmlspecialchars($o['mobile_number'])
              . '</b> (order ' . htmlspecialchars($o['order_number']) . ') is ready.</p><p><a href="' . $link . '">Click here to view your UPC</a></p>';
        $ok = queue_email($o['email'], 'Your UPC is ready - ' . $o['order_number'], $body, json_encode([]));
        ev_log($pdo, $orderId, 'upc_email_queued', 'system', ['to' => $o['email'], 'ok' => (bool) $ok]);
    }

    $phone = preg_replace('/\D/', '', $o['whatsapp_number'] ?: $o['phone_number']);
    if ($phone) {
        try {
            if (!class_exists('WAGateway', false)) { require_once __DIR__ . '/include/wa_gateway.php'; }
            $wa  = new WAGateway($waApiKey, $waSessionId);
            $res = $wa->sendText('91' . substr($phone, -10), "Hi {$o['first_name']}, your UPC for {$o['mobile_number']} (order {$o['order_number']}) is ready:\n$link");
            ev_log($pdo, $orderId, 'upc_whatsapp_sent', 'system', ['to' => '91' . substr($phone, -10), 'response' => $res]);
        } catch (Throwable $e) {
            ev_log($pdo, $orderId, 'upc_whatsapp_failed', 'system', ['error' => $e->getMessage()]);
        }
    }
}


/** Readable payment facts from Cashfree's payment object (gateway['payment']). Card numbers are already masked by Cashfree. */
function ev_payment_details($txnId, $amount, array $gateway): array
{
    $p  = is_array($gateway['payment'] ?? null) ? $gateway['payment'] : [];
    $pm = is_array($p['payment_method'] ?? null) ? $p['payment_method'] : [];
    $detail = '';
    if (isset($pm['upi']) && is_array($pm['upi'])) {
        $detail = (string) ($pm['upi']['upi_id'] ?? '');
    } elseif (isset($pm['card']) && is_array($pm['card'])) {
        $c = $pm['card'];
        $detail = trim(($c['card_network'] ?? '') . ' ' . ($c['card_type'] ?? '') . ' ' . ($c['card_number'] ?? '') . ' ' . ($c['card_bank_name'] ?? ''));
    } elseif ($pm) {
        $detail = (string) array_key_first($pm);
    }
    return [
        'txn'           => $txnId,
        'amount'        => $amount,
        'cf_payment_id' => $gateway['cf_payment_id'] ?? ($p['cf_payment_id'] ?? null),
        'utr'           => $gateway['utr'] ?? ($p['bank_reference'] ?? null),
        'method_group'  => $p['payment_group'] ?? null,
        'method_detail' => $detail,
        'paid_at'       => $p['payment_completion_time'] ?? ($p['payment_time'] ?? null),
    ];
}
