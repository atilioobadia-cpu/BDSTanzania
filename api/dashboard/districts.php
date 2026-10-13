<?php
declare(strict_types=1);

/**
 * GET /api/dashboard/districts.php?region=TZ02
 *
 * District breakdown for one region. Cells below the minimum cohort are
 * folded into an "Other / not specified" row instead of being disclosed.
 */

require __DIR__ . '/_bootstrap.php';

$code = strtoupper(trim((string) ($_GET['region'] ?? '')));
if (!preg_match('/^TZ[0-9]{2}$/', $code)) {
    bdst_api_fail('Provide a region code such as TZ02.', 400);
}

bdst_api_guard(static function () use ($code): array {
    $data   = bdst_dashboard_data(bdst_db());
    $detail = bdst_dashboard_region_detail($data, $code);

    if ($detail === null) {
        bdst_api_fail('Unknown region.', 404);
    }

    return [
        'generated_at' => gmdate('c'),
        'region'       => $detail,
    ];
});
