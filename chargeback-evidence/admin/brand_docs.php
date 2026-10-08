<?php
/**
 * admin/brand_docs.php - builds the branded Invoice and Evidence Pack as HTML and as PDF (Dompdf).
 * Used by: admin/invoice_print.php, admin/evidence_pack.php, invoice.php (customer), mail/email_worker.php (attachment).
 */
require_once __DIR__ . '/brand.php';
require_once __DIR__ . '/../evidence.php';

/** HTML -> PDF bytes. Needs the dompdf/ folder next to your site root. */
function brand_pdf(string $html, bool $landscape = false): string
{
    $auto = __DIR__ . '/../dompdf/autoload.inc.php';
    if (!is_file($auto)) {
        throw new RuntimeException('PDF library missing: upload the dompdf/ folder to the site root.');
    }
    require_once $auto;
    $opt = new \Dompdf\Options();
    $opt->set('isRemoteEnabled', false);                 // logo is embedded as a data: URI
    $opt->set('defaultFont', 'DejaVu Sans');             // has the Rupee sign
    $opt->set('isFontSubsettingEnabled', true);
    $opt->set('chroot', realpath(__DIR__ . '/..'));
    $opt->set('tempDir', sys_get_temp_dir());
    $dompdf = new \Dompdf\Dompdf($opt);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', $landscape ? 'landscape' : 'portrait');
    $dompdf->render();
    return $dompdf->output();
}

function brand_h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

/** Make long unspaced JSON wrap in PDF cells. */
function brand_wrappable(string $s): string { return preg_replace('/([,;:])(?=\S)/', '$1 ', $s); }

/* =========================================================  INVOICE  ========================================================= */

