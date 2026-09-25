<?php
/**
 * Site-wide backend configuration for the CineVault contact form.
 *
 * Real secrets (your Gmail address + App Password) do NOT live in this
 * file, because this file is committed to git / shared as part of the
 * project. There are two ways to supply them instead, checked in this order
 * (later ones win):
 *
 *   1. Local dev: copy config.local.php.example to config.local.php and
 *      fill it in. That file is git-ignored, so it never reaches GitHub.
 *   2. Deployment (e.g. Render): set environment variables instead — see
 *      $envMap below for the exact names. This is the standard way most
 *      hosts expect secrets to be supplied, and avoids needing any file on
 *      the server at all.
 *
 * Environment variables always win if both are present, so on a host where
 * you've set them, any leftover config.local.php values are ignored.
 */

$config = [
    'site_name'    => 'CineVault',
    'from_name'    => 'CineVault (eBEYONDS Evaluation)',

    // Overwritten by config.local.php once you create it (see above).
    'from_email'       => 'CHANGE_ME@gmail.com',
    'admin_emails'     => ['CHANGE_ME@gmail.com'],
    'smtp_host'        => 'smtp.gmail.com',
    'smtp_port'        => 587,
    'smtp_secure'      => 'tls', // 'tls' (STARTTLS, port 587) or 'ssl' (port 465)
    'smtp_username'    => 'CHANGE_ME@gmail.com',
    'smtp_password'    => 'CHANGE_ME_APP_PASSWORD',
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

// Deployment: environment variables (e.g. set in Render's dashboard) take
// final priority over everything above, so a host that supplies these never
// needs config.local.php at all.
$envMap = [
    'from_email'    => 'SMTP_FROM_EMAIL',
    'admin_emails'  => 'ADMIN_EMAILS',   // comma-separated if more than one
    'smtp_username' => 'SMTP_USERNAME',
    'smtp_password' => 'SMTP_PASSWORD',
    'smtp_host'     => 'SMTP_HOST',
    'smtp_port'     => 'SMTP_PORT',
];
foreach ($envMap as $key => $envName) {
    $value = getenv($envName);
    if ($value === false || $value === '') {
        continue;
    }
    $config[$key] = $key === 'admin_emails'
        ? array_map('trim', explode(',', $value))
        : $value;
}

return $config;
