# Aapki site mein 3 chhote changes (baaki sab naye files hain)

## 0. Setup
1. `01_migration.sql` phpMyAdmin mein run karo (pehle backup).
2. `evidence.php` + `upc_view.php` ko site root mein, `admin/evidence_pack.php` ko `admin/` mein copy karo.

## 1. `admin/actions.php` -> `update_upc` (line ~164)
`$pdo->commit();` ke baad (header redirect se pehle) ye add karo:
```php
require_once __DIR__ . '/../evidence.php';
require_once __DIR__ . '/../mail/email_queue.php';
ev_deliver_upc($pdo, (int) $order_id, (int) $order_item_id);
```
Ab UPC dete hi: delivery time save, customer ko email + WhatsApp link, aur har step evidence log mein.

## 2. `checkout.php` -> pay button se pehle (form ke andar)
```html
<label class="flex gap-2 text-sm">
  <input type="checkbox" name="accept_terms" value="1" required>
  <span>I understand this is a digital product (VIP number + UPC). It is non-refundable once the UPC is delivered.
  I accept the <a href="terms-conditions" target="_blank">Terms</a>, <a href="refund-policy" target="_blank">Refund Policy</a>.</span>
</label>
```

## 3. `process_checkout.php` -> order INSERT ke turant baad (line ~508-512)
`$orderId = (int) $pdo->lastInsertId();` ke baad:
```php
require_once __DIR__ . '/evidence.php';
if (empty($_POST['accept_terms'])) { /* pc_fail(...) se rok do */ }
ev_log($pdo, $orderId, 'terms_accepted', 'customer', ['version' => '2026-10', 'email' => $_POST['email'] ?? '', 'phone' => $_POST['phone_number'] ?? '']);
ev_log($pdo, $orderId, 'checkout_submitted', 'customer', ['amount' => $total ?? null]);
```
## 4. `cashfree_helper.php` -> `cf_finalize_tx`, PAID branch me `$pdo->commit();` se pehle
```php
require_once __DIR__ . '/evidence.php';
ev_log($pdo, $orderId, 'payment_success', 'gateway', ['txn' => $txnId, 'amount' => $amount]);
```
Dispute aaye to: Admin -> `admin/evidence_pack.php?order_id=<ID>` -> Print/Save as PDF -> Cashfree dispute portal mein upload.
