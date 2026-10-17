<?php
declare(strict_types=1);

/**
 * Read-only JSON view of one registration, consumed by the preview drawer on
 * the registrations list. Requires an admin session; no CSRF token because
 * this endpoint never changes state.
 */

require __DIR__ . '/auth.php';
require __DIR__ . '/../includes/forms.php';
bdst_require_login();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid registration id.']);
    exit;
}

try {
    $stmt = bdst_db()->prepare('SELECT * FROM bds_registrations WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $reg = $stmt->fetch();
} catch (Throwable $e) {
    error_log('Registration preview error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not load the registration.']);
    exit;
}

if ($reg === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Registration not found.']);
    exit;
}

$cvPath = (string) ($reg['cv_path'] ?? '');
$cvExt  = $cvPath === '' ? '' : strtolower(pathinfo($cvPath, PATHINFO_EXTENSION));

echo json_encode([
    'id'                  => (int) $reg['id'],
    'reference'           => (string) $reg['reference_no'],
    'name'                => (string) $reg['full_name'],
    'email'               => (string) $reg['email'],
    'phone'               => (string) $reg['phone'],
    'gender'              => (string) $reg['gender'],
    'age_group'           => (string) $reg['age_group'],
    'region'              => (string) $reg['region'],
    'district'            => (string) $reg['district'],
    'professional_status' => (string) $reg['professional_status'],
    'firm_name'           => (string) ($reg['firm_name'] ?? ''),
    'experience_years'    => (string) $reg['experience_years'],
    'education'           => (string) $reg['education'],
    'clients_served'      => (string) $reg['clients_served'],
    'service_areas'       => bdst_decode_list($reg['service_areas'] ?? null),
    'industries'          => bdst_decode_list($reg['industries'] ?? null),
    'client_segments'     => bdst_decode_list($reg['client_segments'] ?? null),
    'service_nature'      => bdst_decode_list($reg['service_nature'] ?? null),
    'certifications'      => bdst_decode_list($reg['certifications'] ?? null),
    'zones'               => bdst_decode_list($reg['zones'] ?? null),
    'has_certification'   => (int) $reg['has_certification'] === 1,
    'status'              => (string) $reg['status'],
    'status_label'        => bdst_reg_status_label((string) $reg['status']),
    'submitted_at'        => (string) $reg['submitted_at'],
    'reviewed_at'         => (string) ($reg['reviewed_at'] ?? ''),
    'admin_notes'         => (string) ($reg['admin_notes'] ?? ''),
    'rejection_reason'    => (string) ($reg['rejection_reason'] ?? ''),
    'cv' => [
        'available'     => $cvPath !== '',
        'original_name' => (string) ($reg['cv_original_name'] ?? ''),
        'ext'           => $cvExt,
        'inline'        => $cvExt === 'pdf',
    ],
    'url' => 'registration.php?id=' . $id,
], JSON_UNESCAPED_UNICODE);
