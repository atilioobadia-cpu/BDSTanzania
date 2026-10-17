<?php
declare(strict_types=1);

/**
 * CSV export of the admin registrations list.
 *
 * Honours exactly the same filters and sort order as the on-screen list, and
 * accepts ?ids=1,2,3 to export only the rows selected in the bulk bar.
 */

require __DIR__ . '/auth.php';
require __DIR__ . '/../includes/forms.php';
require __DIR__ . '/_registrations.php';
bdst_require_login();

$state = bdst_reg_read_query($_GET);

// Optional explicit selection from the bulk bar.
$ids = [];
$raw = trim((string) ($_GET['ids'] ?? ''));
if ($raw !== '') {
    $seen = [];
    foreach (explode(',', $raw) as $part) {
        $value = (int) trim($part);
        if ($value > 0) {
            $seen[$value] = true;
        }
    }
    $ids = array_keys($seen);
}

[$where, $params] = bdst_reg_where($state);

if ($ids !== []) {
    $holders = [];
    foreach ($ids as $index => $value) {
        $key = ':sel' . $index;
        $holders[] = $key;
        $params[$key] = $value;
    }
    $where .= ' AND id IN (' . implode(',', $holders) . ')';
}

try {
    $stmt = bdst_db()->prepare(
        "SELECT * FROM bds_registrations
          WHERE $where
          ORDER BY " . bdst_reg_order_by($state['sort'], $state['dir'])
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Registrations export error: ' . $e->getMessage());
    http_response_code(500);
    exit('Export failed.');
}

$filename = 'bds-registrations-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$out = fopen('php://output', 'wb');
if ($out === false) {
    exit;
}

fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads the file correctly.

/**
 * Prefix cells that a spreadsheet would treat as a formula, so a crafted
 * name or firm cannot execute on open.
 */
$safe = static function (string $value): string {
    if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
        return "'" . $value;
    }
    return $value;
};

fputcsv($out, [
    'Reference', 'Full name', 'Gender', 'Age group', 'Phone', 'Email',
    'Region', 'District', 'Professional status', 'Firm', 'Experience',
    'Education', 'Clients served', 'Service areas', 'Industries',
    'Client segments', 'Nature of services', 'Certifications', 'Zones',
    'Status', 'Submitted', 'Reviewed', 'CV',
]);

foreach ($rows as $row) {
    fputcsv($out, array_map($safe, [
        (string) $row['reference_no'],
        (string) $row['full_name'],
        (string) $row['gender'],
        (string) $row['age_group'],
        (string) $row['phone'],
        (string) $row['email'],
        (string) $row['region'],
        (string) $row['district'],
        (string) $row['professional_status'],
        (string) ($row['firm_name'] ?? ''),
        (string) $row['experience_years'],
        (string) $row['education'],
        (string) $row['clients_served'],
        implode('; ', bdst_decode_list($row['service_areas'] ?? null)),
        implode('; ', bdst_decode_list($row['industries'] ?? null)),
        implode('; ', bdst_decode_list($row['client_segments'] ?? null)),
        implode('; ', bdst_decode_list($row['service_nature'] ?? null)),
        implode('; ', bdst_decode_list($row['certifications'] ?? null)),
        implode('; ', bdst_decode_list($row['zones'] ?? null)),
        bdst_reg_status_label((string) $row['status']),
        (string) $row['submitted_at'],
        (string) ($row['reviewed_at'] ?? ''),
        ((string) ($row['cv_path'] ?? '')) !== '' ? 'Yes' : 'No',
    ]));
}

fclose($out);
exit;
