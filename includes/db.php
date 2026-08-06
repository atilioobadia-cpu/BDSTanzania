<?php
declare(strict_types=1);

/**
 * BDS Tanzania — shared configuration + PDO database helper.
 *
 * Locates mail-config.php (gitignored) and exposes a singleton PDO
 * connection used by the updates listing, the post detail page and the
 * admin panel. All queries MUST use prepared statements.
 */

function bdst_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $candidates = [
        __DIR__ . '/../../mail-config.php', // production: one level above public_html
        __DIR__ . '/../mail-config.php',    // dev: site root
        __DIR__ . '/mail-config.php',       // fallback: alongside includes/
    ];

    foreach ($candidates as $file) {
        if (is_file($file)) {
            $loaded = require $file;
            if (is_array($loaded)) {
                $config = $loaded;
                return $config;
            }
        }
    }

    throw new RuntimeException('Configuration file (mail-config.php) not found.');
}

function bdst_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = bdst_config();

    if (empty($config['dbHost']) || empty($config['dbName']) || empty($config['dbUser'])) {
        throw new RuntimeException('Database configuration missing from mail-config.php.');
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['dbHost'], $config['dbName']);

    $pdo = new PDO($dsn, $config['dbUser'], $config['dbPass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

function bdst_e(string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