function brand_invoice_data(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT o.*, c.first_name, c.last_name, c.email, c.phone_number, c.city, c.state, c.pincode,
            p.transaction_id, p.payment_status AS gw_status
            FROM orders o JOIN customers c ON c.id = o.customer_id LEFT JOIN payments p ON p.order_id = o.id WHERE o.id = ?');
    $st->execute([$id]);
    $o = $st->fetch(PDO::FETCH_ASSOC);
    if (!$o) { return null; }
    $st = $pdo->prepare('SELECT oi.price_at_purchase, oi.network_preference, oi.upc_delivered_at, oi.upc_code, v.mobile_number
            FROM order_items oi JOIN vip_numbers v ON v.id = oi.vip_number_id WHERE oi.order_id = ? ORDER BY oi.vip_number_id');
    $st->execute([$id]);
    return ['o' => $o, 'items' => $st->fetchAll(PDO::FETCH_ASSOC), 'dbOff' => brand_db_offset($pdo)];
}

function brand_invoice_html(PDO $pdo, int $id, bool $pdf = false, bool $autoPrint = false): ?string
{
    $d = brand_invoice_data($pdo, $id);
    if (!$d) { return null; }
    ['o' => $o, 'items' => $items, 'dbOff' => $dbOff] = $d;
    $e     = 'brand_h';
    $paid  = strtolower((string) $o['payment_status']) === 'paid';
    $total = (float) $o['total_amount'];
    $sub   = array_sum(array_map(static fn($i) => (float) $i['price_at_purchase'], $items));
    $disc  = max(0, $sub - $total);
    $deliv = $items && !array_filter($items, static fn($i) => empty($i['upc_delivered_at']) && empty($i['upc_code']));
    $inr   = '&#8377; ';

    $h  = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Invoice ' . $e($o['order_number']) . '</title><style>' . brand_css($pdf) . '</style></head><body><div class="page">';
    if (!$pdf) { $h .= '<div class="toolbar"><button onclick="print()">Print / Save PDF</button></div>'; }
    $h .= '<h1 class="title">' . $e(BRAND_INVOICE_TITLE) . '</h1><div class="brandbox">' . brand_header($pdf);

    $h .= '<table><tr><td class="half"><div class="bar">Bill To</div><div class="pad"><b style="font-size:1.15em">' . $e($o['first_name'] . ' ' . $o['last_name']) . '</b><br>Contact No.: ' . $e($o['phone_number'])
        . '<br>Email: ' . $e($o['email']) . '<br>' . $e($o['city'] . ', ' . $o['state'] . ' - ' . $o['pincode']) . '</div></td>'
        . '<td class="half right"><div class="bar r">Invoice Details</div><div class="pad r">Invoice No.: <b>' . $e($o['order_number']) . '</b><br>Date: ' . $e(brand_ist($o['created_at'], $dbOff, false))
        . '<br>Place of Supply: ' . $e($o['state']) . '<br>Payment: <span class="chip ' . ($paid ? 'ok' : 'warn') . '">' . ($paid ? 'PAID' : $e(strtoupper($o['payment_status']))) . '</span></div></td></tr></table>';

    $h .= '<table class="grid"><tr><th style="width:5%">#</th><th style="width:37%">Item Name</th><th style="width:12%">Network</th><th class="r" style="width:7%">Qty</th><th style="width:7%">Unit</th><th class="r" style="width:16%">Price / Unit</th><th class="r" style="width:16%">Amount</th></tr>';
    foreach ($items as $n => $i) {
        $h .= '<tr class="' . ($n % 2 ? 'alt' : '') . '"><td>' . ($n + 1) . '</td><td><b style="letter-spacing:1px">' . $e($i['mobile_number']) . '</b><br><span class="muted">VIP mobile number (UPC / port code)</span></td>'
            . '<td>' . $e($i['network_preference'] ?: 'Any') . '</td><td class="r">1</td><td>Pcs</td><td class="r">' . $inr . number_format((float) $i['price_at_purchase'], 2)
            . '</td><td class="r">' . $inr . number_format((float) $i['price_at_purchase'], 2) . '</td></tr>';
    }
    $h .= '<tr class="tot"><td></td><td>Total</td><td></td><td class="r">' . count($items) . '</td><td></td><td></td><td class="r">' . $inr . number_format($sub, 2) . '</td></tr></table>';

    $amounts = '<table class="grid"><tr><td>Sub Total</td><td class="r">' . $inr . number_format($sub, 2) . '</td></tr>'
        . ($disc > 0.004 ? '<tr><td>Discount</td><td class="r">- ' . $inr . number_format($disc, 2) . '</td></tr>' : '')
        . '<tr class="tot"><td>Total</td><td class="r">' . $inr . number_format($total, 2) . '</td></tr>'
        . '<tr><td>Received</td><td class="r">' . $inr . number_format($paid ? $total : 0, 2) . '</td></tr>'
        . '<tr><td>Balance</td><td class="r">' . $inr . number_format($paid ? 0 : $total, 2) . '</td></tr></table>';
    $h .= '<table><tr><td class="half"><div class="bar">Invoice Amount In Words</div><div class="pad">' . $e(brand_words($total)) . '</div>'
        . '<div class="bar accent">Description</div><div class="pad">' . ($deliv ? 'UPC Code Delivered Successfully &#10004;' : 'UPC code will be delivered to your registered email / WhatsApp.')
        . '<br><span class="muted">Payment: ' . $e(ucfirst((string) ($o['payment_method'] ?: 'Cashfree'))) . ' | Txn ID: ' . $e($o['transaction_id'] ?: 'N/A') . '</span></div></td>'
        . '<td class="half right"><div class="bar">Amounts</div>' . $amounts . '</td></tr></table>';

    $sign = '';
    if (BRAND_SIGN !== '') {
        $sp = BRAND_SIGN; $root = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $sb = preg_match('#^https?://#', $sp) ? @file_get_contents($sp) : (is_file($root . '/' . ltrim($sp, '/')) ? @file_get_contents($root . '/' . ltrim($sp, '/')) : false);
        if ($sb) { $sign = 'data:' . (new finfo(FILEINFO_MIME_TYPE))->buffer($sb) . ';base64,' . base64_encode($sb); }
    }
    $h .= '<table><tr><td class="half"><div class="pad muted"><b>Terms:</b> Digital product. Non-refundable once the UPC code is delivered, as accepted at checkout.</div></td>'
        . '<td class="half right"><div class="pad r">For: <b>' . $e(BRAND_NAME) . '</b><br>' . ($sign ? '<img src="' . $sign . '" style="max-height:44px;margin:4px 0"><br>' : '<br><br><br>') . '<b>Authorized Signatory</b></div></td></tr></table>';

    $h .= '</div>' . brand_footer('Computer generated invoice. All times in IST.') . '</div>';
    if ($autoPrint && !$pdf) { $h .= '<script>window.addEventListener("load",function(){setTimeout(function(){window.print();},500);});</script>'; }
    return $h . '</body></html>';
}

/** @return array{0:string,1:string} [file name, PDF bytes] */
function brand_invoice_pdf(PDO $pdo, int $id): array
{
    $html = brand_invoice_html($pdo, $id, true);
    if ($html === null) { throw new RuntimeException('Order not found'); }
    $st = $pdo->prepare('SELECT order_number FROM orders WHERE id = ?');
    $st->execute([$id]);
    return ['Invoice-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $st->fetchColumn()) . '.pdf', brand_pdf($html)];
}

/* =========================================================  EVIDENCE PACK  ========================================================= */

function brand_evidence_html(PDO $pdo, int $id, bool $pdf = false, bool $autoPrint = false, int $adminId = 0): ?string
{
    $st = $pdo->prepare('SELECT o.*, c.first_name, c.last_name, c.email, c.phone_number, c.whatsapp_number, c.city, c.state, c.pincode,
            p.transaction_id, p.amount AS paid_amount, p.payment_status AS gw_status, p.payment_response
            FROM orders o JOIN customers c ON c.id = o.customer_id LEFT JOIN payments p ON p.order_id = o.id WHERE o.id = ?');
    $st->execute([$id]);
    $o = $st->fetch(PDO::FETCH_ASSOC);
    if (!$o) { return null; }

    $st = $pdo->prepare('SELECT oi.price_at_purchase, oi.upc_delivered_at, oi.network_preference, v.mobile_number FROM order_items oi JOIN vip_numbers v ON v.id = oi.vip_number_id WHERE oi.order_id = ?');
    $st->execute([$id]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare('SELECT * FROM order_evidence WHERE order_id = ? ORDER BY id');
    $st->execute([$id]);
    $events = $st->fetchAll(PDO::FETCH_ASSOC);
    [$chainOk, $brokenAt] = ev_verify($pdo, $id);

    $dbOff  = brand_db_offset($pdo);
    $phpOff = brand_php_offset();
    $pr     = json_decode((string) $o['payment_response'], true) ?: [];
    $pay    = ev_payment_details($o['transaction_id'], $o['paid_amount'], is_array($pr['gateway'] ?? null) ? $pr['gateway'] : []);
    $e      = 'brand_h';

    $undelivered = (bool) array_filter($items, static fn($i) => empty($i['upc_delivered_at']));
    $types    = array_column($events, 'event_type');
    $revealed = in_array('upc_revealed', $types, true);
    $termsOk  = in_array('terms_accepted', $types, true);
    $paidOk   = strtolower((string) $o['payment_status']) === 'paid';
    $chip = static fn(bool $ok, string $yes, string $no) => '<span class="chip ' . ($ok ? 'ok' : 'warn') . '">' . ($ok ? '&#10004; ' . $yes : '! ' . $no) . '</span>';
    $evClass = static function (string $t): string {
        if (strpos($t, 'failed') !== false) { return 'bad'; }
        if (in_array($t, ['upc_revealed', 'payment_success', 'upc_delivered'], true)) { return 'ok'; }
        if (in_array($t, ['terms_accepted', 'upc_link_opened'], true)) { return 'info'; }
        return 'warn';
    };

    $h  = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Evidence ' . $e($o['order_number']) . '</title><style>@page{size:A4 landscape;margin:10mm}' . brand_css($pdf) . '</style></head><body><div class="page">';
    if (!$pdf) { $h .= '<div class="toolbar"><button onclick="print()">Print / Save PDF</button> <span class="muted">Print settings: Landscape, enable "Background graphics"</span></div>'; }
    $h .= '<h1 class="title">Proof of Product Delivery</h1><div class="brandbox">' . brand_header($pdf);
    $h .= '<div class="bar">Order ' . $e($o['order_number']) . ' &nbsp;|&nbsp; Generated ' . $e(brand_ist(gmdate('Y-m-d H:i:s'), 0)) . ($adminId ? ' by admin #' . $adminId : '') . '</div>';
    $h .= '<div class="summary">' . $chip($termsOk, 'Terms accepted', 'Terms record missing') . $chip($paidOk, 'Payment received', 'Payment not confirmed')
        . $chip(!$undelivered, 'UPC delivered', 'UPC not delivered yet') . $chip($revealed, 'Customer opened UPC', 'UPC not opened by customer yet')
        . '<span class="chip ' . ($chainOk ? 'ok' : 'bad') . '">' . ($chainOk ? '&#10004; Integrity check passed - no record altered' : 'Integrity check FAILED at record #' . (int) $brokenAt) . '</span></div>';

    $kv = static fn(array $r) => '<tr>' . implode('', array_map(static fn($c, $i) => '<td' . ($i % 2 == 0 ? ' class="k"' : '') . (is_array($c) ? ' colspan="' . $c[1] . '"' : '') . '>' . (is_array($c) ? $c[0] : $c) . '</td>', $r, array_keys($r))) . '</tr>';

    $h .= '<div class="bar">1. Customer (as entered at checkout)</div><table class="grid kv">'
        . $kv(['Name', $e($o['first_name'] . ' ' . $o['last_name']), 'Email', $e($o['email'])])
        . $kv(['Phone', $e($o['phone_number']), 'WhatsApp', $e($o['whatsapp_number'])])
        . $kv(['Location', [$e($o['city'] . ', ' . $o['state'] . ' - ' . $o['pincode']), 3]]) . '</table>';

    $mode = trim(strtoupper(str_replace('_', ' ', (string) ($pay['method_group'] ?? ''))) . ' ' . ($pay['method_detail'] ?? '')) ?: 'N/A';
    $h .= '<div class="bar" style="margin-top:8px">2. Payment</div><table class="grid kv">'
        . $kv(['Gateway', $e($o['payment_method'] ?: 'Cashfree Payments'), 'Txn ID / UTR', $e($o['transaction_id'])])
        . $kv(['Payment mode', $e($mode), 'Cashfree payment ID', $e($pay['cf_payment_id'] ?? 'N/A')])
        . $kv(['Amount', 'INR ' . $e($o['paid_amount']) . ' (' . $e($o['gw_status']) . ')', 'Order placed', $e(brand_ist($o['created_at'], $dbOff))])
        . $kv(['Paid at (gateway)', [$e(brand_iso_ist($pay['paid_at'] ?? null)), 3]]) . '</table>';

    $h .= '<div class="bar" style="margin-top:8px">3. Product delivered</div><table class="grid"><tr><th>Digital product</th><th>Price</th><th>UPC delivered at</th></tr>';
    foreach ($items as $i) {
        $h .= '<tr><td>VIP number <b>' . $e($i['mobile_number']) . '</b> (reserved &amp; UPC / port code issued)</td><td>INR ' . $e($i['price_at_purchase']) . '</td><td>'
            . ($i['upc_delivered_at'] ? '<span class="chip ok">' . $e(brand_ist($i['upc_delivered_at'], $dbOff)) . '</span>' : '<span class="chip bad">not delivered</span>') . '</td></tr>';
    }
    $h .= '</table>' . ($undelivered ? '<div class="pad"><span class="chip bad">Note</span> UPC delivery is not recorded yet. Deliver the UPC from the admin panel before using this pack as proof.</div>' : '');

    $h .= '<div class="bar" style="margin-top:8px">4. Activity timeline (tamper-evident log) - all times in IST</div>'
        . '<table class="grid tl"><thead><tr><th style="width:15%">Time</th><th style="width:15%">Event</th><th style="width:8%">Actor</th><th style="width:12%">IP</th><th style="width:22%">Device</th><th>Details</th></tr></thead>';
    foreach ($events as $n => $ev) {
        $h .= '<tr class="' . ($n % 2 ? 'alt' : '') . '"><td>' . $e(brand_ist($ev['created_at'], $phpOff)) . '</td><td><span class="chip ' . $evClass($ev['event_type']) . '">' . $e($ev['event_type']) . '</span></td><td>' . $e($ev['actor'])
            . '</td><td>' . $e($ev['ip_address']) . '</td><td>' . $e(brand_wrappable((string) $ev['user_agent'])) . '</td><td>' . $e(brand_wrappable((string) $ev['details'])) . '</td></tr>';
    }
    $h .= '</table>';

    $h .= '<div class="bar" style="margin-top:8px">5. Policies the customer accepted</div><div class="pad">See event <b>terms_accepted</b> above: digital product, non-refundable once the UPC code is issued. Policy pages: '
        . $e(BRAND_WEBSITE) . '/terms-conditions, /refund-policy, /shipping-policy.</div></div>'
        . brand_footer('Confidential - for payment dispute review. Times in IST (server clocks: DB UTC' . sprintf('%+d', $dbOff / 3600) . 'h, app UTC' . sprintf('%+d', $phpOff / 3600) . 'h).') . '</div>';
    if ($autoPrint && !$pdf) { $h .= '<script>window.addEventListener("load",function(){setTimeout(function(){window.print();},500);});</script>'; }
    return $h . '</body></html>';
}

/** @return array{0:string,1:string} [file name, PDF bytes] */
function brand_evidence_pdf(PDO $pdo, int $id, int $adminId = 0): array
{
    $html = brand_evidence_html($pdo, $id, true, false, $adminId);
    if ($html === null) { throw new RuntimeException('Order not found'); }
    $st = $pdo->prepare('SELECT order_number FROM orders WHERE id = ?');
    $st->execute([$id]);
    return ['Evidence-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $st->fetchColumn()) . '.pdf', brand_pdf($html, true)];
}

/** Send a PDF to the browser as a download. */
function brand_send_pdf(string $name, string $bytes): void
{
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: no-store');
    echo $bytes;
}
