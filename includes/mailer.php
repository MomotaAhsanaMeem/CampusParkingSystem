<?php
// Thin wrapper around PHPMailer. All email in this app goes through send_email().
// Returns true on success, false on any failure — callers must never catch from here.

require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

/**
 * Send a plain-text / HTML email via the configured SMTP account.
 *
 * @param  string $to      Recipient email address.
 * @param  string $subject Message subject line.
 * @param  string $body    HTML body (text/plain fallback is stripped automatically).
 * @return bool            true on delivery, false on any error (never throws).
 */
function send_email(string $to, string $subject, string $body): bool {
    try {
        $mail = new PHPMailer(true); // true = throw exceptions internally

        // Character encoding
        $mail->CharSet = PHPMailer::CHARSET_UTF8;

        // Server config
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Bypass SSL verification issues on local development (e.g. XAMPP on Windows without system CA cert bundle)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        // Sender / recipient
        $mail->setFrom(SMTP_USER, 'CampusPark');
        $mail->addAddress($to);

        // Content — strip_tags for a plain-text fallback
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;

    } catch (MailerException $e) {
        // Log internally; never expose raw exception to the caller
        error_log('[CampusPark mailer] ' . $e->errorMessage());
        return false;
    } catch (Throwable $e) {
        error_log('[CampusPark mailer] Unexpected: ' . $e->getMessage());
        return false;
    }
}
