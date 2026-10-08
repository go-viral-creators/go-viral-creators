<?php
/**
 * mail/email_worker.php - retries every pending mail in email_queue.
 *
 * CRON (hPanel -> Advanced -> Cron Jobs, every minute):
 *   /usr/bin/php /home/<user>/public_html/mail/email_worker.php
 *
 * CLI only: it used to be a public web page that listed customer e-mail addresses; now a browser gets 403.
 * Order-confirmation mails are ALSO sent immediately after payment (see cf_send_confirmation_email),
 * this cron is the safety net for retries and for mails queued by other parts of the site.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/send_now.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Safety guard: a mail that has been waiting for more than 48 hours is stale (e.g. the cron was never set up).
// Sending day-old "order confirmed" mails would only confuse customers, so they are closed as 'failed' instead of sent.
try {
    $stale = $pdo->exec("UPDATE email_queue SET status = 'failed' WHERE status = 'pending' AND created_at < (NOW() - INTERVAL 48 HOUR)");
    if ($stale) { echo date('Y-m-d H:i:s'), ' skipped ', $stale, ' stale mail(s) older than 48h', PHP_EOL; }
} catch (Throwable $e) {
    // no created_at column: nothing to guard with, carry on
}

$rows = $pdo->query("SELECT * FROM email_queue WHERE status = 'pending' ORDER BY id ASC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
$count = ['sent' => 0, 'failed' => 0, 'retry' => 0, 'busy' => 0, 'skipped' => 0];
foreach ($rows as $row) {
    $r = mail_process_row($pdo, $row);
    $count[$r] = ($count[$r] ?? 0) + 1;
    echo date('Y-m-d H:i:s'), ' mail #', $row['id'], ' -> ', $r, PHP_EOL;
}
echo date('Y-m-d H:i:s'), ' done: ', json_encode($count), PHP_EOL;
$left = (int) $pdo->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'")->fetchColumn();
echo date('Y-m-d H:i:s'), ' still pending in queue: ', $left, PHP_EOL;
