<?php
declare(strict_types=1);

/**
 * BDS Tanzania - member map / dashboard statistics layer.
 *
 * Single source of truth for every number the public dashboard shows. Both
 * the page (dashboard.php) and the JSON endpoints (api/dashboard/*) call the
 * functions here, so a figure can never differ between the two.
 *
 * PUBLISHING RULES (enforced in code, not just documented):
 *   1. Only aggregate counts are produced. No names, contacts, addresses or
 *      coordinates ever leave this layer.
 *   2. By the owner's decision the register is published in full: every region
 *      and district holding at least one approved member is shown with its
 *      real number, and the map bubbles are sized from those real numbers.
 *   3. The small-cohort protection is still implemented and can be switched
 *      back on without a code change - set 'dashboardMinCohort' and/or
 *      'dashboardMinTotal' in mail-config.php. A cell below the cohort floor
 *      is then folded into "Other / not specified" (district view) or hidden
 *      behind the withheld band (map view), exactly as before.
 *
 * Reference points used by the map are administrative centroids from
 * includes/geo.php - never a member's own location.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/geo.php';

/** Members in a cell required before its exact number may be published. */
const BDST_DASHBOARD_MIN_COHORT = 1;

/** Approved members overall required before any geographic detail is shown. */
const BDST_DASHBOARD_MIN_TOTAL = 1;

/**
 * Optional overrides from mail-config.php:
 *   'dashboardMinCohort' => 3,
 *   'dashboardMinTotal'  => 3,
 * At the shipped defaults of 1 nothing is suppressed: a single approved
 * member is published (and drawn as the smallest bubble). Raise either value
 * to restore the small-cohort protection. Values below 1 are treated as 1.
 */
function bdst_dashboard_min_cohort(): int
{
    return max(1, bdst_dashboard_setting('dashboardMinCohort', BDST_DASHBOARD_MIN_COHORT));
}

function bdst_dashboard_min_total(): int
{
    return max(1, bdst_dashboard_setting('dashboardMinTotal', BDST_DASHBOARD_MIN_TOTAL));
}

function bdst_dashboard_setting(string $key, int $default): int
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $value = $default;
    try {
        $config = bdst_config();
        if (isset($config[$key]) && is_numeric($config[$key])) {
            $value = (int) $config[$key];
        }
    } catch (Throwable $e) {
        // No configuration available: fall back to the safe default.
        $value = $default;
    }

    return $cache[$key] = $value;
}

/**
 * Read the approved registrations and group them by official region/district.
 *
 * @return array{total: int, regions: array<string, int>, districts: array<string, array<string, int>>, unmatched_region: int, unmatched_district: int, unmatched_districts_by_region: array<string, int>, districts_covered: int, regions_covered: int}
 */
function bdst_dashboard_data(PDO $pdo): array
{
    $regions                = [];
    $districts              = [];
    $unmatchedDistrictsByRegion = [];
    foreach (bdst_region_names() as $name) {
        $regions[$name]                 = 0;
        $districts[$name]               = [];
        $unmatchedDistrictsByRegion[$name] = 0;
    }

    $stmt = $pdo->query(
        "SELECT region, district, COUNT(*) AS c
           FROM bds_registrations
          WHERE status = 'approved'
       GROUP BY region, district"
    );

    $total             = 0;
    $unmatchedRegion   = 0;
    $unmatchedDistrict = 0;

    foreach ($stmt->fetchAll() as $row) {
        $count = (int) $row['c'];
        $total += $count;

        $region = bdst_normalize_region((string) $row['region']);
        if ($region === null) {
            $unmatchedRegion += $count;
            continue;
        }
        $regions[$region] += $count;

        $district = bdst_normalize_district((string) $row['district'], $region);
        if ($district === null) {
            $unmatchedDistrict += $count;
            $unmatchedDistrictsByRegion[$region] += $count;
            continue;
        }
        $districts[$region][$district] = ($districts[$region][$district] ?? 0) + $count;
    }

    $regionsCovered   = 0;
    $districtsCovered = 0;
    foreach ($districts as $list) {
        $districtsCovered += count($list);
    }
    foreach ($regions as $count) {
        if ($count > 0) {
            $regionsCovered++;
        }
    }

    return [
        'total'              => $total,
        'regions'            => $regions,
        'districts'          => $districts,
        'unmatched_region'   => $unmatchedRegion,
        'unmatched_district' => $unmatchedDistrict,
        'unmatched_districts_by_region' => $unmatchedDistrictsByRegion,
        'regions_covered'    => $regionsCovered,
        'districts_covered'  => $districtsCovered,
    ];
}

