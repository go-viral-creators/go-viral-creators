<?php
/**
 * admin/invoice_print.php?order_id=123            -> branded invoice page (Print / Save PDF)
 * admin/invoice_print.php?order_id=123&format=pdf -> downloads Invoice-<order>.pdf
 * Also used by the public invoice.php (token-checked), which defines PUBLIC_INVOICE_ID.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/brand_docs.php';

if (defined('PUBLIC_INVOICE_ID')) { $id = (int) PUBLIC_INVOICE_ID; } else { checkAuth(); $id = (int) ($_GET['order_id'] ?? 0); }

if (($_GET['format'] ?? '') === 'pdf') {
    try {
        [$name, $bytes] = brand_invoice_pdf($pdo, $id);
        brand_send_pdf($name, $bytes);
    } catch (Throwable $e) {
        http_response_code(500);
        error_log('[invoice pdf] ' . $e->getMessage());
        echo 'Could not create the PDF: ' . htmlspecialchars($e->getMessage());
    }
    exit;
}

$html = brand_invoice_html($pdo, $id, false, !empty($_GET['print']));
if ($html === null) { die('Order not found'); }
echo $html;
