<?php
/**
 * BDS Tanzania — environment configuration.
 *
 * Copy this file to `mail-config.php` (ideally OUTSIDE public_html, one level
 * above the site root, because sendmail.php already looks for ../../mail-config.php)
 * and fill in the real values. `mail-config.php` is gitignored and must never
 * be committed.
 */
return [
    // --- Contact form (SMTP / PHPMailer) ---
    'toEmail'          => 'info@bdst.or.tz',
    'fromEmail'        => 'no-reply@bdst.or.tz',
    'siteName'         => 'BDS Tanzania',
    'smtpHost'         => 'mail.bdst.or.tz',
    'smtpUsername'     => 'no-reply@bdst.or.tz',
    'smtpPassword'     => '',
    'smtpPort'         => 465,
    'smtpEncryption'   => 'ssl', // 'ssl' (SMTPS) or 'tls' (STARTTLS)

    // --- Database (news updates backend) ---
    'dbHost'           => 'localhost',
    'dbName'           => 'bdst_updates',
    'dbUser'           => 'bdst_updates_user',
    'dbPass'           => '',

    // --- Admin panel credentials ---
    'adminUsername'    => 'admin',
    // Generate with: php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT);"
    'adminPasswordHash'=> '',
];