/**
 * Band key for a count. Keeps the map's colour scale in one place.
 */
function bdst_dashboard_band(int $count, int $minCohort): string
{
    if ($count <= 0) {
        return 'zero';
    }
    if ($count < $minCohort) {
        return 'lt_min';
    }
    if ($count < 10) {
        return 'low';
    }
    if ($count < 25) {
        return 'mid';
    }
    return 'high';
}

/**
 * Human label for a band. Reads as plain prose so the map tooltip, the legend
 * and the table all say the same thing.
 */
function bdst_dashboard_band_label(string $band, int $minCohort): string
{
    $low = $minCohort <= 1 ? '1' : (string) $minCohort;

    switch ($band) {
        case 'withheld':
            return 'Not published';
        case 'zero':
            return 'No members';
        case 'lt_min':
            return 'Fewer than ' . $minCohort;
        case 'low':
            return $low . ' to 9 members';
        case 'mid':
            return '10 to 24 members';
        case 'high':
            return '25 or more members';
    }
    return '';
}

/**
 * Legend definition for the map / table.
 *
 * The "below the floor" entry only makes sense while a cohort floor exists,
 * so it disappears at the shipped default of 1.
 *
 * @return list<array{key: string, label: string}>
 */
function bdst_dashboard_legend(?int $minCohort = null): array
{
    $minCohort ??= bdst_dashboard_min_cohort();
    $low = $minCohort <= 1 ? '1' : (string) $minCohort;

    $legend = [];
    if ($minCohort > 1) {
        $legend[] = ['key' => 'lt_min', 'label' => 'Fewer than ' . $minCohort];
    }
    $legend[] = ['key' => 'zero', 'label' => 'No members'];
    $legend[] = ['key' => 'low',  'label' => $low . ' to 9 members'];
    $legend[] = ['key' => 'mid',  'label' => '10 to 24 members'];
    $legend[] = ['key' => 'high', 'label' => '25 or more members'];

    return $legend;
}

/**
 * Convert the raw counts into the public, privacy-filtered payload.
 *
 * @param array<string, mixed> $data Result of bdst_dashboard_data().
 * @return array<string, mixed>
 */
function bdst_dashboard_public(array $data, ?int $minCohort = null, ?int $minTotal = null, ?string $generatedAt = null): array
{
    $minCohort ??= bdst_dashboard_min_cohort();
    $minTotal  ??= bdst_dashboard_min_total();

    $total = (int) $data['total'];

    // A floor of 1 means "publish everything": the geographic breakdown is
    // shown even when a single member is on the register (or none at all).
    // A higher floor restores the whole-register gate.
    $published = $minTotal <= 1 || $total >= $minTotal;

    $regions  = [];
    $maxCount = 0;
    foreach (bdst_official_regions() as $name => $meta) {
        $count = (int) ($data['regions'][$name] ?? 0);
        $band  = $published ? bdst_dashboard_band($count, $minCohort) : 'withheld';
        if ($published && $count > $maxCount) {
            $maxCount = $count;
        }

        $regions[] = [
            'name'  => $name,
            'code'  => $meta['id'],
            'slug'  => $meta['slug'],
            'lat'   => $meta['lat'],
            'lon'   => $meta['lon'],
            // A suppressed cell is reported as null, never as its real value.
            'count' => ($published && $band !== 'lt_min') ? $count : null,
            'band'  => $band,
            'label' => bdst_dashboard_band_label($band, $minCohort),
        ];
    }

    return [
        'generated_at' => $generatedAt ?? gmdate('c'),
        'published'    => $published,
        'thresholds'   => [
            'min_cohort' => $minCohort,
            'min_total'  => $minTotal,
        ],
        'max_count' => $maxCount,
        'totals' => [
            'members'            => $total,
            'regions_covered'    => $published ? (int) $data['regions_covered'] : null,
            'districts_covered'  => $published ? (int) $data['districts_covered'] : null,
            'unmatched_region'   => $published ? (int) $data['unmatched_region'] : null,
            'unmatched_district' => $published ? (int) $data['unmatched_district'] : null,
        ],
        'legend'  => bdst_dashboard_legend($minCohort),
        'regions' => $regions,
    ];
}

/**
 * District breakdown for one region.
 *
 * Returns null when the region code is unknown. When a cohort floor is in
 * force and this region has fewer members than the floor, 'items' is empty
 * and 'reason' explains why - no district names are disclosed either. At the
 * shipped floor of 1 the region is always published, including a region with
 * zero members (which simply has no rows).
 *
 * @param array<string, mixed> $data Result of bdst_dashboard_data().
 * @return array<string, mixed>|null
 */
