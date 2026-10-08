<?php
/**
 * mail/send_now.php - ONE code path that sends a row of email_queue.
 *   - mail_send_queued_now($pdo, $id)  : called right after payment (no cron needed for order mails)
 *   - mail/email_worker.php (cron)     : retries anything still pending
 * A MySQL named lock per row makes sure the same mail is never sent twice by both at once.
 */
require_once __DIR__ . '/smtp_mailer.php';

/** @return string sent | failed | retry | busy | skipped */
function mail_process_row(PDO $pdo, array $row): string
{
    $id   = (int) $row['id'];
    $lock = 'emailq_' . $id;
    if (!(int) $pdo->query('SELECT GET_LOCK(' . $pdo->quote($lock) . ', 0)')->fetchColumn()) {
        return 'busy';                                   // another process is sending this mail right now
    }
    try {
        $st = $pdo->prepare('SELECT * FROM email_queue WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || ($row['status'] ?? '') !== 'pending') {
            return 'skipped';                            // already sent / failed / removed
        }

        $headers = json_decode((string) ($row['headers'] ?? '{}'), true);
        if (!is_array($headers)) { $headers = []; }
        $cc  = is_array($headers['cc'] ?? null) ? $headers['cc'] : [];
        $bcc = is_array($headers['bcc'] ?? null) ? $headers['bcc'] : [];
        if (!empty($headers['hidden'])) { $bcc[] = $headers['hidden']; }
        $cc  = array_unique(array_filter($cc));
        $bcc = array_unique(array_filter($bcc));

        // Invoice PDF attachment: built fresh on every attempt. A PDF problem never blocks the mail itself.
        $attachments = [];
        if (!empty($headers['invoice_order'])) {
            try {
                require_once __DIR__ . '/../admin/brand_docs.php';
                $q = $pdo->prepare('SELECT id FROM orders WHERE order_number = ? LIMIT 1');
                $q->execute([$headers['invoice_order']]);
                $oid = (int) $q->fetchColumn();
                if ($oid) {
                    [$fname, $pdfBytes] = brand_invoice_pdf($pdo, $oid);
                    $attachments[] = ['name' => $fname, 'data' => $pdfBytes, 'type' => 'application/pdf'];
                }
            } catch (Throwable $e) {
                error_log('[mail] invoice PDF failed for ' . $headers['invoice_order'] . ': ' . $e->getMessage());
            }
        }
        if ($attachments && (new ReflectionFunction('smtp_send_mail'))->getNumberOfParameters() < 8) {
            error_log('[mail] smtp_mailer.php has no attachment support yet - sending without the PDF (apply mail/SMTP_MAILER_EDIT.txt)');
            $attachments = [];
        }

        $sent = smtp_send_mail($row['to_email'], $row['subject'], $row['body'], 'support@vipnumbergallery.com', 'VIP Number Gallery', $cc, $bcc, $attachments);

        if ($sent) {
            $pdo->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?")->execute([$id]);
            return 'sent';
        }
        $attempts = (int) ($row['attempts'] ?? 0) + 1;
        if ($attempts >= 3) {
            $pdo->prepare("UPDATE email_queue SET status = 'failed', attempts = ? WHERE id = ?")->execute([$attempts, $id]);
            return 'failed';
        }
        $pdo->prepare('UPDATE email_queue SET attempts = ? WHERE id = ?')->execute([$attempts, $id]);
        return 'retry';
    } catch (Throwable $e) {
        error_log('[mail] row ' . $id . ' error: ' . $e->getMessage());
        return 'retry';
    } finally {
        $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote($lock) . ')');
    }
}

/** Send one queued mail immediately (used right after payment). */
function mail_send_queued_now(PDO $pdo, int $id): string
{
    $st = $pdo->prepare('SELECT * FROM email_queue WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? mail_process_row($pdo, $row) : 'skipped';
}
