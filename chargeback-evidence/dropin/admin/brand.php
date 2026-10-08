<?php
/**
 * admin/brand.php - ONE place for your business details + colours used by the printable
 * Invoice and Evidence Pack. Edit the values below; nothing else needs changing.
 */
const BRAND_NAME        = 'Vip Shop';
const BRAND_TAGLINE     = 'VIP Number Gallery';
const BRAND_ADDRESS     = 'SHOP NO : 6, JAI HIND CHOWK, Ram Mandir Rd, Pangari, Yavatmal, Maharashtra 445001';
const BRAND_PHONE       = '8882143786';
const BRAND_EMAIL       = 'support@vipnumbergallery.com';
const BRAND_STATE       = '27-Maharashtra';
const BRAND_WEBSITE     = 'vipnumbergallery.com';
const BRAND_GSTIN       = '';                 // e.g. '27ABCDE1234F1Z5' - printed only if filled
const BRAND_INVOICE_TITLE = 'Tax Invoice';    // change to 'Invoice' if you are not GST registered
// Logo: upload your logo to your site and put its path/URL here (PNG/JPG/WebP). Falls back to text if missing.
const BRAND_LOGO        = '/assets/images/logo.png';
// Optional signature image (your own scanned signature). Leave '' for a blank signing line.
const BRAND_SIGN        = '';

const BRAND_COLOR       = '#1e4475';          // navy  - headers
const BRAND_COLOR_DARK  = '#15325c';
const BRAND_ACCENT      = '#ff8318';          // orange - highlights
const BRAND_SOFT        = '#eef3fa';          // light tint for rows

function brand_url(string $p): string
{
    if ($p === '') { return ''; }
    return preg_match('#^https?://#', $p) ? $p : rtrim(SITE_URL, '/') . '/' . ltrim($p, '/');
}

/** Logo URL, or '' when a relative logo path points to a file that does not exist (avoids broken-image icons). */
function brand_logo_url(): string
{
    $p = BRAND_LOGO;
    if ($p === '') { return ''; }
    if (!preg_match('#^https?://#', $p)) {
        $root = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        if ($root !== '' && !is_file($root . '/' . ltrim($p, '/'))) { return ''; }
    }
    return brand_url($p);
}

/** Logo as a data: URI (needed inside PDFs). '' if missing/unreadable. WebP is converted to PNG when GD can. */
function brand_logo_data_uri(): string
{
    $p = BRAND_LOGO;
    if ($p === '') { return ''; }
    $bytes = false;
    if (preg_match('#^https?://#', $p)) {
        $bytes = @file_get_contents($p, false, stream_context_create(['http' => ['timeout' => 4], 'ssl' => ['verify_peer' => true]]));
    } else {
        $root = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $f = $root . '/' . ltrim($p, '/');
        if ($root !== '' && is_file($f)) { $bytes = @file_get_contents($f); }
    }
    if ($bytes === false || $bytes === '' || strlen($bytes) > 3000000) { return ''; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    if ($mime === 'image/webp') {
        if (!function_exists('imagecreatefromstring')) { return ''; }
        $im = @imagecreatefromstring($bytes);
        if (!$im) { return ''; }
        ob_start(); imagepng($im); $bytes = ob_get_clean(); $mime = 'image/png';
    }
    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) { return ''; }
    return 'data:' . $mime . ';base64,' . base64_encode($bytes);
}

