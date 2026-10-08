<?php
/**
 * process_checkout.php
 *
 * POST target of your checkout form. Flow:
 *   1. validate input + cart
 *   2. DB transaction: lock numbers (FOR UPDATE) -> coupon -> customer -> order (pending)
 *      -> numbers 'reserved' -> COMMIT
 *   3. create the Cashfree order (outside the DB transaction, so no row locks are held
 *      during a network call). If Cashfree fails -> order marked failed, numbers released.
 *   4. send the buyer to Cashfree checkout.
 *
 * ASSUMPTIONS you may need to adjust (search for "ADJUST"):
 *   - $pdo is created in admin/config.php
 *   - number comes from POST vip_number_id (buy-now form) or $_SESSION['cart']   (pc_cart_items)
 *   - vip_numbers.price = ORIGINAL (struck-through) price; the store discount sits in another
 *     column, auto-detected by name (pc_line_price) - see the PC_* overrides there
 *   - coupons table = code, discount_percentage, category_id      (pc_apply_coupon)
 *   - form field names = customers column names             (pc_read_input)
 *   - your checkout page shows $_SESSION['checkout_error']  (pc_fail)
 */

declare(strict_types=1);

require_once __DIR__ . '/admin/config.php';      // $pdo + CASHFREE_* + SITE_URL
require_once __DIR__ . '/cashfree_helper.php';
require_once __DIR__ . '/evidence.php';          // chargeback evidence log

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);   // transactions below rely on exceptions

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Cache-Control: no-store');

const PC_CHECKOUT_PATH = '/checkout.php';   // ADJUST: your checkout page (it is opened as checkout.php?id=<vip_number_id>)

class CheckoutException extends RuntimeException
{
    /** Technical detail, shown only while CASHFREE_DEBUG is on. */
    public $detail = '';

    public function __construct(string $message, string $detail = '')
    {
        parent::__construct($message);
        $this->detail = $detail;
    }
}

function pc_url(string $path): string
{
    return rtrim(SITE_URL, '/') . $path;
}

/** Where "Go back" leads: the page the buyer came from (keeps ?id=...), else checkout.php?id=<number>, else the store. */
function pc_back_url(): string
{
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref !== '') {
        $strip = static function ($h) { return strtolower(preg_replace('/^www\./i', '', (string) $h)); };
        if ($strip(parse_url($ref, PHP_URL_HOST)) === $strip(parse_url(SITE_URL, PHP_URL_HOST))) {
            return $ref;                                   // same site only - never an open redirect
        }
    }
    $id = (int) (is_array($_POST['vip_number_id'] ?? null) ? reset($_POST['vip_number_id']) : ($_POST['vip_number_id'] ?? 0));
    return $id > 0 ? pc_url(PC_CHECKOUT_PATH . '?id=' . $id) : pc_url('/store.php');
}

/** Flatten $_POST to [name, value] pairs (for re-posting the form). */
function pc_flatten(array $data, string $prefix = ''): array
{
    $out = [];
    foreach ($data as $k => $v) {
        $name = $prefix === '' ? (string) $k : $prefix . '[' . $k . ']';
        if (is_array($v)) {
            $out = array_merge($out, pc_flatten($v, $name));
        } else {
            $out[] = [$name, (string) $v];
        }
    }
    return $out;
}

function pc_debug_lines(string $detail = ''): array
{
    if (!(defined('CASHFREE_DEBUG') && CASHFREE_DEBUG)) {
        return [];
    }
    return array_values(array_filter([
        $detail !== '' ? 'Detail: ' . $detail : '',
        'Cashfree: ' . CASHFREE_ENV . ' / API ' . CASHFREE_API_VERSION,
        'Posted fields: ' . (implode(', ', array_keys($_POST)) ?: '(none)'),
        'Session keys: ' . (implode(', ', array_keys($_SESSION)) ?: '(none)'),
        'Debug mode is ON - set CASHFREE_DEBUG to false before real customers use the site.',
    ]));
}

/** Show a branded error page (never a blind redirect) and stop. */
function pc_fail(string $message, string $detail = ''): void
{
    $_SESSION['checkout_error'] = $message;                                   // kept for pages that read it
    $_SESSION['checkout_old']   = array_diff_key($_POST, ['csrf_token' => 1]);

    cf_page('Order not placed', cf_status_card([
        'tone'    => 'error',
        'title'   => "We couldn't place your order",
        'text'    => $message,
        'buttons' => [
            ['Go back to checkout', pc_back_url(), 'primary', 'if (history.length > 1) { history.back(); return false; }'],
            ['Browse numbers', pc_url('/store.php'), 'secondary'],
        ],
        'debug'   => pc_debug_lines($detail),
    ]));
    exit;
}

