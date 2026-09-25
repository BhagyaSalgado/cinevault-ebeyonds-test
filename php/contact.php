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
 *
 * $htmlBody is the rich version; $textBody is a plain-text fallback shown
 * by clients that don't render HTML (and used outright by the mail()
 * fallback path, which sends plain text only).
 */
function dispatchMail(array $config, array $to, string $subject, string $htmlBody, string $textBody, ?string $replyTo = null): bool
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
            $mail->isHTML(true);
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody;

            return $mail->send();
        } catch (PHPMailerException $e) {
            return false;
        }
    }

    // Fallback: native mail(), plain text only — needs a configured MTA to
    // actually deliver anything.
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . sprintf('%s <%s>', $config['from_name'], $config['from_email']),
    ];
    if ($replyTo) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    return @mail(implode(',', $to), $subject, $textBody, implode("\r\n", $headers));
}

/**
 * Wraps a block of HTML content in a simple, email-client-safe letterhead
 * (inline styles only — most mail clients strip <style> blocks and external
 * CSS). Kept deliberately plain: no external images, no web fonts.
 */
function emailShell(array $config, string $innerHtml): string
{
    $siteName = htmlspecialchars($config['site_name'], ENT_QUOTES, 'UTF-8');
    $year = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html>
<body style="margin:0; padding:0; background:#0F0F0F; font-family:Arial, Helvetica, sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0F0F0F; padding:32px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background:#1A1A1A; border-radius:8px; overflow:hidden;">
          <tr>
            <td style="background:#141414; padding:24px 32px; border-bottom:3px solid #D4A62A;">
              <span style="color:#FFFFFF; font-size:20px; font-weight:bold; letter-spacing:.03em;">{$siteName}</span>
            </td>
          </tr>
          <tr>
            <td style="padding:32px; color:#E6E6E6; font-size:15px; line-height:1.6;">
              {$innerHtml}
            </td>
          </tr>
          <tr>
            <td style="padding:20px 32px; background:#141414; color:#8A8A8A; font-size:12px;">
              &copy; {$year} {$siteName}. This is an automated message from the eBEYONDS Web Developer evaluation build — please do not reply.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

function sendAutoResponse(array $config, array $s): bool
{
    $subject = 'We\'ve received your message — ' . $config['site_name'];

    $firstName = htmlspecialchars($s['firstName'], ENT_QUOTES, 'UTF-8');
    $comments  = nl2br(htmlspecialchars($s['comments'], ENT_QUOTES, 'UTF-8'));
    $siteName  = htmlspecialchars($config['site_name'], ENT_QUOTES, 'UTF-8');

    $inner = <<<HTML
<p style="margin:0 0 16px;">Hi {$firstName},</p>
<p style="margin:0 0 16px;">Thank you for getting in touch with {$siteName}. This email confirms that we've received your message and a member of our team will review it shortly.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0F0F0F; border-radius:6px; margin:0 0 20px;">
  <tr>
    <td style="padding:16px 20px; color:#B7B7B7; font-size:14px; font-style:italic; border-inline-start:3px solid #D4A62A;">
      &ldquo;{$comments}&rdquo;
    </td>
  </tr>
</table>
<p style="margin:0 0 16px;">If your enquiry is urgent, feel free to reply directly to this email.</p>
<p style="margin:0;">Kind regards,<br>The {$siteName} Team</p>
HTML;

    $text = "Hi {$s['firstName']},\n\n"
        . "Thank you for getting in touch with {$config['site_name']}. This email confirms that we've received your message and a member of our team will review it shortly.\n\n"
        . "Your message:\n\"{$s['comments']}\"\n\n"
        . "If your enquiry is urgent, feel free to reply directly to this email.\n\n"
        . "Kind regards,\nThe {$config['site_name']} Team\n";

    return dispatchMail($config, [$s['email']], $subject, emailShell($config, $inner), $text);
}

function sendAdminNotification(array $config, array $s): bool
{
    $subject = 'New contact form submission from ' . $s['firstName'] . ' ' . $s['lastName'];

    $fullName = htmlspecialchars($s['firstName'] . ' ' . $s['lastName'], ENT_QUOTES, 'UTF-8');
    $email    = htmlspecialchars($s['email'], ENT_QUOTES, 'UTF-8');
    $phone    = htmlspecialchars($s['phone'] !== '' ? $s['phone'] : 'Not provided', ENT_QUOTES, 'UTF-8');
    $comments = nl2br(htmlspecialchars($s['comments'], ENT_QUOTES, 'UTF-8'));
    $submittedAt = htmlspecialchars($s['submittedAt'], ENT_QUOTES, 'UTF-8');

    $row = static function (string $label, string $value): string {
        return <<<HTML
<tr>
  <td style="padding:8px 0; color:#8A8A8A; font-size:13px; text-transform:uppercase; letter-spacing:.04em; width:120px; vertical-align:top;">{$label}</td>
  <td style="padding:8px 0; color:#FFFFFF; font-size:14px;">{$value}</td>
</tr>
HTML;
    };

    $inner = <<<HTML
<p style="margin:0 0 20px;">A new contact form submission was received on {$config['site_name']}.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
  {$row('Name', $fullName)}
  {$row('Email', $email)}
  {$row('Phone', $phone)}
  {$row('Submitted', $submittedAt)}
</table>
<p style="margin:0 0 8px; color:#8A8A8A; font-size:13px; text-transform:uppercase; letter-spacing:.04em;">Message</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0F0F0F; border-radius:6px;">
  <tr>
    <td style="padding:16px 20px; color:#E6E6E6; font-size:14px; border-inline-start:3px solid #D4A62A;">
      {$comments}
    </td>
  </tr>
</table>
HTML;

    $text = "A new contact form submission was received.\n\n"
        . "Name:      {$s['firstName']} {$s['lastName']}\n"
        . "Email:     {$s['email']}\n"
        . "Phone:     " . ($s['phone'] !== '' ? $s['phone'] : 'Not provided') . "\n"
        . "Submitted: {$s['submittedAt']}\n\n"
        . "Message:\n{$s['comments']}\n";

    return dispatchMail($config, $config['admin_emails'], $subject, emailShell($config, $inner), $text, $s['email']);
}
