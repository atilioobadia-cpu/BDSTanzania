<?php
declare(strict_types=1);

/**
 * GET /api/dashboard/trends.php?period=monthly|weekly|daily|yearly&from=&to=
 *
 * Aggregate approved-member counts over time ("growth trend"). A bucket is a
 * number of members, never a member: no region, district or identity is
 * attached. Malformed period/from/to values are rejected with a 400; nothing
 * user-supplied reaches SQL.
 */

require __DIR__ . '/_bootstrap.php';

$period = 'monthly';
if (isset($_GET['period']) && is_string($_GET['period']) && trim($_GET['period']) !== '') {
    $period = strtolower(trim($_GET['period']));
    if (!bdst_dashboard_valid_period($period)) {
        bdst_api_fail('Unknown period. Use daily, weekly, monthly or yearly.', 400);
    }
}

$from = isset($_GET['from']) && is_string($_GET['from']) ? $_GET['from'] : null;
$to   = isset($_GET['to']) && is_string($_GET['to']) ? $_GET['to'] : null;
if (bdst_dashboard_period_token_invalid($period, $from) || bdst_dashboard_period_token_invalid($period, $to)) {
    bdst_api_fail('Invalid from/to value for the chosen period.', 400);
}

bdst_api_guard(static function () use ($period, $from, $to): array {
    return bdst_dashboard_trends(bdst_db(), null, $period, $from, $to);
});
