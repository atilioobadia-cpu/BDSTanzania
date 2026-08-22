<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/db.php';

const BdstAdminIdleTimeout = 60; // seconds of inactivity before forced sign-out

function bdst_admin_username(): string
{
    $config = bdst_config();
    return (string) ($config['adminUsername'] ?? 'admin');
}

function bdst_admin_hash(): string
{
    $config = bdst_config();
    return (string) ($config['adminPasswordHash'] ?? '');
}

function bdst_is_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function bdst_require_login(): void
{
    if (!bdst_is_logged_in()) {
        header('Location: index.php');
        exit;
    }

    $now = time();
    $last = $_SESSION['admin_last_activity'] ?? null;

    if ($last !== null && ($now - (int) $last) > BdstAdminIdleTimeout) {
        session_unset();
        session_destroy();
        session_start();
        session_regenerate_id(true);
        bdst_flash('error', 'You were signed out automatically after one minute of inactivity. Please sign in again.');
        header('Location: index.php');
        exit;
    }

    $_SESSION['admin_last_activity'] = $now;
}

function bdst_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function bdst_csrf_verify(?string $token): bool
{
    return is_string($token) && $token !== '' && hash_equals(bdst_csrf_token(), $token);
}

function bdst_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function bdst_flash_consume(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function bdst_admin_title(string $page): string
{
    return $page . ' · BDS Tanzania Admin';
}
