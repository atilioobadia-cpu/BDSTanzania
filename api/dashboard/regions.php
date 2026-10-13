<?php
declare(strict_types=1);

/**
 * GET /api/dashboard/regions.php
 *
 * One entry per official region (31), with the privacy-filtered count/band.
 */

require __DIR__ . '/_bootstrap.php';

bdst_api_guard(static function (): array {
    $public = bdst_dashboard_public(bdst_dashboard_data(bdst_db()));

    return [
        'generated_at' => $public['generated_at'],
        'published'    => $public['published'],
        'thresholds'   => $public['thresholds'],
        'max_count'    => $public['max_count'],
        'legend'       => $public['legend'],
        'regions'      => $public['regions'],
    ];
});
