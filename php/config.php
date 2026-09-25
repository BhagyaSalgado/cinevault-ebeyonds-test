<?php
/**
 * Site-wide backend configuration for the CineVault contact form.
 *
 * Real secrets (your Gmail address + App Password) do NOT live in this
 * file, because this file is committed to git / shared as part of the
 * project. Instead they live in `config.local.php`, which is listed in
 * .gitignore and never gets pushed to GitHub.
 *
 * First-time setup:
 *   1. Copy config.local.php.example to config.local.php
 *   2. Fill in your Gmail address and App Password in that new file
 *   3. Done — config.local.php is merged into the settings below automatically
 */

$config = [
    'site_name'    => 'CineVault',
    'from_name'    => 'CineVault (eBEYONDS Evaluation)',

    // Overwritten by config.local.php once you create it (see above).
    'from_email'       => 'photophile12345@gmail.com',
    'admin_emails'     => ['mbhagyasalgado@gmail.com'],
    'smtp_host'        => 'smtp.gmail.com',
    'smtp_port'        => 587,
    'smtp_secure'      => 'tls', // 'tls' (STARTTLS, port 587) or 'ssl' (port 465)
    'smtp_username'    => 'CineVault',
    'smtp_password'    => 'ulpa qqjy jtir yejs',
    // Set to false to fall back to PHP's built-in mail() instead of SMTP
    // (useful if you haven't set up config.local.php yet, but most local
    // Windows/Mac setups have no mail server, so nothing will actually send).
    'use_smtp'         => true,

    'submissions_file' => __DIR__ . '/../data/submissions.json',
    'log_file'         => __DIR__ . '/../data/contact-errors.log',
];

$localConfigFile = __DIR__ . '/config.local.php';
if (is_file($localConfigFile)) {
    $overrides = require $localConfigFile;
    if (is_array($overrides)) {
        $config = array_merge($config, $overrides);
    }
}

return $config;
