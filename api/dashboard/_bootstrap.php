<?php
declare(strict_types=1);

/**
 * BDS Tanzania - shared bootstrap for the public dashboard JSON endpoints.
 *
 * Loaded by api/dashboard/*.php only. Requests for this file directly get a
 * 404 so it never produces output on its own.
 */

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit;
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/geo.php';
require_once __DIR__ . '/../../includes/dashboard.php';

// The endpoints are machine-readable mirrors of a page that is itself
// indexable; keep the raw JSON out of search results and caches.
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');
header('Referrer-Policy: no-referrer');

/**
 * Emit a JSON response and stop.
 *
 * @param array<string, mixed> $payload
 */
function bdst_api_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Emit a JSON error and stop. The message is deliberately generic.
 */
function bdst_api_fail(string $message, int $status = 500): void
{
    bdst_api_out(['ok' => false, 'error' => $message], $status);
}

/**
 * Run a callback, converting an unexpected failure into a clean JSON 500.
 *
 * @param callable(): array<string, mixed> $callback
 */
function bdst_api_guard(callable $callback, int $status = 200): void
{
    try {
        $payload = $callback();
    } catch (Throwable $e) {
        bdst_api_fail('Statistics are temporarily unavailable.', 500);
    }

    bdst_api_out(array_merge(['ok' => true], $payload), $status);
}