function bdst_dashboard_region_detail(array $data, string $regionCode, ?int $minCohort = null, ?int $minTotal = null): ?array
{
    $minCohort ??= bdst_dashboard_min_cohort();
    $minTotal  ??= bdst_dashboard_min_total();

    $code = strtoupper(trim($regionCode));
    $name = null;
    $meta = null;
    foreach (bdst_official_regions() as $regionName => $regionMeta) {
        if (strtoupper($regionMeta['id']) === $code) {
            $name = $regionName;
            $meta = $regionMeta;
            break;
        }
    }
    if ($name === null || $meta === null) {
        return null;
    }

    $total           = (int) ($data['regions'][$name] ?? 0);
    $globalPublished = $minTotal <= 1 || (int) $data['total'] >= $minTotal;
    // A region with no members still gets published: there is nothing to
    // protect in an empty list, and saying "0 members" is not a disclosure.
    $published       = $globalPublished && ($minCohort <= 1 || $total >= $minCohort || $total === 0);

    $out = [
        'region'     => $name,
        'code'       => $meta['id'],
        'slug'       => $meta['slug'],
        'published'  => $published,
        'min_cohort' => $minCohort,
        'reason'     => $published ? null : ($globalPublished ? 'below_cohort' : 'withheld'),
        'total'      => $published ? $total : null,
        'items'      => [],
    ];

    if (!$published) {
        // No district detail at all, not even names. Naming a district would
        // narrow a cohort of one or two members down to a specific area -
        // exactly what the threshold exists to prevent - and it would also
        // contradict the published branch below, which folds sub-cohort
        // districts into "Other / not specified" rather than listing them.
        // The 'reason' field tells the panel what to say instead.
        return $out;
    }

    $list       = $data['districts'][$name] ?? [];
    $small      = 0;
    $smallCount = 0;
    foreach ($list as $district => $count) {
        if ($count < $minCohort) {
            // Do not publish this cell on its own: fold it into "Other".
            $small++;
            $smallCount += $count;
            continue;
        }
        $band = bdst_dashboard_band($count, $minCohort);
        $out['items'][] = [
            'name'  => $district,
            'count' => $count,
            'band'  => $band,
            'label' => (string) $count,
        ];
    }

    $unmatchedHere = (int) ($data['unmatched_districts_by_region'][$name] ?? 0);
    if ($small > 0 || $unmatchedHere > 0) {
        // Members in this region whose district is unknown or too small to
        // publish on its own.
        $rest = $smallCount + $unmatchedHere;
        $band = $rest >= $minCohort ? 'low' : 'lt_min';
        $out['items'][] = [
            'name'  => 'Other / not specified',
            'count' => $rest >= $minCohort ? $rest : null,
            'band'  => $band,
            'label' => $rest >= $minCohort ? (string) $rest : 'Fewer than ' . $minCohort,
        ];
    }

    $out['items'] = bdst_dashboard_sort_items($out['items']);
    return $out;
}

/**
 * Sort published rows by count desc, then name; suppressed rows last.
 *
 * @param list<array{name: string, count: int|null, band: string, label: string}> $items
 * @return list<array{name: string, count: int|null, band: string, label: string}>
 */
function bdst_dashboard_sort_items(array $items): array
{
    usort($items, static function (array $a, array $b): int {
        $ac = $a['count'];
        $bc = $b['count'];
        if ($ac === null && $bc !== null) {
            return 1;
        }
        if ($ac !== null && $bc === null) {
            return -1;
        }
        if ($ac !== $bc) {
            return $bc <=> $ac;
        }
        return strcmp($a['name'], $b['name']);
    });
    return $items;
}

/**
 * Sort public region rows for display: most members first, then name.
 *
 * Shared by the ranked list and the full table so their order can never differ.
 *
 * @param list<array<string, mixed>> $regions
 * @return list<array<string, mixed>>
 */
function bdst_dashboard_sort_regions(array $regions): array
{
    usort($regions, static function (array $a, array $b): int {
        $ac = $a['count'] === null ? 0 : (int) $a['count'];
        $bc = $b['count'] === null ? 0 : (int) $b['count'];
        if ($ac !== $bc) {
            return $bc <=> $ac;
        }
        return strcmp((string) $a['name'], (string) $b['name']);
    });
    return $regions;
}