/**
 * The previous payment attempt is still closing (Cashfree terminates orders asynchronously) or a
 * payment is in progress. Show a branded "one moment" page that re-submits the SAME form
 * automatically (what a manual refresh used to do), at most 5 times.
 */
function pc_wait_page(string $reason): void
{
    $retry = (int) ($_POST['pc_retry'] ?? 0);
    $auto  = $retry < 5;

    $inputs = '';
    foreach (pc_flatten($_POST) as [$n, $v]) {
        if ($n !== 'pc_retry') {
            $inputs .= '<input type="hidden" name="' . cf_esc($n) . '" value="' . cf_esc($v) . '">';
        }
    }
    $inputs .= '<input type="hidden" name="pc_retry" value="' . ($retry + 1) . '">';

    $inProgress = ($reason === 'payment_in_progress');
    $extra = '<form id="retry" method="post" action="' . cf_esc($_SERVER['SCRIPT_NAME'] ?? 'process_checkout.php') . '" class="mt-6 text-center">'
           . $inputs
           . ($auto ? '<p class="mb-4 text-sm text-slate-500">Trying again in <span id="sec" class="font-semibold text-[#1e4475]">5</span> seconds…</p>' : '')
           . '<button type="submit" class="inline-flex items-center justify-center rounded-full bg-[#1e4475] px-7 py-2.5 text-[15px] font-medium text-white transition hover:bg-[#2a5b9e] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#f5be29] focus-visible:ring-offset-2">Try again now</button>'
           . '</form>';

    $scripts = $auto ? '<script>(function(){var f=document.getElementById("retry"),s=document.getElementById("sec"),n=5;if(!f||!s)return;'
             . 'var t=setInterval(function(){n--;s.textContent=n;if(n<=0){clearInterval(t);f.submit();}},1000);})();</script>' : '';

    cf_page('One moment', cf_status_card([
        'tone'    => 'wait',
        'spin'    => $auto,
        'title'   => $inProgress ? 'Your earlier payment is still processing' : 'Closing your earlier payment attempt',
        'text'    => $inProgress
            ? 'A payment you started earlier is still being processed by your bank or UPI app. Please don’t pay from another app — we’ll check again automatically.'
            : 'You started a payment earlier and didn’t finish it. We’re closing it so you can pay again — this takes a few seconds.',
        'extra'   => $extra,
        'debug'   => pc_debug_lines('previous order reason: ' . $reason . ', retry #' . $retry),
    ]), $scripts);
    exit;
}

function pc_str(string $key, int $max = 100): string
{
    $v = trim((string) ($_POST[$key] ?? ''));
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}

function pc_phone(string $raw): string
{
    $d = preg_replace('/\D+/', '', $raw);
    return strlen($d) > 10 ? substr($d, -10) : $d;   // drop +91 / 0 prefix
}

/** ADJUST: field names must match your checkout form. */
function pc_read_input(): array
{
    $in = [
        'first_name'      => pc_str('first_name'),
        'last_name'       => pc_str('last_name'),
        'email'           => strtolower(pc_str('email', 254)),
        'phone_number'    => pc_phone(pc_str('phone_number', 20)),
        'whatsapp_number' => pc_phone(pc_str('whatsapp_number', 20)),
        'state'           => pc_str('state'),
        'city'            => pc_str('city'),
        'pincode'         => pc_str('pincode', 10),
        'coupon_code'     => strtoupper(pc_str('coupon_code', 50) ?: pc_str('applied_coupon', 50)),
    ];
    if (in_array($in['coupon_code'], ['0', 'NULL', 'UNDEFINED', 'NONE', 'FALSE'], true)) {
        $in['coupon_code'] = '';
    }

    if (empty($_POST['accept_terms']))                              throw new CheckoutException('Please accept the Terms and Refund Policy to continue.');
    if ($in['first_name'] === '')                                   throw new CheckoutException('Please enter your first name.');
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL) || strlen($in['email']) > 100) throw new CheckoutException('Please enter a valid email address (max 100 characters).');
    if (!preg_match('/^[6-9]\d{9}$/', $in['phone_number']))         throw new CheckoutException('Please enter a valid 10-digit mobile number.');
    if ($in['whatsapp_number'] === '')                              $in['whatsapp_number'] = $in['phone_number'];
    if (!preg_match('/^[6-9]\d{9}$/', $in['whatsapp_number']))      throw new CheckoutException('Please enter a valid 10-digit WhatsApp number.');
    if ($in['state'] === '' || $in['city'] === '')                  throw new CheckoutException('Please enter your state and city.');
    if (!preg_match('/^\d{6}$/', $in['pincode']))                   throw new CheckoutException('Please enter a valid 6-digit pincode.');

    return $in;
}