/** Table-based CSS (works in browsers AND Dompdf: no flexbox, no rgba). */
function brand_css(bool $pdf = false): string
{
    $c = BRAND_COLOR; $d = BRAND_COLOR_DARK; $a = BRAND_ACCENT; $s = BRAND_SOFT;
    $font = $pdf ? "'DejaVu Sans', sans-serif" : 'Arial, Helvetica, sans-serif';
    $fs   = $pdf ? '8.6px' : '13px';
    $small = $pdf ? '7.6px' : '11px';
    $page = $pdf ? '' : 'max-width:1000px;margin:0 auto;padding:18px;';
    return "html{-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{font-family:$font;font-size:$fs;line-height:1.45;margin:0;color:#1f2937;background:#fff}
.page{{$page}}
.toolbar{margin-bottom:10px}.toolbar button{background:$a;color:#fff;border:0;padding:9px 18px;border-radius:6px;font-weight:bold;cursor:pointer}
h1.title{text-align:center;font-size:" . ($pdf ? '15px' : '20px') . ";margin:0 0 6px;color:$d}
.brandbox{border:2px solid $c}
table{border-collapse:collapse;width:100%}
table.head td{padding:9px 12px;border-bottom:3px solid $a;vertical-align:middle}
.wordmark{font-weight:bold;font-size:" . ($pdf ? '15px' : '20px') . ";color:$c;line-height:1.15}.wordmark span{color:$a}
td.biz{text-align:right;font-size:$small}.biz b{font-size:" . ($pdf ? '12px' : '17px') . ";color:$d}
.bar{background:$c;color:#fff;font-weight:bold;padding:5px 9px;font-size:$small;text-transform:uppercase;letter-spacing:.4px}
.bar.accent{background:$a}.bar.r{text-align:right}
td.half{width:50%;vertical-align:top;padding:0}td.half.right{border-left:1px solid #cbd5e1}
.pad{padding:6px 9px}.r{text-align:right}.muted{color:#64748b;font-size:$small}
table.grid th{background:$c;color:#fff;text-align:left;padding:5px 7px;font-size:$small}
table.grid th.r{text-align:right}
table.grid td{border:1px solid #d5dde8;padding:5px 7px;vertical-align:top}
table.grid tr.alt td{background:$s}table.grid tr.tot td{background:$s;font-weight:bold}
table.kv td.k{width:15%;font-weight:bold;color:$d;background:$s}
table.tl{table-layout:fixed;font-size:$small}
.summary{padding:8px 10px;background:$s;border-bottom:1px solid #cbd5e1}
.chip{display:inline-block;padding:1px 8px;border-radius:9px;font-weight:bold;font-size:$small;white-space:nowrap;margin:1px 3px 1px 0}
.ok{background:#dcfce7;color:#166534}.bad{background:#fee2e2;color:#991b1b}.warn{background:#fef3c7;color:#92400e}.info{background:#dbeafe;color:#1e40af}
table.foot{margin-top:10px;border-top:3px solid $a}table.foot td{padding-top:5px;font-size:$small;color:#475569}
.bar,tr{page-break-inside:avoid}
@page{margin:10mm}@media print{.toolbar{display:none}.page{padding:0}}";
}

/** Shared header: logo (left) + business details (right). */
function brand_header(bool $pdf = false): string
{
    $e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $src = $pdf ? brand_logo_data_uri() : brand_logo_url();
    $h = '<table class="head"><tr><td>';
    if ($src !== '') {
        $h .= '<img src="' . $e($src) . '" alt="' . $e(BRAND_TAGLINE) . '" style="max-height:' . ($pdf ? '44px' : '60px') . ';max-width:190px"'
            . ($pdf ? '' : ' onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'block\'"') . '>';
    }
    $h .= '<div class="wordmark" style="' . ($src !== '' ? 'display:none' : '') . '">VIP <span>NUMBER</span><br>GALLERY</div></td>';
    $h .= '<td class="biz"><b>' . $e(BRAND_NAME) . '</b><br>' . $e(BRAND_ADDRESS) . '<br>Phone: ' . $e(BRAND_PHONE) . ' | Email: ' . $e(BRAND_EMAIL)
        . '<br>State: ' . $e(BRAND_STATE) . (BRAND_GSTIN !== '' ? ' | GSTIN: ' . $e(BRAND_GSTIN) : '') . '</td></tr></table>';
    return $h;
}

function brand_footer(string $note = ''): string
{
    $e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    return '<table class="foot"><tr><td><b>' . $e(BRAND_TAGLINE) . '</b> | ' . $e(BRAND_WEBSITE) . ' | ' . $e(BRAND_PHONE) . '</td><td class="r">' . $e($note) . '</td></tr></table>';
}

/** Indian-style amount in words, e.g. 4000 => "Four Thousand Rupees only". */
function brand_words(float $amount): string
{
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $two = static function (int $n) use ($ones, $tens): string {
        return $n < 20 ? $ones[$n] : trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
    };
    $conv = static function (int $n) use ($two, $ones): string {
        $out = '';
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$div, $name]) {
            if ($n >= $div) { $out .= $two(intdiv($n, $div)) . " $name "; $n %= $div; }
        }
        if ($n >= 100) { $out .= $ones[intdiv($n, 100)] . ' Hundred '; $n %= 100; }
        return trim($out . ' ' . ($n ? $two($n) : ''));
    };
    $rupees = (int) floor($amount);
    $paise  = (int) round(($amount - $rupees) * 100);
    $w = ($rupees === 0 ? 'Zero' : $conv($rupees)) . ' Rupees';
    if ($paise) { $w .= ' and ' . $two($paise) . ' Paise'; }
    return $w . ' only';
}


/* ------------------------------------------------------------------
 * Time handling. Your server/DB clock is NOT India time (it runs in UTC), so every
 * timestamp is converted to IST before it is printed. Stored values are never changed.
 * ------------------------------------------------------------------ */
const BRAND_IST_OFFSET = 19800;   // UTC+05:30

/** Seconds the MySQL clock (NOW()) is ahead of UTC. 0 = DB runs in UTC. */
function brand_db_offset($pdo): int
{
    static $o = null;
    if ($o !== null) { return $o; }
    try {
        $raw = (int) $pdo->query('SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW())')->fetchColumn();
        $o = (int) (round($raw / 900) * 900);          // clocks are offset in 15-min steps
    } catch (Throwable $e) {
        $o = 0;
    }
    return $o;
}

/** Offset of PHP's own clock (used by date() when evidence rows are written). */
function brand_php_offset(): int { return (int) date('Z'); }

/** 'YYYY-MM-DD HH:MM:SS' written by a clock that is $srcOffset seconds ahead of UTC -> '07 Oct 2026, 04:35:06 PM IST'. */
function brand_ist(?string $ts, int $srcOffset, bool $seconds = true): string
{
    if (!$ts || strtotime($ts . ' UTC') === false) { return 'N/A'; }
    $utc = strtotime($ts . ' UTC') - $srcOffset;
    return gmdate($seconds ? 'd M Y, h:i:s A' : 'd M Y, h:i A', $utc + BRAND_IST_OFFSET) . ' IST';
}

/** ISO-8601 string with its own offset (Cashfree: 2026-10-07T11:05:58+05:30) -> IST text. */
function brand_iso_ist(?string $iso): string
{
    if (!$iso) { return 'N/A'; }
    try { $t = (new DateTime($iso))->getTimestamp(); } catch (Throwable $e) { return (string) $iso; }
    return gmdate('d M Y, h:i:s A', $t + BRAND_IST_OFFSET) . ' IST';
}

/* ------------------------------------------------------------------
 * Customer-facing invoice link: HMAC of the order number, keyed with a secret that never
 * leaves the server. Only paid orders can be opened.
 * ------------------------------------------------------------------ */
function brand_invoice_token(string $orderNumber): string
{
    $secret = defined('CASHFREE_SECRET_KEY') ? CASHFREE_SECRET_KEY : 'change-me';
    return substr(hash_hmac('sha256', 'invoice|' . $orderNumber, $secret), 0, 32);
}

function brand_invoice_url(string $orderNumber): string
{
    return rtrim(SITE_URL, '/') . '/invoice.php?o=' . rawurlencode($orderNumber) . '&t=' . brand_invoice_token($orderNumber);
}