/**
 * Width (as a percentage of the tallest bar) for a count bar.
 *
 * A non-zero count is never drawn thinner than 5% so a lone member still has
 * a visible bar, and a zero count is drawn as an empty track.
 */
function bdst_dashboard_bar_percent(int $count, int $max): int
{
    if ($max <= 0 || $count <= 0) {
        return 0;
    }
    return max(5, (int) round($count * 100 / $max));
}

/* -------------------------------------------------------------------------
   Registration trends (Phase 4)

   Aggregate counts over time, derived from submitted_at. A bucket is a
   number of members, never a member: no region, district or identity is
   attached. Bucketing is done in SQL with a per-driver expression so the
   same code serves MySQL in production and SQLite in the local preview.
   ------------------------------------------------------------------------- */

/** Period keys the trends endpoint understands. */
const BDST_DASHBOARD_PERIODS = ['daily', 'weekly', 'monthly', 'yearly'];

/** Hard cap on buckets in one response, so a wild range cannot flood a client. */
const BDST_DASHBOARD_MAX_BUCKETS = 240;

/**
 * @return list<string>
 */
function bdst_dashboard_periods(): array
{
    return BDST_DASHBOARD_PERIODS;
}

function bdst_dashboard_valid_period(string $period): bool
{
    return in_array($period, BDST_DASHBOARD_PERIODS, true);
}

/**
 * Validate a from/to bucket token for a period. Returns the trimmed token, or
 * null when it is empty or malformed - callers turn a malformed value into a
 * 400 rather than letting anything near SQL.
 */
function bdst_dashboard_period_token(string $period, ?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    switch ($period) {
        case 'yearly':
            $pattern = '/^\d{4}$/';
            break;
        case 'daily':
            $pattern = '/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/';
            break;
        case 'weekly':
            $pattern = '/^\d{4}-\d{2}$/';
            break;
        default: // monthly
            $pattern = '/^\d{4}-(0[1-9]|1[0-2])$/';
    }

    return preg_match($pattern, $value) === 1 ? $value : null;
}

/** True when a value was supplied but is not a valid token for the period. */
function bdst_dashboard_period_token_invalid(string $period, ?string $value): bool
{
    if ($value === null || trim($value) === '') {
        return false;
    }
    return bdst_dashboard_period_token($period, $value) === null;
}

/**
 * SQL expression reducing submitted_at to a bucket key for the driver in use.
 *
 * The period is whitelisted by the caller, so the expression is chosen from a
 * fixed map and is never built from raw input.
 */
function bdst_dashboard_period_expr(PDO $pdo, string $period): ?string
{
    $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    $sqlite = strpos($driver, 'sqlite') !== false;

    $map = $sqlite ? [
        'daily'   => "strftime('%Y-%m-%d', submitted_at)",
        'weekly'  => "strftime('%Y-%W', submitted_at)",
        'monthly' => "strftime('%Y-%m', submitted_at)",
        'yearly'  => "strftime('%Y', submitted_at)",
    ] : [
        'daily'   => "DATE_FORMAT(submitted_at, '%Y-%m-%d')",
        'weekly'  => "DATE_FORMAT(submitted_at, '%x-%v')",
        'monthly' => "DATE_FORMAT(submitted_at, '%Y-%m')",
        'yearly'  => "DATE_FORMAT(submitted_at, '%Y')",
    ];

    return $map[$period] ?? null;
}

/** Human label for a bucket key, e.g. "2026-03" becomes "Mar 2026". */
function bdst_dashboard_bucket_label(string $period, string $key): string
{
    static $months = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    if ($period === 'monthly' && preg_match('/^(\d{4})-(\d{2})$/', $key, $m) === 1) {
        return ($months[(int) $m[2]] ?? $m[2]) . ' ' . $m[1];
    }
    if ($period === 'daily' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $key, $m) === 1) {
        return (int) $m[3] . ' ' . ($months[(int) $m[2]] ?? $m[2]) . ' ' . $m[1];
    }
    if ($period === 'weekly' && preg_match('/^(\d{4})-(\d{2})$/', $key, $m) === 1) {
        return $m[1] . ' W' . $m[2];
    }
    return $key;
}

/**
 * Expand sparse bucket counts into an ordered series with a running
 * cumulative total. Gaps are filled with zeros when a bounded range is known,
 * so the chart does not show a misleading jump.
 *
 * @param array<string, int> $counts
 * @return list<array{key: string, label: string, count: int, cumulative: int}>
 */
