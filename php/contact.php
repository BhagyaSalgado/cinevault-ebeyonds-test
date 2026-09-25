<?php
/**
 * Contact form handler for CineVault (eBEYONDS Web Developer evaluation).
 *
 *  - Re-validates every field server-side (never trust client-side JS alone)
 *  - Persists each submission as a JSON record in ../data/submissions.json
 *  - Sends an auto-response email to the visitor
 *  - Sends an admin notification email with the full submission details
 *
 * Responds with JSON so the front-end (js/contact-form.js) can show an
 * inline success/error message without a page reload. The endpoint also
 * degrades gracefully for a plain (non-JS) form POST — see the redirect
 * fallback at the bottom.
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$config = require __DIR__ . '/config.php';

function respond(int $httpCode, bool $success, string $message, array $extra = []): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function logError(string $file, string $message): void
{
    $line = '[' . date('c') . '] ' . $message . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'This endpoint only accepts POST requests.');
}

// ---- Honeypot (silently accept but do nothing with bot submissions) ----
if (!empty($_POST['website'])) {
    respond(200, true, 'Thanks!'); // pretend success, no email/save
}

// ---- Gather + sanitize input --------------------------------------------
$fields = [
    'firstName' => trim((string)($_POST['firstName'] ?? '')),
    'lastName'  => trim((string)($_POST['lastName']  ?? '')),
    'email'     => trim((string)($_POST['email']     ?? '')),
    'phone'     => trim((string)($_POST['phone']     ?? '')),
    'comments'  => trim((string)($_POST['comments']  ?? '')),
];

// ---- Validation -----------------------------------------------------------
$errors = [];

if ($fields['firstName'] === '') {
    $errors['firstName'] = 'First name is required.';
} elseif (mb_strlen($fields['firstName']) > 80) {
    $errors['firstName'] = 'First name is too long.';
}

if ($fields['lastName'] === '') {
    $errors['lastName'] = 'Last name is required.';
} elseif (mb_strlen($fields['lastName']) > 80) {
    $errors['lastName'] = 'Last name is too long.';
}

if ($fields['email'] === '') {
    $errors['email'] = 'Email is required.';
} elseif (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

if ($fields['phone'] !== '' && !preg_match('/^[0-9+()\-.\s]{6,20}$/', $fields['phone'])) {
    $errors['phone'] = 'Please enter a valid phone number.';
}

if ($fields['comments'] === '') {
    $errors['comments'] = 'Comments are required.';
} elseif (mb_strlen($fields['comments']) > 4000) {
    $errors['comments'] = 'Comments are too long (max 4000 characters).';
}

if (!empty($errors)) {
    respond(422, false, 'Please correct the highlighted fields.', ['errors' => $errors]);
}

// Strip anything HTML-like before storing/emailing to avoid stored XSS /
// header-injection if these values are ever echoed back into HTML or emails.
$clean = array_map(static function (string $v): string {
    return strip_tags($v);
}, $fields);

// ---- Persist to JSON --------------------------------------------------
$submission = [
    'id'        => bin2hex(random_bytes(8)),
    'firstName' => $clean['firstName'],
    'lastName'  => $clean['lastName'],
    'email'     => $clean['email'],
    'phone'     => $clean['phone'],
    'comments'  => $clean['comments'],
    'submittedAt' => date('c'),
];

$saveOk = saveSubmission($config['submissions_file'], $submission, $config['log_file']);
if (!$saveOk) {
    logError($config['log_file'], 'Failed to persist submission ' . $submission['id']);
    // Still attempt to send the emails even if the JSON write failed.
}

// ---- Emails -------------------------------------------------------------
$userSent  = sendAutoResponse($config, $submission);
$adminSent = sendAdminNotification($config, $submission);

if (!$userSent) {
    logError($config['log_file'], 'Auto-response email failed for ' . $submission['email']);
}
if (!$adminSent) {
    logError($config['log_file'], 'Admin notification email failed for submission ' . $submission['id']);
}

// The submission is saved regardless of email delivery — mail() depends on
// the host having a configured MTA (sendmail/Postfix) or an SMTP relay, so
// failures here shouldn't be reported to the visitor as a form failure.
respond(200, true, 'Thanks, ' . $submission['firstName'] . '! Your message has been received.');

// =========================================================================
// Helpers
// =========================================================================

function saveSubmission(string $path, array $submission, string $logFile): bool
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        logError($logFile, "Could not create data directory: $dir");
        return false;
    }

    $fp = @fopen($path, 'c+');
    if ($fp === false) {
        logError($logFile, "Could not open $path for writing");
        return false;
    }

    $ok = true;
    if (flock($fp, LOCK_EX)) {
        $size = filesize($path);
        $contents = $size > 0 ? fread($fp, $size) : '';
        $records = json_decode($contents ?: '[]', true);
        if (!is_array($records)) {
            $records = [];
        }
        $records[] = $submission;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
    } else {
        $ok = false;
    }
    fclose($fp);
    return $ok;
}

/**
 * Sends one email. Uses PHPMailer over Gmail SMTP when $config['use_smtp']
 * is true and credentials are filled in; otherwise falls back to PHP's
 * built-in mail() (which needs a configured MTA to actually deliver
 * anything — fine as a no-op fallback during local dev).
 */
