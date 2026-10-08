<?php
/**
 * invoice.php?o=<order_number>&t=<token>  - customer's own invoice (no login).
 * The link is sent in the order-confirmation email. The token is an HMAC of the order number
 * (see brand_invoice_token), so an order's invoice can't be guessed or enumerated.
 */
require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/admin/brand.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$orderNumber = (string) ($_GET['o'] ?? '');
$token       = (string) ($_GET['t'] ?? '');
$id          = 0;

if (preg_match('/^[A-Za-z0-9_-]{6,50}$/', $orderNumber) && hash_equals(brand_invoice_token($orderNumber), $token)) {
    $st = $pdo->prepare("SELECT id FROM orders WHERE order_number = ? AND payment_status = 'paid' LIMIT 1");
    $st->execute([$orderNumber]);
    $id = (int) $st->fetchColumn();
}

if (!$id) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Invoice not found</title>'
       . '<body style="font-family:Arial,sans-serif;text-align:center;padding:60px 16px"><h2>Invoice not found</h2>'
       . '<p>This link is invalid, or the payment is not confirmed yet. Please contact us on WhatsApp / email with your order number.</p></body>';
    exit;
}

define('PUBLIC_INVOICE_ID', $id);
require __DIR__ . '/admin/invoice_print.php';