function bdst_dashboard_fill_buckets(array $counts, string $period, ?string $from, ?string $to): array
{
    ksort($counts);
    $keys = array_keys($counts);

    $bounded = $from !== null && $to !== null && $from <= $to && $period !== 'weekly';
    if (!$bounded) {
        $series = $keys;
    } else {
        $series = [];
        try {
            if ($period === 'yearly') {
                $a = (int) $from;
                $b = (int) $to;
                if ($b - $a > BDST_DASHBOARD_MAX_BUCKETS) {
                    $b = $a + BDST_DASHBOARD_MAX_BUCKETS;
                }
                for ($y = $a; $y <= $b; $y++) {
                    $series[] = (string) $y;
                }
            } elseif ($period === 'monthly') {
                $cursor = new DateTimeImmutable($from . '-01');
                $end    = new DateTimeImmutable($to . '-01');
                while ($cursor <= $end && count($series) < BDST_DASHBOARD_MAX_BUCKETS) {
                    $series[] = $cursor->format('Y-m');
                    $cursor = $cursor->modify('+1 month');
                }
            } else { // daily
                $cursor = new DateTimeImmutable($from);
                $end    = new DateTimeImmutable($to);
                while ($cursor <= $end && count($series) < BDST_DASHBOARD_MAX_BUCKETS) {
                    $series[] = $cursor->format('Y-m-d');
                    $cursor = $cursor->modify('+1 day');
                }
            }
        } catch (Throwable $e) {
            // Never let a bad range blank the series: fall back to the data.
            $series = $keys;
        }
    }

    $out        = [];
    $cumulative = 0;
    foreach ($series as $key) {
        $count       = (int) ($counts[(string) $key] ?? 0);
        $cumulative += $count;
        $out[] = [
            'key'        => (string) $key,
            'label'      => bdst_dashboard_bucket_label($period, (string) $key),
            'count'      => $count,
            'cumulative' => $cumulative,
        ];
    }

    return $out;
}

/**
 * Approved-member registrations over time.
 *
 * @return array{period: string, periods: list<string>, from: ?string, to: ?string, published: bool, total: ?int, peak: int, buckets: list<array{key: string, label: string, count: int, cumulative: int}>}
 */
function bdst_dashboard_trends(PDO $pdo, ?int $minTotal = null, string $period = 'monthly', ?string $from = null, ?string $to = null): array
{
    $minTotal ??= bdst_dashboard_min_total();
    if (!bdst_dashboard_valid_period($period)) {
        $period = 'monthly';
    }

    $from = bdst_dashboard_period_token($period, $from);
    $to   = bdst_dashboard_period_token($period, $to);
    if ($from !== null && $to !== null && $from > $to) {
        $swap = $from;
        $from = $to;
        $to   = $swap;
    }

    $total = (int) $pdo->query("SELECT COUNT(*) FROM bds_registrations WHERE status = 'approved'")->fetchColumn();

    // The series carries no location, but it still reflects register size, so
    // it obeys the same global publish gate as the map.
    $published = $minTotal <= 1 || $total >= $minTotal;

    $out = [
        'period'    => $period,
        'periods'   => bdst_dashboard_periods(),
        'from'      => $from,
        'to'        => $to,
        'published' => $published,
        'total'     => $published ? $total : null,
        'peak'      => 0,
        'buckets'   => [],
    ];
    if (!$published) {
        return $out;
    }

    $expr = bdst_dashboard_period_expr($pdo, $period);
    if ($expr === null) {
        return $out;
    }

    $counts = [];
    $rows   = $pdo->query(
        "SELECT $expr AS bucket, COUNT(*) AS c
           FROM bds_registrations
          WHERE status = 'approved'
       GROUP BY bucket
       ORDER BY bucket"
    )->fetchAll();
    foreach ($rows as $row) {
        $key = (string) $row['bucket'];
        if ($key === '' || $key === '0') {
            continue;
        }
        $counts[$key] = (int) $row['c'];
    }

    // With no explicit window, span the data so internal gaps still get filled.
    if ($counts !== []) {
        $keys = array_keys($counts);
        sort($keys);
        if ($from === null) {
            $from = (string) $keys[0];
        }
        if ($to === null) {
            $to = (string) $keys[count($keys) - 1];
        }
    }

    $buckets = bdst_dashboard_fill_buckets($counts, $period, $from, $to);

    $peak = 0;
    foreach ($buckets as $bucket) {
        if ($bucket['count'] > $peak) {
            $peak = $bucket['count'];
        }
    }

    $out['from']    = $from;
    $out['to']      = $to;
    $out['peak']    = $peak;
    $out['buckets'] = $buckets;

    return $out;
}
