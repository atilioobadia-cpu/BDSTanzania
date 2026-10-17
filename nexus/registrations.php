<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../includes/forms.php';
require __DIR__ . '/_registrations.php';
require __DIR__ . '/_sidebar.php';
bdst_require_login();

$pdo = bdst_db();

/**
 * Where to send the browser back to after a POST, so a status change or a
 * bulk action never throws away the administrator's filters.
 */
$returnQuery = [];
foreach (['q', 'status', 'sort', 'dir', 'per', 'page'] as $key) {
    $value = (string) ($_POST['return_' . $key] ?? '');
    if ($value !== '') {
        $returnQuery[$key] = $value;
    }
}
$returnUrl = 'registrations.php';
$returnParams = bdst_reg_query_params($returnQuery);
if ($returnParams !== '') {
    $returnUrl .= '?' . $returnParams;
}

// ---------------------------------------------------------
// Status changes (single + bulk), POST only
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bdst_csrf_verify($_POST['csrf'] ?? null)) {
        bdst_flash('error', 'Security token expired. Please try again.');
        header('Location: registrations.php');
        exit;
    }

    $bulkAction = (string) ($_POST['bulk_action'] ?? '');

    if ($bulkAction !== '') {
        $seen = [];
        foreach ((array) ($_POST['ids'] ?? []) as $raw) {
            $value = (int) $raw;
            if ($value > 0) {
                $seen[$value] = true;
            }
        }
        $ids = array_keys($seen);

        if ($ids === []) {
            bdst_flash('error', 'Select at least one registration first.');
            header('Location: ' . $returnUrl);
            exit;
        }

        $target = match ($bulkAction) {
            'approve' => 'approved',
            'review'  => 'reviewed',
            'reject'  => 'rejected',
            default   => null,
        };

        try {
            if ($bulkAction === 'delete') {
                $removed = bdst_reg_delete_ids($pdo, $ids);
                bdst_flash('success', $removed . ' registration' . ($removed === 1 ? '' : 's') . ' deleted.');
            } elseif ($target !== null) {
                $holders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare(
                    "UPDATE bds_registrations
                        SET status = ?, reviewed_at = CURRENT_TIMESTAMP
                      WHERE id IN ($holders)"
                );
                $stmt->execute(array_merge([$target], $ids));
                $changed = $stmt->rowCount();
                bdst_flash(
                    'success',
                    $changed . ' registration' . ($changed === 1 ? '' : 's')
                    . ' marked as "' . bdst_reg_status_label($target) . '".'
                );
            } else {
                bdst_flash('error', 'Unknown bulk action.');
            }
        } catch (Throwable $e) {
            error_log('Bulk registration action failed: ' . $e->getMessage());
            bdst_flash('error', 'The bulk action could not be completed. Please try again.');
        }

        header('Location: ' . $returnUrl);
        exit;
    }

    $id     = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');

    if ($id > 0 && in_array($status, bdst_registration_statuses(), true)) {
        try {
            $stmt = $pdo->prepare('SELECT reference_no FROM bds_registrations WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $reference = (string) ($stmt->fetchColumn() ?: ('#' . $id));

            $stmt = $pdo->prepare(
                'UPDATE bds_registrations SET status = :status, reviewed_at = CURRENT_TIMESTAMP WHERE id = :id'
            );
            $stmt->execute([':status' => $status, ':id' => $id]);

            bdst_flash('success', 'Registration ' . $reference . ' marked as "' . bdst_reg_status_label($status) . '".');
        } catch (Throwable $e) {
            error_log('Registration status update failed: ' . $e->getMessage());
            bdst_flash('error', 'The status could not be updated. Please try again.');
        }
    } else {
        bdst_flash('error', 'Invalid status update request.');
    }

    header('Location: ' . $returnUrl);
    exit;
}

// ---------------------------------------------------------
// List + filters + sort + pagination (GET)
// ---------------------------------------------------------
$state = bdst_reg_read_query($_GET);

$registrations = [];
$total         = 0;
$totalPages    = 1;
$offset        = 0;
$counts        = ['total' => 0] + array_fill_keys(bdst_registration_statuses(), 0);
$recent        = array_fill_keys(bdst_registration_statuses(), 0);
$recentTotal   = 0;
$loadError     = '';

