<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../includes/forms.php';
bdst_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !bdst_csrf_verify($_POST['csrf'] ?? null)) {
    bdst_flash('error', 'Security token expired. Please try again.');
    header('Location: registrations.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    bdst_flash('error', 'Invalid registration.');
    header('Location: registrations.php');
    exit;
}

try {
    $pdo  = bdst_db();
    $stmt = $pdo->prepare("SELECT reference_no, cv_path FROM bds_registrations WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $reg = $stmt->fetch();

    if ($reg === false) {
        bdst_flash('error', 'Registration not found.');
    } else {
        if (!empty($reg['cv_path'])) {
            $cvFile = basename((string) $reg['cv_path']);
            $cvPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'register' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $cvFile;
            if (is_file($cvPath)) {
                @unlink($cvPath);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM bds_registrations WHERE id = :id");
        $stmt->execute([':id' => $id]);

        bdst_flash('success', 'Registration ' . $reg['reference_no'] . ' deleted.');
    }
} catch (Throwable $e) {
    error_log('Registration delete error: ' . $e->getMessage());
    bdst_flash('error', 'Could not delete the registration. Please try again.');
}

header('Location: registrations.php');
exit;
