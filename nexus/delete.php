<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
bdst_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !bdst_csrf_verify($_POST['csrf'] ?? null)) {
    bdst_flash('error', 'Invalid request.');
    header('Location: dashboard.php');
    exit;
}

$id = max(0, (int) ($_POST['id'] ?? 0));

try {
    $pdo   = bdst_db();
    $stmt  = $pdo->prepare("SELECT image_path FROM updates WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $post = $stmt->fetch();

    $stmt = $pdo->prepare("DELETE FROM updates WHERE id = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($post && $post['image_path']) {
        $image = __DIR__ . '/../' . $post['image_path'];
        if (is_file($image)) {
            @unlink($image);
        }
    }

    bdst_flash('success', 'Update deleted.');
} catch (Throwable $e) {
    error_log('Admin delete error: ' . $e->getMessage());
    bdst_flash('error', 'Could not delete the update: ' . $e->getMessage());
}

header('Location: dashboard.php');
exit;
