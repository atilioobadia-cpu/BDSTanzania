<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
bdst_require_login();

$pdo = bdst_db();

// ---------------------------------------------------------
// Quick status update (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bdst_csrf_verify($_POST['csrf'] ?? null)) {
        bdst_flash('error', 'Security token expired. Please try again.');
        header('Location: registrations.php');
        exit;
    }

    $id     = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');

    if ($id > 0 && in_array($status, bdst_registration_statuses(), true)) {
        $stmt = $pdo->prepare(
            "UPDATE bds_registrations SET status = :status, reviewed_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':status' => $status, ':id' => $id]);
        bdst_flash('success', 'Registration ' . bdst_reg_reference($id) . ' marked as "' . $status . '".');
    } else {
        bdst_flash('error', 'Invalid status update request.');
    }

    header('Location: registrations.php?' . http_build_query(array_filter([
        'status' => $_POST['return_status'] ?? '',
        'q'      => $_POST['return_q'] ?? '',
        'page'   => $_POST['return_page'] ?? '',
    ])));
    exit;
}

// ---------------------------------------------------------
// List + filters + pagination (GET)
// ---------------------------------------------------------
$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$where  = '1';
$params = [];

if ($search !== '') {
    $where .= ' AND (full_name LIKE :q1 OR email LIKE :q2 OR reference_no LIKE :q3 OR phone LIKE :q4)';
    $q = '%' . $search . '%';
    $params += [':q1' => $q, ':q2' => $q, ':q3' => $q, ':q4' => $q];
}
if (in_array($status, bdst_registration_statuses(), true)) {
    $where .= ' AND status = :status';
    $params[':status'] = $status;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bds_registrations WHERE $where");
    $stmt->execute($params);
    $total      = (int) $stmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT id, reference_no, full_name, email, phone, region, district,
                professional_status, status, submitted_at
         FROM bds_registrations
         WHERE $where
         ORDER BY submitted_at DESC, id DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $registrations = $stmt->fetchAll();

    $counts = ['total' => 0] + array_fill_keys(bdst_registration_statuses(), 0);
    foreach ($pdo->query("SELECT status, COUNT(*) AS c FROM bds_registrations GROUP BY status") as $row) {
        $counts[$row['status']] = (int) $row['c'];
        $counts['total'] += (int) $row['c'];
    }
} catch (Throwable $e) {
    error_log('Registrations list error: ' . $e->getMessage());
    $registrations = [];
    $total = 0;
    $totalPages = 1;
    $counts = ['total' => 0] + array_fill_keys(bdst_registration_statuses(), 0);
}

$flash = bdst_flash_consume();

function reg_status_badge(string $status): string
{
    return '<span class="badge badge-reg-' . bdst_e($status) . '">' . bdst_e(ucfirst($status)) . '</span>';
}
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
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">
<?php bdst_admin_sidebar('registrations'); ?>

<main class="main">
    <header class="topbar">
        <div class="topbar-inner">
            <div class="page-title-block">
                <h1>Member Registrations</h1>
                <p>Review applicants, manage statuses and download CVs.</p>
            </div>
            <a href="../register/members.php" class="btn btn-ghost" target="_blank" rel="noopener">Public Directory</a>
        </div>
    </header>

    <div class="page">
        <?php if ($flash): ?>
            <div class="alert alert-<?= bdst_e($flash['type']) ?>"><?= bdst_e($flash['message']) ?></div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat">
                <span class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <div><div class="stat-value"><?= $counts['total'] ?></div><div class="stat-label">Total</div></div>
            </div>
            <div class="stat">
                <span class="stat-icon stat-icon--warning"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                <div><div class="stat-value"><?= $counts['pending'] ?></div><div class="stat-label">Pending</div></div>
            </div>
            <div class="stat">
                <span class="stat-icon stat-icon--success"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
                <div><div class="stat-value"><?= $counts['approved'] ?></div><div class="stat-label">Approved</div></div>
            </div>
            <div class="stat">
                <span class="stat-icon stat-icon--muted"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></span>
                <div><div class="stat-value"><?= $counts['rejected'] ?></div><div class="stat-label">Rejected</div></div>
            </div>
        </div>

        <div class="panel">
            <form class="filter-bar" method="get" action="registrations.php">
                <input type="text" name="q" placeholder="Search name, email, phone or reference..."
                       value="<?= bdst_e($search) ?>">
                <select name="status">
                    <option value="">All Statuses</option>
                    <?php foreach (bdst_registration_statuses() as $statusOption): ?>
                        <option value="<?= bdst_e($statusOption) ?>"<?= $status === $statusOption ? ' selected' : '' ?>>
                            <?= bdst_e(ucfirst($statusOption)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($search !== '' || $status !== ''): ?>
                    <a class="btn btn-ghost" href="registrations.php">Clear</a>
                <?php endif; ?>
            </form>

            <?php if (empty($registrations)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <p>No registrations found.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Applicant</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th class="th-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registrations as $reg): ?>
                                <tr>
                                    <td class="td-ref"><?= bdst_e($reg['reference_no']) ?></td>
                                    <td>
                                        <div class="cell-title">
                                            <span class="cell-avatar"><?= bdst_e(mb_strtoupper(mb_substr($reg['full_name'], 0, 1))) ?></span>
                                            <span>
                                                <?= bdst_e($reg['full_name']) ?><br>
                                                <small class="td-muted"><?= bdst_e($reg['email']) ?></small>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="td-muted"><?= bdst_e($reg['district']) ?>, <?= bdst_e($reg['region']) ?></td>
                                    <td><?= reg_status_badge($reg['status']) ?></td>
                                    <td class="td-muted"><?= bdst_e(date('M j, Y', strtotime($reg['submitted_at']))) ?></td>
                                    <td class="th-actions">
                                        <div class="row-actions">
                                            <a class="btn btn-ghost btn-sm" href="registration.php?id=<?= (int) $reg['id'] ?>">Open</a>
                                            <form method="post" action="registration_delete.php" onsubmit="return confirm('Delete this registration permanently?');">
                                                <input type="hidden" name="csrf" value="<?= bdst_e(bdst_csrf_token()) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $reg['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="pager">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a class="pager-link<?= $i === $page ? ' is-current' : '' ?>"
                               href="?<?= bdst_e(http_build_query(['q' => $search, 'status' => $status, 'page' => $i])) ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
