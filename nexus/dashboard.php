<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/_sidebar.php';
bdst_require_login();

$posts = [];
$loadError = '';

try {
    $pdo   = bdst_db();
    $posts = $pdo->query(
        "SELECT id, title, slug, status, published_at, image_path
         FROM updates
         ORDER BY published_at DESC, id DESC"
    )->fetchAll();
} catch (Throwable $e) {
    error_log('Admin dashboard error: ' . $e->getMessage());
    $loadError = 'Could not load updates: ' . $e->getMessage();
}

$flash = bdst_flash_consume();

$totalUpdates = count($posts);
$publishedCount = 0;
$draftCount = 0;
foreach ($posts as $p) {
    if ($p['status'] === 'published') {
        $publishedCount++;
    } else {
        $draftCount++;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= bdst_admin_title('Dashboard') ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/images/fav.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">
<?php bdst_admin_sidebar('dashboard'); ?>

    <main class="main">
        <header class="topbar">
            <div class="topbar-inner">
                <div class="page-title-block">
                    <h1>News Updates</h1>
                    <p>Publish, edit and manage the news shown on the Updates page.</p>
                </div>
                <a href="post.php" class="btn btn-primary">+ New Update</a>
            </div>
        </header>

        <div class="page">
            <?php if ($flash): ?>
                <div class="alert alert-<?= bdst_e($flash['type']) ?>"><?= bdst_e($flash['message']) ?></div>
            <?php endif; ?>

            <?php if ($loadError !== ''): ?>
                <div class="alert alert-error"><?= bdst_e($loadError) ?></div>
            <?php endif; ?>

            <div class="stats">
                <div class="stat">
                    <span class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
                    <div>
                        <div class="stat-value"><?= $totalUpdates ?></div>
                        <div class="stat-label">Total Updates</div>
                    </div>
                </div>
                <div class="stat">
                    <span class="stat-icon stat-icon--success"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
                    <div>
                        <div class="stat-value"><?= $publishedCount ?></div>
                        <div class="stat-label">Published</div>
                    </div>
                </div>
                <div class="stat">
                    <span class="stat-icon stat-icon--muted"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg></span>
                    <div>
                        <div class="stat-value"><?= $draftCount ?></div>
                        <div class="stat-label">Drafts</div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <?php if (empty($posts)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        </div>
                        <p>No updates yet.</p>
                        <a href="post.php" class="btn btn-primary">Create your first update</a>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="th-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($posts as $post): ?>
                                    <tr>
                                        <td>
                                            <div class="cell-title">
                                                <?php
                                                $thumbRaw = (string) ($post['image_path'] ?? '');
                                                $thumbDec = json_decode($thumbRaw, true);
                                                $thumb    = is_array($thumbDec) ? (string) ($thumbDec[0] ?? '') : $thumbRaw;
                                                ?>
                                                <?php if ($thumb !== ''): ?>
                                                    <img class="cell-thumb" src="../<?= bdst_e($thumb) ?>" alt="">
                                                <?php else: ?>
                                                    <span class="cell-thumb cell-thumb--empty">BDS</span>
                                                <?php endif; ?>
                                                <span><?= bdst_e($post['title']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $post['status'] === 'published' ? 'published' : 'draft' ?>">
                                                <?= bdst_e($post['status']) ?>
                                            </span>
                                        </td>
                                        <td class="td-muted"><?= bdst_e(date('M j, Y', strtotime($post['published_at']))) ?></td>
                                        <td class="th-actions">
                                            <div class="row-actions">
                                                <a class="btn btn-ghost btn-sm" href="post.php?id=<?= (int) $post['id'] ?>">Edit</a>
                                                <a class="btn btn-ghost btn-sm" href="../update.html?slug=<?= bdst_e($post['slug']) ?>" target="_blank" rel="noopener">View</a>
                                                <form method="post" action="delete.php" onsubmit="return confirm('Delete this update permanently?');">
                                                    <input type="hidden" name="csrf" value="<?= bdst_e(bdst_csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