function dispatchMail(array $config, array $to, string $subject, string $body, ?string $replyTo = null): bool
{
    $subject = str_replace(["\r", "\n"], '', $subject);

    $useSmtp = !empty($config['use_smtp'])
        && !empty($config['smtp_username'])
        && $config['smtp_username'] !== 'CHANGE_ME@gmail.com'
        && !empty($config['smtp_password'])
        && $config['smtp_password'] !== 'CHANGE_ME_APP_PASSWORD';

    if ($useSmtp) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $config['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['smtp_username'];
            $mail->Password   = $config['smtp_password'];
            $mail->SMTPSecure = $config['smtp_secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)$config['smtp_port'];
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 10; // seconds — fail fast instead of hanging the request

            $mail->setFrom($config['from_email'], $config['from_name']);
            foreach ($to as $recipient) {
                $mail->addAddress($recipient);
            }
            if ($replyTo) {
                $mail->addReplyTo($replyTo);
            }

            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->isHTML(false);

            return $mail->send();
        } catch (PHPMailerException $e) {
            return false;
        }
    }

    // Fallback: native mail() — only works if the host has a configured MTA.
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . sprintf('%s <%s>', $config['from_name'], $config['from_email']),
    ];
    if ($replyTo) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    return @mail(implode(',', $to), $subject, $body, implode("\r\n", $headers));
}

function sendAutoResponse(array $config, array $s): bool
{
    $subject = 'We received your message — ' . $config['site_name'];
    $body = "Hi {$s['firstName']},\n\n"
        . "Thanks for reaching out to {$config['site_name']}! This confirms we received your message:\n\n"
        . "\"{$s['comments']}\"\n\n"
        . "We (or the eBEYONDS team) will get back to you shortly.\n\n"
        . "— {$config['site_name']}\n";

    return dispatchMail($config, [$s['email']], $subject, $body);
}

function sendAdminNotification(array $config, array $s): bool
{
    $subject = 'New contact form submission — ' . $config['site_name'];
    $body = "A new contact form submission was received.\n\n"
        . "Name:      {$s['firstName']} {$s['lastName']}\n"
        . "Email:     {$s['email']}\n"
        . "Phone:     " . ($s['phone'] !== '' ? $s['phone'] : '—') . "\n"
        . "Submitted: {$s['submittedAt']}\n\n"
        . "Comments:\n{$s['comments']}\n";

    return dispatchMail($config, $config['admin_emails'], $subject, $body, $s['email']);
}