/**
 * Which numbers is the buyer purchasing? Returns [vip_number_id => network_preference].
 *   1) "Buy now" form:  POST vip_number_id (one id, "12,15", or vip_number_id[]) + network_preference
 *   2) otherwise the session cart: $_SESSION['cart'] = [12, 15]  or  [['vip_number_id'=>12,'network_preference'=>'Jio'], ...]
 * Prices are NEVER taken from the request - they are re-read from the DB.
 */
function pc_cart_items(): array
{
    $items = [];

    if (isset($_POST['vip_number_id'])) {
        $raw = $_POST['vip_number_id'];
        $ids = is_array($raw) ? $raw : explode(',', (string) $raw);
        $nets = $_POST['network_preference'] ?? '';
        foreach ($ids as $k => $id) {
            $id = (int) trim((string) $id);
            if ($id > 0) {
                $net = is_array($nets) ? ($nets[$id] ?? $nets[$k] ?? '') : $nets;
                $items[$id] = substr(trim((string) $net), 0, 50);
            }
        }
        if ($items) {
            return $items;
        }
    }

    foreach ((array) ($_SESSION['cart'] ?? []) as $row) {
        if (is_array($row)) {
            $id  = (int) ($row['vip_number_id'] ?? $row['id'] ?? 0);
            $net = (string) ($row['network_preference'] ?? $row['network'] ?? '');
        } else {
            $id  = (int) $row;
            $net = '';
        }
        if ($id > 0) {
            $items[$id] = substr($net, 0, 50);
        }
    }
    return $items;
}

function pc_paise($v): int
{
    return (int) round((float) $v * 100);
}

/**
 * What ONE number costs the buyer BEFORE coupon = the "Total Payable" checkout.php shows.
 *
 * vip_numbers.price is the ORIGINAL (struck-through) price. The discounted price lives in another column,
 * detected by name: final price (discount_price, sale_price, offer_price, ...), else discount amount
 * (discount_amount, store_discount, ...), else discount percent (discount_percent, ...).
 * If a discount column exists but is ambiguous / unknown the order is REFUSED - never guessed.
 * Force the right column in admin/config.php if needed:
 *   define('PC_FINAL_PRICE_COL',      'column');   // holds the discounted selling price
 *   define('PC_DISCOUNT_AMOUNT_COL',  'column');   // OR holds the discount in rupees
 *   define('PC_DISCOUNT_PERCENT_COL', 'column');   // OR holds the discount in percent
 *   define('PC_ORIGINAL_PRICE_COL',   'column');   // only if the original price is not `price`
 *
 * @return array{original:int,final:int,source:string}  paise
 */
