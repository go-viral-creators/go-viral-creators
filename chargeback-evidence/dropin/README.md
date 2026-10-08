# Drop-in patch (FTP/Hostinger File Manager)
1. DB backup lo, phir `01_migration.sql` phpMyAdmin mein run karo.
2. Is folder ki files apni site ke root mein same path par upload/overwrite karo:
   - Naye: `evidence.php`, `upc_view.php`, `admin/evidence_pack.php`
   - Badli hui (purani ka backup rakho): `checkout.php`, `process_checkout.php`, `cashfree_helper.php`, `admin/actions.php`, `admin/order_view.php` (+ naya `admin/invoice_print.php`)
3. `admin/config.php` ko touch nahi kiya gaya.
4. Test: ek test order -> UPC do -> email/WhatsApp link kholo -> `admin/evidence_pack.php?order_id=<ID>`.
Rollback: purani files wapas daal do (naya table/columns harmless hain).

## Branding (invoice + evidence PDF)
`admin/brand.php` mein apna naam, address, phone, GSTIN, logo path (`BRAND_LOGO`), signature image (`BRAND_SIGN`) aur colors set karo.
Logo apni site par upload karke uska path wahan daalo, e.g. '/assets/images/logo.png'.
PDF save karte waqt print dialog mein **"Background graphics" ON** rakho, warna colors nahi aayenge.

## Order confirmation email + invoice
- Payment success par jo confirmation mail pehle se jaati thi, ab usme **invoice (HTML)** + "View / Download Invoice" button aata hai.
- Button `invoice.php?o=<order>&t=<token>` kholta hai (customer ka apna invoice, login nahi chahiye; token HMAC hai, sirf PAID orders).
- Naye files: `invoice.php`, `mail/invoice_email.php`. Badli: `cashfree_helper.php`, `admin/brand.php`, `admin/invoice_print.php`, `admin/evidence_pack.php`, `evidence.php`.
- **Mail queue tabhi bhejti hai jab `mail/email_worker.php` cron se chalta ho.** Hostinger hPanel -> Cron Jobs: har 1 minute `php /home/<user>/public_html/mail/email_worker.php`. Bina cron ke koi bhi mail (naya ya purana) customer tak nahi jaayegi.

## Time (IST)
Server/DB UTC mein chalte hain (IST se 5:30 ghante peeche). Dono PDFs aur email ab sab time IST mein dikhate hain. Config ya DB ka timezone mat badalna, warna purane records ka time galat convert hoga.

## PDF (Dompdf library) - ab customer ko PDF attachment jaata hai
- `dompdf/` folder (Dompdf 3.1.0, LGPL, sab dependencies ke saath) site ke **root** mein upload karo (`/public_html/dompdf/`). Composer ki zaroorat nahi.
- PHP >= 7.1 aur extensions `mbstring`, `dom`, `gd` chahiye (Hostinger par default hote hain; hPanel -> PHP Configuration mein check kar sakte ho).
- Payment success par confirmation mail queue hoti hai (`headers` mein `invoice_order`). Aapka cron wala `mail/email_worker.php` mail bhejte waqt invoice ka **PDF banakar attach** kar deta hai. PDF na ban paaye to mail phir bhi jaati hai (body mein invoice + download link ke saath) aur error `error_log` mein aata hai.
- Naye/badle files: `admin/brand_docs.php` (naya), `admin/brand.php`, `admin/invoice_print.php`, `admin/evidence_pack.php`, `admin/order_view.php`, `mail/invoice_email.php`, `mail/email_worker.php`, `cashfree_helper.php`, `invoice.php`, `evidence.php`.
- Admin order page par ab 2 buttons seedha PDF download karte hain: **Evidence PDF** (Cashfree dispute ke liye) aur **Invoice PDF**.
- Test: koi paid order kholo -> Invoice PDF / Evidence PDF dabao. Phir ek test order karo, cron chalne ke baad mail kholo: subject "Order Confirmation & Invoice", attachment `Invoice-<order>.pdf`.
- Agar PDF download par "PDF library missing" aaye to `dompdf/` folder ka path (`/public_html/dompdf/autoload.inc.php`) check karo.

## IMPORTANT: mail/smtp_mailer.php ko haath se edit karna hai (2 chhote edits)
Is file mein aapka SMTP password hai, isliye maine poori file zip mein nahi daali. `mail/SMTP_MAILER_EDIT.txt` mein exact 2 edits likhe hain (attachment support). Ye edit na karo to mail jaayegi par **PDF attach nahi hoga**.

## EMAIL FIX (cron + turant send)
- Order mail ab payment confirm hote hi **turant** jaati hai (`mail/send_now.php`, response jaane ke baad), PDF invoice ke saath. Cron sirf retry ke liye.
- `mail/email_worker.php` ab **CLI-only** hai (pehle public web page tha jo customers ke email dikhata tha). Cron: har 1 minute `public_html/mail/email_worker.php`. `cron_release_reservations.php` wala cron delete mat karna.
- 48 ghante se purani pending mails 'failed' mark hoti hain (purane customers ko purani mail na jaaye).
- `mail/smtp_mailer.php` repo mein nahi hai (password). `mail/SMTP_MAILER_EDIT.txt` ke 2 edits lagao.
