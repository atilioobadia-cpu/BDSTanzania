<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
bdst_require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid request.');
}

try {
    $stmt = bdst_db()->prepare(
        "SELECT reference_no, full_name, cv_original_name, cv_path
         FROM bds_registrations
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->execute([':id' => $id]);
    $reg = $stmt->fetch();
} catch (Throwable $e) {
    error_log('CV download error: ' . $e->getMessage());
    http_response_code(500);
    exit('Download failed.');
}

if ($reg === false || empty($reg['cv_path'])) {
    http_response_code(404);
    exit('CV not found.');
}

// Only allow plain filenames inside register/uploads (no traversal).
$fileName = basename((string) $reg['cv_path']);
$filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'register' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $fileName;

if (!is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit('CV file is missing on the server.');
}

$downloadName = trim((string) ($reg['cv_original_name'] ?? ''));
if ($downloadName === '') {
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $downloadName = $reg['reference_no'] . '_CV' . ($ext !== '' ? '.' . $ext : '');
}
$downloadName = str_replace('"', '', $downloadName);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . (string) filesize($filePath));
header('Cache-Control: must-revalidate');
header('Pragma: private');
header('Expires: 0');

readfile($filePath);
exit;