function pc_line_price(array $row): array
{
    $row  = array_change_key_case($row, CASE_LOWER);
    $cols = implode(', ', array_keys($row));
    $fail = static function (string $detail) use ($cols) {
        return new CheckoutException('We couldn’t calculate the price for this number right now. Please try again shortly or message us on WhatsApp.', $detail . ' | vip_numbers columns: ' . $cols);
    };

    $origCol = defined('PC_ORIGINAL_PRICE_COL') ? strtolower(PC_ORIGINAL_PRICE_COL) : 'price';
    $orig    = pc_paise($row[$origCol] ?? 0);
    if ($orig < 100) {
        throw $fail('original price column "' . $origCol . '" is missing or below 1');
    }

    $modes = [
        'final'   => ['PC_FINAL_PRICE_COL',      ['final_price', 'selling_price', 'sale_price', 'offer_price', 'discounted_price', 'discount_price', 'special_price', 'our_price', 'current_price', 'new_price']],
        'amount'  => ['PC_DISCOUNT_AMOUNT_COL',  ['discount_amount', 'store_discount', 'discount_value', 'flat_discount', 'discount_rs']],
        'percent' => ['PC_DISCOUNT_PERCENT_COL', ['discount_percent', 'discount_percentage', 'discount_pct', 'discount_per']],
    ];
    $forced = array_filter($modes, static function ($m) { return defined($m[0]); });
    if ($forced) {
        $modes = $forced;
    }

    $used = [];
    foreach ($modes as $mode => [$const, $names]) {
        $names = defined($const) ? [strtolower(constant($const))] : $names;
        foreach ($names as $n) {
            if (!array_key_exists($n, $row)) {
                continue;
            }
            $used[] = $n;
            $v = (float) $row[$n];
            if ($v <= 0) {
                continue;                                          // this number has no discount in that column
            }
            if ($mode === 'final') {
                $final = pc_paise($v);
            } elseif ($mode === 'amount') {
                $final = $orig - pc_paise($v);
            } else {
                $final = (int) round($orig - $orig * $v / 100);
            }
            if ($final < 100 || $final > $orig) {
                throw $fail('column "' . $n . '" (' . $mode . ') gives an impossible price: ' . $final / 100 . ' from original ' . $orig / 100);
            }
            return ['original' => $orig, 'final' => $final, 'source' => $mode . ':' . $n . ' (original: ' . $origCol . ')'];
        }
    }

    // Any OTHER numeric discount-looking column we did not recognise (e.g. plain "discount") => refuse to guess.
    foreach ($row as $k => $v) {
        if (!in_array($k, $used, true) && $k !== $origCol && is_numeric($v) && (float) $v > 0
            && preg_match('/(^|_)(discount|offer|sale|selling|final|mrp|special|deal)(_|$)/', $k)) {
            throw $fail('unrecognised price column "' . $k . '" = ' . $v . ' (amount or percent? set PC_DISCOUNT_AMOUNT_COL / PC_DISCOUNT_PERCENT_COL / PC_FINAL_PRICE_COL in admin/config.php)');
        }
    }

    return ['original' => $orig, 'final' => $orig, 'source' => 'no discount (original: ' . $origCol . ')'];
}

/**
 * Coupons table (as used by checkout.php): code, discount_percentage, category_id (0 = every category).
 * The percent is applied to each number's store-discounted price and rounded to whole rupees,
 * exactly like the checkout page does. status / expiry / usage columns are enforced if your table has them.
 *
 * @param array $lines [['category'=>int,'final'=>paise], ...]
 * @return array{code:string,discount:int}  discount in paise
 */
function pc_apply_coupon(PDO $pdo, string $code, array $lines): array
{
    $st = $pdo->prepare('SELECT * FROM coupons WHERE code = ? LIMIT 1');
    $st->execute([$code]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c) {
        throw new CheckoutException('This coupon code is not valid.');
    }
    $c = array_change_key_case($c, CASE_LOWER);

    if ((array_key_exists('status', $c) && !in_array(strtolower((string) $c['status']), ['active', '1', 'enabled', 'yes', 'on'], true))
        || (array_key_exists('is_active', $c) && !(int) $c['is_active'])) {
        throw new CheckoutException('This coupon code is not valid.');
    }
    foreach (['expiry_date', 'expires_at', 'valid_until', 'end_date', 'expiry'] as $k) {
        if (!empty($c[$k]) && (string) $c[$k] !== '0000-00-00' && (string) $c[$k] !== '0000-00-00 00:00:00') {
            $v = (string) $c[$k];
            $t = strtotime(strlen($v) <= 10 ? $v . ' 23:59:59' : $v);
            if ($t !== false && $t < time()) {
                throw new CheckoutException('This coupon has expired.');
            }
        }
    }
    if (!empty($c['usage_limit']) && (int) ($c['used_count'] ?? 0) >= (int) $c['usage_limit']) {
        throw new CheckoutException('This coupon has reached its usage limit.');
    }

    $pct = null;
    foreach (['discount_percentage', 'discount_percent'] as $k) {
        if (array_key_exists($k, $c)) {
            $pct = (float) $c[$k];
            break;
        }
    }
    if ($pct === null) {
        throw new CheckoutException('This coupon can’t be applied right now.', 'coupons has no discount_percentage column. Columns: ' . implode(', ', array_keys($c)));
    }

    $cat = (int) ($c['category_id'] ?? 0);
    $discount = 0;
    $applicable = false;
    foreach ($lines as $l) {
        if ($cat === 0 || $cat === $l['category']) {
            $applicable = true;
            $discount += (int) round(($l['final'] / 100) * ($pct / 100)) * 100;   // same maths as checkout.php's JS
        }
    }
    if (!$applicable) {
        throw new CheckoutException('This coupon is not valid for the selected number.');
    }

    $subtotal = array_sum(array_column($lines, 'final'));
    return ['code' => (string) $c['code'], 'discount' => max(0, min($discount, $subtotal))];
}

