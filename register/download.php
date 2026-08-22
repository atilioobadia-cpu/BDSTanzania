<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/forms.php';
require __DIR__ . '/../includes/approval-docs.php';

/**
 * Streams one approval document. Access is granted ONLY when the reference
 * number exists and its registration status is "approved" — the files on disk
 * are never exposed directly.
 */

function bdst_download_exit(int $code, string $heading, string $message): never
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<style>body{font-family:Arial,Helvetica,sans-serif;background:#f4f8f8;color:#364e52;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}'
        . '.box{background:#fff;border-radius:14px;padding:40px 46px;text-align:center;max-width:420px;box-shadow:0 10px 30px rgba(12,30,33,.08);}'
        . 'h1{font-size:19px;color:#0c1e21;margin:12px 0 8px;}p{font-size:14px;line-height:1.6;margin:0 0 18px;}'
        . 'i{font-size:38px;color:#1997C6;}a{color:#1997C6;font-weight:bold;text-decoration:none;}</style></head><body><div class="box">';
    echo '<i class="fa-solid fa-lock"></i><h1>' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<a href="check.php">&larr; Back to Check Status</a></div></body></html>';
    exit;
}

$refRaw = trim((string) ($_GET['ref'] ?? ''));
$slug   = (string) ($_GET['doc'] ?? '');

if ($refRaw === '' || !str_starts_with(strtoupper($refRaw), 'BDS-TZ-')) {
    bdst_download_exit(400, 'Invalid reference', 'The reference number provided is not valid.');
}

try {
    $stmt = bdst_db()->prepare(
        "SELECT reference_no, full_name, status FROM bds_registrations WHERE reference_no = :ref LIMIT 1"
    );
    $stmt->execute([':ref' => strtoupper($refRaw)]);
    $reg = $stmt->fetch() ?: null;
} catch (Throwable $e) {
    error_log('Document download error: ' . $e->getMessage());
    bdst_download_exit(500, 'Something went wrong', 'We could not verify your reference right now. Please try again shortly.');
}

if ($reg === null) {
    bdst_download_exit(404, 'Not found', 'No registration matches that reference number.');
}
if ((string) $reg['status'] !== 'approved') {
    bdst_download_exit(403, 'Documents not yet available', 'Membership documents become available once your application has been approved.');
}

$path = bdst_approval_doc_file($slug);
if ($path === null) {
    bdst_download_exit(404, 'Document unavailable', 'This document could not be found. Please contact the Secretariat at info@bdst.or.tz.');
}

$docs = bdst_approval_docs();
$title = (string) $docs[$slug]['title'];
$safeName = preg_replace('/[^A-Za-z0-9 \-_]/', '', $title);
if (!is_string($safeName) || trim($safeName) === '') {
    $safeName = basename($path);
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . str_replace(' ', '-', trim((string) $safeName)) . '.pdf"');
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($path);
exit;
