<?php
/**
 * upc_view.php?t=<token>  - private delivery link sent to the customer by email/WhatsApp.
 * GET  = page with a "Reveal my UPC" button (logs 'upc_link_opened')
 * POST = shows the UPC and logs 'upc_revealed' with IP + device.
 * That log line is the strongest proof that the customer received the product.
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/evidence.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$token = (string) ($_GET['t'] ?? '');
$row = null;
if (preg_match('/^[a-f0-9]{32}$/', $token)) {
    $st = $pdo->prepare('SELECT oi.order_id, oi.upc_code, oi.upc_valid_until, v.mobile_number, o.order_number
                         FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN vip_numbers v ON v.id = oi.vip_number_id
                         WHERE oi.upc_view_token = ? AND oi.upc_code IS NOT NULL AND oi.upc_code <> "" LIMIT 1');
    $st->execute([$token]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$reveal = $row && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if ($row) {
    ev_log($pdo, (int) $row['order_id'], $reveal ? 'upc_revealed' : 'upc_link_opened', 'customer', ['mobile' => $row['mobile_number']]);
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Your UPC</title><style>body{font-family:system-ui,sans-serif;background:#f3f4f6;display:flex;justify-content:center;padding:24px}
.c{background:#fff;max-width:420px;width:100%;padding:28px;border-radius:14px;box-shadow:0 4px 18px #0001;text-align:center}
.u{font:700 32px monospace;letter-spacing:4px;background:#eef2ff;padding:14px;border-radius:10px;margin:16px 0}
button{background:#20c15a;color:#fff;border:0;padding:12px 24px;border-radius:10px;font-weight:700;font-size:16px;cursor:pointer}</style></head><body><div class="c">
<?php if (!$row): ?>
  <h2>Link not valid</h2><p>This link is invalid or expired. Please contact us on WhatsApp.</p>
<?php elseif (!$reveal): ?>
  <h2>Order <?= $e($row['order_number']) ?></h2><p>Number: <b><?= $e($row['mobile_number']) ?></b></p>
  <form method="post"><button>Reveal my UPC code</button></form>
  <p style="font-size:12px;color:#6b7280">Opening this page is recorded as delivery of your product.</p>
<?php else: ?>
  <h2>Your UPC code</h2><p>For number <b><?= $e($row['mobile_number']) ?></b></p>
  <div class="u"><?= $e($row['upc_code']) ?></div>
  <?php if (!empty($row['upc_valid_until'])): ?><p>Valid until <?= $e(date('d M Y', strtotime($row['upc_valid_until']))) ?></p><?php endif; ?>
  <p style="font-size:12px;color:#6b7280">Delivered and viewed on <?= $e(date('d M Y, h:i A')) ?> (IST).</p>
<?php endif; ?>
</div></body></html>
