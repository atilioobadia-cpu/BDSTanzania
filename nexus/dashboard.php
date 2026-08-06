<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= bdst_admin_title('Dashboard') ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/images/fav.png">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="dashboard.php">
                <img src="../assets/images/logos/logo.webp" alt="BDS Tanzania" height="32">
                <span>Admin</span>
            </a>
            <nav class="topnav">
                <a href="../index.html" target="_blank" rel="noopener">View Site</a>
                <a href="post.php" class="btn btn-primary">+ New Update</a>
                <a href="logout.php" class="btn btn-ghost">Log Out</a>
            </nav>
        </div>
    </header>

    <main class="page">
        <div class="page-head">
            <div>
                <h1>News Updates</h1>
                <p>Publish, edit and manage the news shown on the Updates page.</p>
            </div>
            <a href="post.php" class="btn btn-primary">+ New Update</a>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= bdst_e($flash['type']) ?>"><?= bdst_e($flash['message']) ?></div>
        <?php endif; ?>

        <?php if ($loadError !== ''): ?>
            <div class="alert alert-error"><?= bdst_e($loadError) ?></div>
        <?php endif; ?>

        <div class="panel">
            <?php if (empty($posts)): ?>
                <div class="empty-state">
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
                                            <?php if ($post['image_path']): ?>
                                                <img src="../<?= bdst_e($post['image_path']) ?>" alt="" width="44" height="30">
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
    </main>
</body>
</html>