/** Same buyer + same numbers + same details => same fingerprint (lets a second click re-open the first order). */
function pc_fingerprint(array $in, array $cart): string
{
    ksort($cart);
    return sha1(json_encode([$cart, $in], JSON_UNESCAPED_UNICODE));
}

/** @return array{id:int,payment_status:string,total:float,ids:int[],meta:array}|null */
function pc_load_order(PDO $pdo, string $orderNumber): ?array
{
    $st = $pdo->prepare('SELECT id, total_amount, payment_status FROM orders WHERE order_number = ? LIMIT 1');
    $st->execute([$orderNumber]);
    $o = $st->fetch(PDO::FETCH_ASSOC);
    if (!$o) {
        return null;
    }
    $st = $pdo->prepare('SELECT vip_number_id FROM order_items WHERE order_id = ?');
    $st->execute([$o['id']]);
    $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    sort($ids);
    return ['id' => (int) $o['id'], 'payment_status' => (string) $o['payment_status'], 'total' => (float) $o['total_amount'],
            'ids' => $ids, 'meta' => cf_payment_meta($pdo, (int) $o['id']) ?? []];
}

/** "Taking you to secure payment" - hands the payment_session_id to Cashfree.js. Never returns. */
function pc_render_bridge(string $orderNumber, int $totalPaise, string $sessionId, array $meta): void
{
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $scripts = '<script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script><script>(function(){'
             . 'var cashfree=Cashfree({mode:' . json_encode(CASHFREE_SDK_MODE, $jsonFlags) . '});'
             . 'cashfree.checkout({paymentSessionId:' . json_encode($sessionId, $jsonFlags) . ',redirectTarget:"_self"});'
             . '})();</script>';

    $rows = [['Order number', $orderNumber]];
    $orig = (float) ($meta['original_total'] ?? 0);
    $sd   = (float) ($meta['store_discount'] ?? 0);
    $cd   = (float) ($meta['discount'] ?? 0);
    if ($sd > 0 && $orig > 0) {
        $rows[] = ['Original price', cf_inr($orig)];
        $rows[] = ['Store discount', '− ' . cf_inr($sd)];
    }
    if ($cd > 0) {
        $rows[] = ['Coupon ' . ($meta['coupon_code'] ?? ''), '− ' . cf_inr($cd)];
    }
    $rows[] = ['Total payable', cf_inr($totalPaise / 100)];

    cf_page('Redirecting to secure payment', cf_status_card([
        'tone'  => 'wait',
        'spin'  => true,
        'title' => 'Taking you to secure payment',
        'text'  => 'Please don’t refresh or press back. Your number is reserved for ' . CF_ORDER_MINUTES . ' minutes while you pay.',
        'rows'  => $rows,
        'extra' => '<noscript><p class="mt-4 text-center text-sm text-red-600">JavaScript is required to complete the payment. Please enable it and try again.</p></noscript>',
        'debug' => pc_debug_lines('price source: ' . ($meta['price_source'] ?? '?')),
    ]), $scripts);
    exit;
}

/**
 * Step 2: everything that must be atomic. Returns the created order.
 * @return array{order_id:int,order_number:string,customer_id:int,total_paise:int,meta:array}
 */
