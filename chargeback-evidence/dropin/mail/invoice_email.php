<?php
/**
 * mail/invoice_email.php - invoice block for the order-confirmation email.
 * Email clients ignore <style>/external CSS, so everything is inline and table based.
 */
require_once __DIR__ . '/../admin/brand.php';

function invoice_email_block($pdo, string $orderNumber): string
{
    $st = $pdo->prepare('SELECT o.id, o.order_number, o.total_amount, o.payment_status, o.payment_method, o.created_at,
            c.first_name, c.last_name, c.email, c.phone_number, c.city, c.state, c.pincode, p.transaction_id
            FROM orders o JOIN customers c ON c.id = o.customer_id LEFT JOIN payments p ON p.order_id = o.id
            WHERE o.order_number = ? LIMIT 1');
    $st->execute([$orderNumber]);
    $o = $st->fetch(PDO::FETCH_ASSOC);
    if (!$o) { return ''; }

    $st = $pdo->prepare('SELECT oi.price_at_purchase, oi.network_preference, v.mobile_number
            FROM order_items oi JOIN vip_numbers v ON v.id = oi.vip_number_id WHERE oi.order_id = ? ORDER BY oi.vip_number_id');
    $st->execute([$o['id']]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);

    $e     = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $c     = BRAND_COLOR; $a = BRAND_ACCENT; $soft = BRAND_SOFT;
    $total = (float) $o['total_amount'];
    $sub   = array_sum(array_map(static fn($i) => (float) $i['price_at_purchase'], $items));
    $disc  = max(0, $sub - $total);
    $paid  = strtolower((string) $o['payment_status']) === 'paid';
    $date  = brand_ist($o['created_at'], brand_db_offset($pdo), false);
    $logo  = brand_logo_url();
    $money = static fn($n) => '&#8377;&nbsp;' . number_format((float) $n, 2);
    $td    = 'padding:8px 10px;border-bottom:1px solid #e2e8f0;font-size:13px;color:#1f2937;';

    $rows = '';
    foreach ($items as $k => $i) {
        $rows .= '<tr><td style="' . $td . '">' . ($k + 1) . '</td><td style="' . $td . '"><strong style="letter-spacing:1px">' . $e($i['mobile_number'])
              . '</strong><br><span style="font-size:11px;color:#64748b">VIP mobile number (UPC / port code)</span></td>'
              . '<td style="' . $td . '">' . $e($i['network_preference'] ?: 'Any') . '</td><td align="right" style="' . $td . '">' . $money($i['price_at_purchase']) . '</td></tr>';
    }
    $sumRow = static fn($label, $val, $bold = false) => '<tr><td colspan="3" align="right" style="padding:6px 10px;font-size:13px;color:#475569;' . ($bold ? 'font-weight:bold;color:#0f172a;' : '') . '">' . $label
            . '</td><td align="right" style="padding:6px 10px;font-size:13px;' . ($bold ? 'font-weight:bold;color:#0f172a;' : 'color:#1f2937;') . '">' . $val . '</td></tr>';

    $brand = $logo !== ''
        ? '<img src="' . $e($logo) . '" alt="' . $e(BRAND_TAGLINE) . '" height="48" style="display:block;border:0;max-height:48px">'
        : '<strong style="font-size:18px;color:' . $c . '">' . $e(BRAND_TAGLINE) . '</strong>';

    $html  = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;border:2px solid ' . $c . ';border-radius:8px;overflow:hidden;">';
    $html .= '<tr><td style="padding:14px 16px;border-bottom:4px solid ' . $a . ';"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
          . '<td valign="middle">' . $brand . '</td><td align="right" valign="middle" style="font-size:12px;line-height:1.5;color:#334155;">'
          . '<strong style="font-size:16px;color:' . BRAND_COLOR_DARK . '">' . $e(BRAND_NAME) . '</strong><br>' . $e(BRAND_ADDRESS) . '<br>Phone: ' . $e(BRAND_PHONE) . ' | ' . $e(BRAND_EMAIL)
          . (BRAND_GSTIN !== '' ? '<br>GSTIN: ' . $e(BRAND_GSTIN) : '') . '</td></tr></table></td></tr>';
    $html .= '<tr><td style="background:' . $c . ';color:#ffffff;padding:8px 16px;font-size:13px;font-weight:bold;letter-spacing:.5px;text-transform:uppercase;">' . $e(BRAND_INVOICE_TITLE) . ' &nbsp;|&nbsp; ' . $e($o['order_number']) . '</td></tr>';
    $html .= '<tr><td style="padding:12px 16px;font-size:13px;line-height:1.6;color:#1f2937;background:' . $soft . ';">'
          . '<strong>Billed to:</strong> ' . $e($o['first_name'] . ' ' . $o['last_name']) . ' &middot; ' . $e($o['phone_number']) . '<br>' . $e($o['city'] . ', ' . $o['state'] . ' - ' . $o['pincode'])
          . '<br><strong>Invoice date:</strong> ' . $e($date) . ' &nbsp; <strong>Status:</strong> <span style="color:' . ($paid ? '#166534' : '#92400e') . ';font-weight:bold">' . ($paid ? 'PAID' : $e(strtoupper($o['payment_status']))) . '</span></td></tr>';
    $html .= '<tr><td><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
          . '<th align="left" style="background:' . $c . ';color:#fff;padding:8px 10px;font-size:12px;">#</th><th align="left" style="background:' . $c . ';color:#fff;padding:8px 10px;font-size:12px;">Item</th>'
          . '<th align="left" style="background:' . $c . ';color:#fff;padding:8px 10px;font-size:12px;">Network</th><th align="right" style="background:' . $c . ';color:#fff;padding:8px 10px;font-size:12px;">Amount</th></tr>'
          . $rows . $sumRow('Sub total', $money($sub)) . ($disc > 0.004 ? $sumRow('Discount', '- ' . $money($disc)) : '') . $sumRow('Total paid', $money($paid ? $total : 0), true) . '</table></td></tr>';
    $html .= '<tr><td style="padding:10px 16px;font-size:12px;color:#475569;border-top:1px solid #e2e8f0;">' . $e(brand_words($total)) . '<br>Payment: ' . $e(ucfirst((string) ($o['payment_method'] ?: 'Cashfree'))) . ' | Txn ID: ' . $e($o['transaction_id'] ?: 'N/A')
          . '<br><span style="color:#64748b">Digital product. Non-refundable once the UPC code is delivered, as accepted at checkout.</span></td></tr></table>';

    $url   = brand_invoice_url($orderNumber) . '&format=pdf';
    $html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:16px;"><tr><td style="border-radius:8px;background:' . $a . ';">'
          . '<a href="' . $e($url) . '" target="_blank" style="display:inline-block;padding:12px 22px;font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;">Download Invoice (PDF)</a></td></tr></table>'
          . '<p style="margin:8px 0 0 0;font-size:12px;color:#64748b;">Your invoice is also attached to this email as a PDF.</p>';
    return $html;
}

/** Put the invoice block just above the "Visit Website" button of the existing template (or before </body>). */
function invoice_email_inject(string $body, string $block): string
{
    if ($block === '') { return $body; }
    $anchor = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;">';
    $pos = strpos($body, $anchor);
    if ($pos !== false) { return substr($body, 0, $pos) . $block . substr($body, $pos); }
    $pos = strripos($body, '</body>');
    return $pos !== false ? substr($body, 0, $pos) . $block . substr($body, $pos) : $body . $block;
}
