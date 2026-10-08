<?php
/**
 * admin/evidence_pack.php?order_id=123            -> branded proof-of-delivery page
 * admin/evidence_pack.php?order_id=123&format=pdf -> downloads Evidence-<order>.pdf (upload this to Cashfree)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/brand_docs.php';
checkAuth();

$id = (int) ($_GET['order_id'] ?? 0);
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

if (($_GET['format'] ?? '') === 'pdf') {
    try {
        [$name, $bytes] = brand_evidence_pdf($pdo, $id, $adminId);
        brand_send_pdf($name, $bytes);
    } catch (Throwable $e) {
        http_response_code(500);
        error_log('[evidence pdf] ' . $e->getMessage());
        echo 'Could not create the PDF: ' . htmlspecialchars($e->getMessage());
    }
    exit;
}

$html = brand_evidence_html($pdo, $id, false, !empty($_GET['print']), $adminId);
if ($html === null) { die('Order not found'); }
echo $html;