try {
    [$where, $params] = bdst_reg_where($state);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bds_registrations WHERE $where");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $totalPages = max(1, (int) ceil($total / $state['per']));
    $state['page'] = min($state['page'], $totalPages);
    $offset = ($state['page'] - 1) * $state['per'];

    $stmt = $pdo->prepare(
        "SELECT id, reference_no, full_name, email, phone, region, district,
                professional_status, status, submitted_at, cv_path
           FROM bds_registrations
          WHERE $where
          ORDER BY " . bdst_reg_order_by($state['sort'], $state['dir']) . "
          LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $state['per'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $registrations = $stmt->fetchAll();

    foreach ($pdo->query('SELECT status, COUNT(*) AS c FROM bds_registrations GROUP BY status') as $row) {
        $counts[(string) $row['status']] = (int) $row['c'];
        $counts['total'] += (int) $row['c'];
    }

    // "Last 30 days" activity, computed in PHP so the query stays portable.
    $stmt = $pdo->prepare(
        'SELECT status, COUNT(*) AS c FROM bds_registrations WHERE submitted_at >= :since GROUP BY status'
    );
    $stmt->execute([':since' => date('Y-m-d H:i:s', strtotime('-30 days'))]);
    foreach ($stmt->fetchAll() as $row) {
        $recent[(string) $row['status']] = (int) $row['c'];
        $recentTotal += (int) $row['c'];
    }
} catch (Throwable $e) {
    error_log('Registrations list error: ' . $e->getMessage());
    $loadError = 'Registrations could not be loaded right now.';
}

$flash = bdst_flash_consume();

$first = $total === 0 ? 0 : $offset + 1;
$last  = min($offset + $state['per'], $total);

$hasFilters = $state['q'] !== '' || $state['status'] !== '';

/**
 * Soft, deterministic tint for the initials bubble so the list is easier to
 * scan without storing a colour per member.
 */
function reg_avatar_class(string $name): string
{
    return 'cell-avatar cell-avatar--t' . (abs(crc32($name)) % 6);
}

function reg_status_badge(string $status): string
{
    return '<span class="badge badge-reg-' . bdst_e($status) . '">'
        . bdst_e(bdst_reg_status_label($status)) . '</span>';
}

/**
 * Windowed page list for the pager: 1 … 4 5 6 … 12
 *
 * @return list<int|string>
 */
function reg_pager_pages(int $current, int $totalPages): array
{
    if ($totalPages <= 7) {
        return range(1, $totalPages);
    }

    $pages = [1];
    $start = max(2, $current - 1);
    $end   = min($totalPages - 1, $current + 1);

    if ($start > 2) {
        $pages[] = '…';
    }
    for ($i = $start; $i <= $end; $i++) {
        $pages[] = $i;
    }
    if ($end < $totalPages - 1) {
        $pages[] = '…';
    }
    $pages[] = $totalPages;

    return $pages;
}

$cards = [
    [
        'key'    => 'total',
        'label'  => 'Total',
        'value'  => $counts['total'],
        'mod'    => '',
        'delta'  => $recentTotal,
        'href'   => bdst_reg_list_url($state, ['status' => '', 'page' => 1]),
        'active' => $state['status'] === '',
    ],
    [
        'key'    => 'pending',
        'label'  => 'Pending review',
        'value'  => $counts['pending'],
        'mod'    => 'warning',
        'delta'  => $recent['pending'],
        'href'   => bdst_reg_list_url($state, ['status' => 'pending', 'page' => 1]),
        'active' => $state['status'] === 'pending',
    ],
    [
        'key'    => 'reviewed',
        'label'  => 'Under review',
        'value'  => $counts['reviewed'],
        'mod'    => 'info',
        'delta'  => $recent['reviewed'],
        'href'   => bdst_reg_list_url($state, ['status' => 'reviewed', 'page' => 1]),
        'active' => $state['status'] === 'reviewed',
    ],
    [
        'key'    => 'approved',
        'label'  => 'Approved',
        'value'  => $counts['approved'],
        'mod'    => 'success',
        'delta'  => $recent['approved'],
        'href'   => bdst_reg_list_url($state, ['status' => 'approved', 'page' => 1]),
        'active' => $state['status'] === 'approved',
    ],
    [
        'key'    => 'rejected',
        'label'  => 'Rejected',
        'value'  => $counts['rejected'],
        'mod'    => 'muted',
        'delta'  => $recent['rejected'],
        'href'   => bdst_reg_list_url($state, ['status' => 'rejected', 'page' => 1]),
        'active' => $state['status'] === 'rejected',
    ],
];

$cardIcons = [
    'total'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'pending'  => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    'reviewed' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/>',
    'approved' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
    'rejected' => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
];

/** Hidden inputs that carry the current list state through a POST. */
$returnFields = static function (array $from) : void {
    foreach ($from as $key => $value) {
        printf(
            '<input type="hidden" name="return_%s" value="%s">',
            bdst_e((string) $key),
            bdst_e((string) $value)
        );
    }
};

/** Sortable column heading. */
$sortHeading = static function (string $key, string $label, string $class = '') use ($state): void {
    $isActive = $state['sort'] === $key;
    $nextDir  = $isActive && $state['dir'] === 'asc' ? 'desc' : 'asc';
    $ariaSort = $isActive ? ($state['dir'] === 'asc' ? 'ascending' : 'descending') : 'none';
    ?>
    <th scope="col" class="<?= bdst_e(trim('th-sort ' . $class)) ?>" aria-sort="<?= $ariaSort ?>">
        <a class="th-sort-link<?= $isActive ? ' is-active' : '' ?>"
           href="<?= bdst_e(bdst_reg_list_url($state, ['sort' => $key, 'dir' => $nextDir, 'page' => 1])) ?>">
            <span><?= bdst_e($label) ?></span>
            <span class="th-sort-icon" aria-hidden="true">
                <svg viewBox="0 0 10 14" width="9" height="13" fill="currentColor">
                    <path class="caret-up" d="M5 0 0 6h10z"/>
                    <path class="caret-down" d="M5 14 0 8h10z"/>
                </svg>
            </span>
        </a>
    </th>
    <?php
};

$exportQuery = bdst_reg_query_params($state, [], ['page']);
$exportUrl   = 'registrations_export.php' . ($exportQuery === '' ? '' : '?' . $exportQuery);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= bdst_admin_title('Registrations') ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/images/fav.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= bdst_e(bdst_admin_css()) ?>">
    <script>
        // Page configuration for registrations.js. Hex-escaped so no value that
        // arrived through the query string can break out of this script block.
        window.BDST_ADMIN = <?= json_encode([
            'csrf'    => bdst_csrf_token(),
            'state'   => array_map(static fn ($v): string => (string) $v, $state),
            'exportUrl' => 'registrations_export.php',
            'detailUrl' => 'registration_json.php',
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

        // Inline fallback: destructive actions stay confirmed even if the page
        // script fails to load. registrations.js replaces the UI behaviour.
        window.bdstUiConfirm = null;

        function bdstAskConfirm(ev, form) {
            var message = form.getAttribute('data-confirm') || '';
            var submitter = ev && ev.submitter;
            if (!message && submitter && submitter.hasAttribute('data-confirm')) {
                message = submitter.getAttribute('data-confirm');
            }
            if (!message) {
                return true;
            }
            if (typeof window.bdstUiConfirm === 'function') {
                window.bdstUiConfirm(ev, form, message, submitter || null);
                return false;
            }
            return window.confirm(message);
        }
    </script>
    <script src="registrations.js" defer></script>
</head>
<body class="admin-body">
<?php bdst_admin_sidebar('registrations'); ?>

<main class="main">
    <header class="topbar">
        <div class="topbar-inner">
            <?php bdst_admin_nav_toggle(); ?>
            <div class="page-title-block">
                <h1>Member Registrations</h1>
                <p>Review applicants, manage statuses and download CVs.</p>
            </div>
            <a href="../register/members.php" class="btn btn-ghost" target="_blank" rel="noopener">Public Directory</a>
        </div>
    </header>

    <div class="page">
        <?php if ($flash): ?>
            <div class="alert alert-<?= bdst_e($flash['type']) ?>" role="status"><?= bdst_e($flash['message']) ?></div>
        <?php endif; ?>

        <?php if ($loadError !== ''): ?>
            <div class="alert alert-error" role="alert"><?= bdst_e($loadError) ?></div>
        <?php endif; ?>

        <div class="stats">
            <?php foreach ($cards as $card): ?>
                <a class="stat stat--link<?= $card['active'] ? ' is-current' : '' ?>"
                   href="<?= bdst_e($card['href']) ?>"
                   <?= $card['active'] ? 'aria-current="true"' : '' ?>>
                    <span class="stat-icon<?= $card['mod'] !== '' ? ' stat-icon--' . bdst_e($card['mod']) : '' ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $cardIcons[$card['key']] ?></svg>
                    </span>
                    <span class="stat-body">
                        <span class="stat-value"><?= $card['value'] ?></span>
                        <span class="stat-label"><?= bdst_e($card['label']) ?></span>
                        <span class="stat-foot">
                            <?php if ($card['delta'] > 0): ?>
                                +<?= $card['delta'] ?> in 30 days
                            <?php else: ?>
                                None in 30 days
                            <?php endif; ?>
                        </span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="panel panel--registrations">
            <div class="toolbar">
                <form class="toolbar-form" id="filterForm" method="get" action="registrations.php">
                    <input type="hidden" name="sort" value="<?= bdst_e($state['sort']) ?>">
                    <input type="hidden" name="dir" value="<?= bdst_e($state['dir']) ?>">

                    <div class="search-field">
                        <span class="search-icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </span>
                        <input type="search" name="q" value="<?= bdst_e($state['q']) ?>"
                               placeholder="Search name, firm, email, phone, reference or location…"
                               aria-label="Search registrations">
                    </div>

                    <label class="select-field">
                        <span class="sr-only">Filter by status</span>
                        <select name="status" data-autosubmit>
                            <option value="">All statuses</option>
                            <?php foreach (bdst_registration_statuses() as $statusOption): ?>
                                <option value="<?= bdst_e($statusOption) ?>"<?= $state['status'] === $statusOption ? ' selected' : '' ?>>
                                    <?= bdst_e(bdst_reg_status_label($statusOption)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <button type="submit" class="btn btn-primary">Apply</button>

                    <?php if ($hasFilters): ?>
                        <a class="btn btn-ghost btn-clear" href="<?= bdst_e(bdst_reg_list_url($state, ['q' => '', 'status' => '', 'page' => 1])) ?>"
                           title="Clear filters" aria-label="Clear filters">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            <span class="btn-clear-label">Clear</span>
                        </a>
                    <?php endif; ?>
                </form>

                <div class="toolbar-actions">
                    <label class="select-field select-field--per">
                        <span>Rows</span>
                        <select name="per" form="filterForm" data-autosubmit
                                aria-label="Rows per page">
                            <?php foreach (bdst_reg_per_page_options() as $option): ?>
                                <option value="<?= $option ?>"<?= $state['per'] === $option ? ' selected' : '' ?>><?= $option ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <a class="btn btn-ghost" href="<?= bdst_e($exportUrl) ?>">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export CSV
                    </a>
                </div>
            </div>

            <?php if ($registrations === []): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <?php if ($hasFilters): ?>
                        <p>No registrations match your filters.</p>
                        <p class="empty-hint">Try a different search term, or clear the filters to see everything.</p>
                        <a class="btn btn-ghost" href="<?= bdst_e(bdst_reg_list_url($state, ['q' => '', 'status' => '', 'page' => 1])) ?>">Clear filters</a>
                    <?php else: ?>
                        <p>No registrations yet.</p>
                        <p class="empty-hint">New applications will appear here as soon as they are submitted.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form id="bulkForm" method="post" action="registrations.php"
                      onsubmit="return bdstAskConfirm(event, this)">
                    <input type="hidden" name="csrf" value="<?= bdst_e(bdst_csrf_token()) ?>">
                    <?php $returnFields($state); ?>

                    <div class="bulkbar" id="bulkBar" hidden>
                        <span class="bulkbar-count" data-bulk-count aria-live="polite"></span>
                        <div class="bulkbar-actions">
                            <button type="submit" class="btn btn-primary btn-sm" name="bulk_action" value="approve">Approve</button>
                            <button type="submit" class="btn btn-ghost btn-sm" name="bulk_action" value="review">Mark under review</button>
                            <button type="submit" class="btn btn-ghost btn-sm" name="bulk_action" value="reject">Reject</button>
                            <button type="button" class="btn btn-ghost btn-sm" id="bulkExport">Export selected</button>
                            <button type="submit" class="btn btn-danger btn-sm" name="bulk_action" value="delete"
                                    data-confirm="Delete the selected registrations permanently? Their uploaded CVs are removed too."
                                    data-confirm-ok="Delete">Delete</button>
                        </div>
                    </div>
                </form>

                <div class="table-wrap">
                    <table class="table table-registrations">
                        <caption class="sr-only">Member registrations, sorted by <?= bdst_e($state['sort']) ?></caption>
                        <thead>
                            <tr>
                                <th scope="col" class="th-check">
                                    <input type="checkbox" id="selectAll" aria-label="Select all registrations on this page">
                                </th>
                                <?php $sortHeading('name', 'Applicant'); ?>
                                <?php $sortHeading('location', 'Location', 'col-location'); ?>
                                <?php $sortHeading('status', 'Status'); ?>
                                <?php $sortHeading('date', 'Submitted', 'col-date'); ?>
                                <th scope="col" class="th-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registrations as $reg): ?>
                                <?php $initial = mb_strtoupper(mb_substr((string) $reg['full_name'], 0, 1)); ?>
                                <tr class="row-clickable" data-preview="<?= (int) $reg['id'] ?>">
                                    <td class="td-check">
                                        <input type="checkbox" name="ids[]" value="<?= (int) $reg['id'] ?>" form="bulkForm"
                                               aria-label="Select <?= bdst_e($reg['reference_no']) ?>">
                                    </td>
                                    <td>
                                        <div class="cell-title">
                                            <span class="<?= bdst_e(reg_avatar_class((string) $reg['full_name'])) ?>"><?= bdst_e($initial) ?></span>
                                            <span class="cell-name">
                                                <span class="cell-name__text"><?= bdst_e($reg['full_name']) ?></span>
                                                <span class="td-ref">
                                                    <?= bdst_e($reg['reference_no']) ?>
                                                    <?php if ((string) ($reg['cv_path'] ?? '') !== ''): ?>
                                                        <span class="cv-flag" title="CV attached" aria-label="CV attached">
                                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                                <small><?= bdst_e($reg['email']) ?></small>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="td-muted col-location"><?= bdst_e($reg['district']) ?>, <?= bdst_e($reg['region']) ?></td>
                                    <td class="td-status"><?= reg_status_badge((string) $reg['status']) ?></td>
                                    <td class="td-muted col-date"><?= bdst_e(date('M j, Y', (int) strtotime((string) $reg['submitted_at']))) ?></td>
                                    <td class="th-actions td-actions">
                                        <div class="row-actions">
                                            <a class="btn btn-ghost btn-icon" href="registration.php?id=<?= (int) $reg['id'] ?>"
                                               data-preview="<?= (int) $reg['id'] ?>"
                                               title="Preview" aria-label="Preview <?= bdst_e($reg['reference_no']) ?>">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            </a>
                                            <form method="post" action="registration_delete.php"
                                                  data-confirm="Delete <?= bdst_e($reg['reference_no']) ?> permanently? The uploaded CV is removed too."
                                                  data-confirm-ok="Delete"
                                                  onsubmit="return bdstAskConfirm(event, this)">
                                                <input type="hidden" name="csrf" value="<?= bdst_e(bdst_csrf_token()) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $reg['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-icon"
                                                        title="Delete" aria-label="Delete <?= bdst_e($reg['reference_no']) ?>">
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-foot">
                    <p class="table-count">
                        Showing <strong><?= $first ?>–<?= $last ?></strong> of <strong><?= $total ?></strong>
                        <?= $hasFilters ? ' matching registration' . ($total === 1 ? '' : 's') : ' registration' . ($total === 1 ? '' : 's') ?>
                    </p>

                    <?php if ($totalPages > 1): ?>
                        <nav class="pager" aria-label="Pagination">
                            <a class="pager-link<?= $state['page'] <= 1 ? ' is-disabled' : '' ?>"
                               href="<?= bdst_e(bdst_reg_list_url($state, ['page' => max(1, $state['page'] - 1)])) ?>"
                               <?= $state['page'] <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Previous</a>

                            <?php foreach (reg_pager_pages($state['page'], $totalPages) as $p): ?>
                                <?php if ($p === '…'): ?>
                                    <span class="pager-gap" aria-hidden="true">…</span>
                                <?php else: ?>
                                    <a class="pager-link<?= (int) $p === $state['page'] ? ' is-current' : '' ?>"
                                       href="<?= bdst_e(bdst_reg_list_url($state, ['page' => (int) $p])) ?>"
                                       <?= (int) $p === $state['page'] ? 'aria-current="page"' : '' ?>><?= (int) $p ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <a class="pager-link<?= $state['page'] >= $totalPages ? ' is-disabled' : '' ?>"
                               href="<?= bdst_e(bdst_reg_list_url($state, ['page' => min($totalPages, $state['page'] + 1)])) ?>"
                               <?= $state['page'] >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Next</a>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<div class="drawer-scrim" id="drawerScrim" hidden></div>
<aside class="drawer" id="regDrawer" role="dialog" aria-modal="true" aria-labelledby="drawerTitle" hidden>
    <div class="drawer-head">
        <div class="drawer-head-main">
            <span class="drawer-ref" id="drawerRef"></span>
            <h2 class="drawer-title" id="drawerTitle">Registration</h2>
            <span id="drawerStatus"></span>
        </div>
        <button type="button" class="drawer-close" id="drawerClose" aria-label="Close preview">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="drawer-body" id="drawerBody"></div>
    <div class="drawer-foot" id="drawerFoot"></div>
</aside>

<dialog class="amodal amodal--confirm" id="confirmModal" aria-labelledby="confirmTitle">
    <div class="amodal-icon bad">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    </div>
    <h3 id="confirmTitle">Please confirm</h3>
    <p id="confirmText"></p>
    <div class="amodal-actions">
        <button type="button" class="btn btn-ghost" id="confirmCancel">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmOk">Delete</button>
    </div>
</dialog>
</body>
</html>
