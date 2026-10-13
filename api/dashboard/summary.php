<?php
declare(strict_types=1);

/**
 * GET /api/dashboard/summary.php
 *
 * Aggregate totals for the public member map. Suppressed values are null.
 */

require __DIR__ . '/_bootstrap.php';

bdst_api_guard(static function (): array {
    $public = bdst_dashboard_public(bdst_dashboard_data(bdst_db()));

    return [
        'generated_at' => $public['generated_at'],
        'published'    => $public['published'],
        'thresholds'   => $public['thresholds'],
        'totals'       => $public['totals'],
        'max_count'    => $public['max_count'],
        'legend'       => $public['legend'],
    ];
});