function pc_create_order(PDO $pdo, array $in, array $cart, string $fingerprint): array
{
    $ids = array_keys($cart);
    sort($ids);
    $ph = implode(',', array_fill(0, count($ids), '?'));

    $pdo->beginTransaction();
    try {
        // Lock the numbers (ascending id => no deadlocks between concurrent buyers).
        $st = $pdo->prepare("SELECT * FROM vip_numbers WHERE id IN ($ph) ORDER BY id FOR UPDATE");
        $st->execute($ids);
        $rows = array_map(static function ($r) { return array_change_key_case($r, CASE_LOWER); }, $st->fetchAll(PDO::FETCH_ASSOC));

        if (count($rows) !== count($ids)) {
            throw new CheckoutException('We couldn\'t find the number you selected. Please go back and choose again.');
        }
        $unavailable = [];
        foreach ($rows as $r) {
            if (strtolower((string) $r['status']) !== 'available') {
                $unavailable[] = $r['mobile_number'];
            }
        }
        if ($unavailable) {
            throw new CheckoutException('Sorry, ' . implode(', ', $unavailable) . ' is no longer available. Someone else may have just booked it — please choose another number.');
        }

        // Price = what checkout.php shows as Total Payable (store discount applied), then coupon.
        $lines = [];
        $original = 0;
        $subtotal = 0;                                     // paise, after store discount
        $priceSource = '';
        foreach ($rows as $r) {
            $pr = pc_line_price($r);
            $lines[] = ['id' => (int) $r['id'], 'category' => (int) ($r['category_id'] ?? 0), 'original' => $pr['original'], 'final' => $pr['final']];
            $original += $pr['original'];
            $subtotal += $pr['final'];
            $priceSource = $pr['source'];
        }

        $coupon = ['code' => '', 'discount' => 0];
        if ($in['coupon_code'] !== '') {
            $coupon = pc_apply_coupon($pdo, $in['coupon_code'], $lines);
        }
        $total = $subtotal - $coupon['discount'];
        if ($total < 100) {                                // Cashfree minimum is ₹1
            throw new CheckoutException('Order total must be at least ₹1.');
        }

        // Customer: reuse by email + phone, otherwise insert
        $st = $pdo->prepare('SELECT id FROM customers WHERE email = ? AND phone_number = ? LIMIT 1');
        $st->execute([$in['email'], $in['phone_number']]);
        $customerId = (int) $st->fetchColumn();
        if ($customerId) {
            $pdo->prepare('UPDATE customers SET first_name = ?, last_name = ?, whatsapp_number = ?, state = ?, city = ?, pincode = ? WHERE id = ?')
                ->execute([$in['first_name'], $in['last_name'], $in['whatsapp_number'], $in['state'], $in['city'], $in['pincode'], $customerId]);
        } else {
            $pdo->prepare('INSERT INTO customers (first_name, last_name, email, phone_number, whatsapp_number, state, city, pincode) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$in['first_name'], $in['last_name'], $in['email'], $in['phone_number'], $in['whatsapp_number'], $in['state'], $in['city'], $in['pincode']]);
            $customerId = (int) $pdo->lastInsertId();
        }

        // Order (pending). order_number doubles as the Cashfree order_id (3-45 chars, [A-Za-z0-9_-]).
        $orderNumber = 'VN' . date('ymd') . strtoupper(bin2hex(random_bytes(4)));
        $pdo->prepare('INSERT INTO orders (order_number, customer_id, total_amount, payment_method, payment_status, order_status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$orderNumber, $customerId, number_format($total / 100, 2, '.', ''), CF_PAYMENT_LABEL, CF_PAY_PENDING, CF_ORDER_PENDING]);
        $orderId = (int) $pdo->lastInsertId();

        // Chargeback evidence: what the buyer agreed to, from where, on which device.
        ev_log($pdo, $orderId, 'terms_accepted', 'customer', ['version' => '2026-10', 'text' => 'Digital product; non-refundable once UPC is delivered', 'email' => $in['email'], 'phone' => $in['phone_number']]);
        ev_log($pdo, $orderId, 'checkout_submitted', 'customer', ['amount' => number_format($total / 100, 2, '.', ''), 'numbers' => array_keys($cart)]);

        $ins = $pdo->prepare('INSERT INTO order_items (order_id, vip_number_id, price_at_purchase, network_preference) VALUES (?, ?, ?, ?)');
        foreach ($lines as $l) {                           // price_at_purchase = price actually charged for the number (before coupon)
            $ins->execute([$orderId, $l['id'], number_format($l['final'] / 100, 2, '.', ''), $cart[$l['id']]]);
        }

        // Reserve
        $upd = $pdo->prepare("UPDATE vip_numbers SET status = 'reserved' WHERE id IN ($ph) AND status = 'available'");
        $upd->execute($ids);
        if ($upd->rowCount() !== count($ids)) {
            throw new RuntimeException('Reservation mismatch for ' . $orderNumber);
        }

        // Pending payment row; also keeps coupon info until the order is paid.
        $meta = [
            'coupon_code'    => $coupon['code'],
            'discount'       => $coupon['discount'] / 100,          // coupon discount (Rs)
            'subtotal'       => $subtotal / 100,                    // sum of store-discounted prices
            'original_total' => $original / 100,
            'store_discount' => ($original - $subtotal) / 100,
            'price_source'   => $priceSource,
            'fingerprint'    => $fingerprint,
        ];
        cf_save_payment($pdo, $orderId, $orderNumber, $total / 100, CF_PAY_PENDING, ['checkout' => $meta]);

        $pdo->commit();
        return ['order_id' => $orderId, 'order_number' => $orderNumber, 'customer_id' => $customerId, 'total_paise' => $total, 'meta' => $meta];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* =====================================================================
 * Request handling
 * ===================================================================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . pc_url('/store.php'));
    exit;
}

// CSRF (enforced only if your checkout page put a token in the session)
if (!empty($_SESSION['csrf_token']) && !hash_equals((string) $_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
    pc_fail('Your session expired. Please try again.');
}

/**
 * WhatsApp OTP: checkout.php has it switched off right now (hidden flag defaults to 1). If you switch it back on there,
 * set define('WA_OTP_REQUIRED', true) in admin/config.php - then the server also insists on a number verified by
 * verify_otp.php in this browser session (a hidden form field alone can be faked).
 */
function pc_check_wa_otp(array $in): void
{
    if (!(defined('WA_OTP_REQUIRED') && WA_OTP_REQUIRED)) {
        return;
    }
    $verified = !empty($_SESSION['wa_otp_verified']) && in_array((string) ($_SESSION['wa_otp_verified_phone'] ?? ''), [$in['phone_number'], $in['whatsapp_number']], true);
    if (!$verified) {
        throw new CheckoutException('Please verify your WhatsApp number with the OTP first.');
    }
}

/**
 * SMS OTP check (apitxt.com) — enforced independently of WA_OTP_REQUIRED.
 * Session keys used: sms_otp_verified, sms_otp_verified_phone (set by sms_otp_handler.php).
 * The hidden form field sms_otp_verified is an extra client-side signal but we
 * always validate from the session — the field alone cannot be trusted.
 */
function pc_check_sms_otp(array $in): void
{
    // Only enforce when APITXT_AUTHKEY is configured
    if (!defined('APITXT_AUTHKEY') || APITXT_AUTHKEY === '' || APITXT_AUTHKEY === 'YOUR_APITXT_AUTHKEY_HERE') {
        return;
    }
    $verifiedPhone = (string) ($_SESSION['sms_otp_verified_phone'] ?? '');
    $verified = !empty($_SESSION['sms_otp_verified'])
        && in_array($verifiedPhone, [$in['phone_number'], $in['whatsapp_number']], true);
    if (!$verified) {
        throw new CheckoutException('Please verify your mobile number via SMS OTP before proceeding to payment.');
    }
}

try {
    $in   = pc_read_input();
    pc_check_wa_otp($in);
    pc_check_sms_otp($in);
    $cart = pc_cart_items();
    if (!$cart) {
        throw new CheckoutException('No number selected. Please choose a number and try again.');
    }
} catch (CheckoutException $e) {
    pc_fail($e->getMessage(), $e->detail);
}

// ---- Buyer already has an unpaid order from this browser (came back from Cashfree, clicked Pay again) ----
$ids         = array_keys($cart);
sort($ids);
$fingerprint = pc_fingerprint($in, $cart);
$prevNo      = (string) ($_SESSION['cf_pending_order'] ?? '');

if ($prevNo !== '') {
    $prev = pc_load_order($pdo, $prevNo);

    if (!$prev || $prev['payment_status'] !== CF_PAY_PENDING) {
        unset($_SESSION['cf_pending_order']);                     // already settled (a paid order is shown by the callback)
    } elseif (array_intersect($prev['ids'], $ids)) {
        // Same number(s). Same details => simply re-open the SAME Cashfree order: nothing to cancel, nothing to wait for.
        $ck = $prev['meta']['checkout'] ?? [];
        if ($prev['ids'] === $ids && ($ck['fingerprint'] ?? '') === $fingerprint && !empty($ck['payment_session_id'])) {
            $cf = cf_get_order($prevNo);
            $cfStatus = strtoupper((string) ($cf['data']['order_status'] ?? ''));
            if ($cf['ok'] && $cfStatus === 'ACTIVE') {
                pc_render_bridge($prevNo, (int) round($prev['total'] * 100), (string) $ck['payment_session_id'], $ck);
            }
            if ($cf['ok'] && $cfStatus === 'PAID') {
                header('Location: ' . pc_url('/payment_callback.php?order_id=' . rawurlencode($prevNo)));
                exit;
            }
        }
        // Details changed (or the old order is dead): close it first so the number is free again.
        $r = cf_settle_order($pdo, $prevNo, true, 5, 'superseded_by_new_checkout');   // waits up to 5s - Cashfree closes orders asynchronously; no alerts for this
        if ($r['state'] === 'paid') {
            header('Location: ' . pc_url('/payment_callback.php?order_id=' . rawurlencode($prevNo)));
            exit;
        }
        if ($r['state'] === 'pending') {
            pc_wait_page((string) ($r['reason'] ?? 'terminating'));
        }
        unset($_SESSION['cf_pending_order']);
    }
    // else: the buyer picked a DIFFERENT number - the earlier order keeps its own number and expires by itself.
}

try {
    $order = pc_create_order($pdo, $in, $cart, $fingerprint);
} catch (CheckoutException $e) {
    pc_fail($e->getMessage(), $e->detail);
} catch (Throwable $e) {
    cf_log('checkout db error', ['error' => $e->getMessage()]);
    pc_fail('Something went wrong while placing your order. Please try again.', $e->getMessage());
}

// ---- Cashfree: create order ------------------------------------------------
$name = trim(preg_replace('/[^\p{L}\p{N} ._-]/u', '', $in['first_name'] . ' ' . $in['last_name']));
$payload = [
    'order_id'         => $order['order_number'],
    'order_amount'     => round($order['total_paise'] / 100, 2),
    'order_currency'   => 'INR',
    'customer_details' => [
        'customer_id'    => 'CUST' . $order['customer_id'],       // Cashfree: alphanumeric only, 3-50 chars
        'customer_email' => $in['email'],
        'customer_phone' => $in['phone_number'],
    ],
    'order_meta'       => [
        'return_url' => pc_url('/payment_callback.php?order_id={order_id}'),   // Cashfree fills {order_id}
    ],
    'order_expiry_time' => gmdate('Y-m-d\TH:i:s\Z', time() + CF_ORDER_MINUTES * 60),
    'order_note'       => 'VIP number order ' . $order['order_number'],
];
// customer_name is optional but must be 3-100 chars if sent (e.g. a lone first name "Om" would be rejected).
$nameLen = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
if ($nameLen >= 3) {
    $payload['customer_details']['customer_name'] = $nameLen > 100 ? (function_exists('mb_substr') ? mb_substr($name, 0, 100) : substr($name, 0, 100)) : $name;
}
if (strpos(SITE_URL, 'https://') === 0) {
    $payload['order_meta']['notify_url'] = pc_url('/webhook.php');            // must be HTTPS
}

$api       = cf_create_order($payload);
$sessionId = (string) ($api['data']['payment_session_id'] ?? '');

if (!$api['ok'] || $sessionId === '') {
    cf_log('create order failed', ['order' => $order['order_number'], 'http' => $api['http'], 'error' => $api['error']]);
    try {   // compensate: release the numbers we just reserved
        cf_apply($pdo, $order['order_number'], CF_PAY_FAILED, ['reason' => 'cashfree_create_order_failed', 'http' => $api['http'], 'error' => $api['error']]);   // releases the numbers + alerts the admin
    } catch (Throwable $e) {
        cf_log('compensation failed - cron will release', ['order' => $order['order_number'], 'error' => $e->getMessage()]);
    }
    pc_fail('We could not start the payment. Please try again in a moment.', 'Cashfree HTTP ' . $api['http'] . ': ' . $api['error']);
}

try {
    cf_save_payment($pdo, $order['order_id'], (string) ($api['data']['cf_order_id'] ?? $order['order_number']),
        $order['total_paise'] / 100, CF_PAY_PENDING,
        ['checkout' => $order['meta'] + ['cf_order_id' => $api['data']['cf_order_id'] ?? null, 'payment_session_id' => $sessionId]]);
} catch (Throwable $e) {
    cf_log('could not store payment_session_id', ['order' => $order['order_number'], 'error' => $e->getMessage()]);
}

$_SESSION['cf_pending_order'] = $order['order_number'];
unset($_SESSION['checkout_error'], $_SESSION['checkout_old']);

// Legacy API versions returned payment_link. 2023-08-01 does not, but honour it if present.
$link = (string) ($api['data']['payment_link'] ?? '');
if ($link !== '' && strpos($link, 'https://') === 0) {
    header('Location: ' . $link);
    exit;
}

// Hand payment_session_id to Cashfree.js, which opens the hosted checkout page.
pc_render_bridge($order['order_number'], $order['total_paise'], $sessionId, $order['meta']);
