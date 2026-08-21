<?php
declare(strict_types=1);

/**
 * BDS Tanzania — member registration option lists + shared helpers.
 *
 * Single source of truth for every dropdown / checkbox group on the
 * registration form, the admin editor and the public member directory.
 */

const BDST_CV_MAX_BYTES = 10485760; // 10 MB

function bdst_regions(): array
{
    return [
        'Arusha', 'Dar es Salaam', 'Dodoma', 'Geita', 'Iringa', 'Kagera',
        'Katavi', 'Kigoma', 'Kilimanjaro', 'Lindi', 'Manyara', 'Mara',
        'Mbeya', 'Mjini Magharibi', 'Morogoro', 'Mtwara', 'Mwanza',
        'Njombe', 'Pemba', 'Pwani', 'Rukwa', 'Ruvuma', 'Shinyanga',
        'Simiyu', 'Singida', 'Songwe', 'Tabora', 'Tanga', 'Unguja',
    ];
}

function bdst_age_groups(): array
{
    return ['20-30', '31-40', '41-50', '51-60', '60+'];
}

function bdst_professional_statuses(): array
{
    return [
        'Independent consultant',
        'Consulting firm owner',
        'Partner in consulting firm',
        'Employee in consulting firm',
        'Academic / Trainer',
        'Corporate professional',
        'Other',
    ];
}

function bdst_experience_levels(): array
{
    return ['0-2 years', '3-5 years', '6-10 years', '11-15 years', '16+ years'];
}

function bdst_education_levels(): array
{
    return ['Diploma', "Bachelor's Degree", "Master's Degree", 'PhD', 'Other'];
}

function bdst_service_areas(): array
{
    return [
        'Business strategy',
        'Marketing & branding',
        'Financial management',
        'Accounting & tax advisory',
        'HR Management',
        'Operations management',
        'SME Development',
        'Startup advisory',
        'Franchising',
        'Export readiness',
        'Investment readiness',
        'Business planning',
        'Digital transformation',
        'Corporate governance',
        'Innovation & product development',
    ];
}

function bdst_industries(): array
{
    return [
        'Agriculture',
        'Food Processing',
        'Manufacturing',
        'Tourism & Hospitality',
        'Retail & Trade',
        'Logistics',
        'ICT / Technology',
        'Education',
        'Healthcare',
        'Construction',
        'Financial Services',
        'NGOs / Development Sector',
    ];
}

function bdst_client_segments(): array
{
    return [
        'Startups',
        'Micro enterprises',
        'Small businesses',
        'Medium enterprises',
        'Large corporations',
        'Government',
        'NGOs / Development programs',
    ];
}

function bdst_clients_served(): array
{
    return ['1-5', '6-10', '11-20', '21-50', '50+'];
}

function bdst_clients_served_label(string $value): string
{
    return $value === '50+' ? 'More than 50 clients' : $value . ' clients';
}

function bdst_service_natures(): array
{
    return [
        'Training',
        'Consulting',
        'Coaching / Mentoring',
        'Advisory Retainers',
        'Project Implementation',
    ];
}

function bdst_certification_types(): array
{
    return [
        'Accounting (CPA, ACCA etc.)',
        'HR Certification',
        'Project Management (e.g. PMP)',
        'Marketing Certification',
        'Coaching Certification',
    ];
}

function bdst_zones(): array
{
    return [
        'Northern Zone (Tanga, Kilimanjaro, Arusha, Manyara)',
        'Eastern Zone (DSM, Pwani, Morogoro, ZNZ)',
        'Central Zone (Dodoma, Singida)',
        'Lake Zone (Kagera, Geita, Mwanza, Mara, Simiyu and Shinyanga)',
        'Southern Highlands (Iringa, Njombe, Mbeya, Songwe, Rukwa)',
        'Southern Zone (Ruvuma, Mtwara, Lindi)',
        'Western Zone (Tabora, Kigoma, Katavi)',
    ];
}

function bdst_registration_statuses(): array
{
    return ['pending', 'reviewed', 'approved', 'rejected'];
}

/**
 * Build the public-facing reference number from the row id.
 */
function bdst_reg_reference(int $id): string
{
    return sprintf('BDS-TZ-%s-%04d', date('Y'), $id);
}

/**
 * Encode a multi-select group for storage.
 */
function bdst_json_list(array $values): string
{
    $clean = array_values(array_filter(array_map(
        static fn ($v): string => trim((string) $v),
        $values
    ), static fn ($v): bool => $v !== ''));

    return json_encode($clean, JSON_UNESCAPED_UNICODE) ?: '[]';
}

/**
 * Decode a stored multi-select group.
 */
function bdst_decode_list(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Validate + store an uploaded CV. Returns [storedName, originalName].
 *
 * @throws RuntimeException when the upload is missing or invalid.
 */
function bdst_store_cv(array $file, string $referenceNo): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('CV upload failed. Please try again.');
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Please attach your CV (PDF or Word document).');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The CV could not be uploaded. Please try again.');
    }
    if ($file['size'] < 1 || $file['size'] > BDST_CV_MAX_BYTES) {
        throw new RuntimeException('The CV must be between 1 byte and 10 MB.');
    }

    $original = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
        throw new RuntimeException('Only PDF, DOC or DOCX files are accepted.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    if (!in_array($mime, $allowedMimes, true)) {
        throw new RuntimeException('The uploaded file is not a valid PDF or Word document.');
    }

    $dir = __DIR__ . '/../register/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Storage folder is not available. Contact the Secretariat.');
    }

    $safeRef   = preg_replace('/[^A-Za-z0-9\-]/', '', $referenceNo) ?: 'BDS';
    $storedName = $safeRef . '_' . bin2hex(random_bytes(6)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $storedName)) {
        throw new RuntimeException('The CV could not be saved. Please try again.');
    }

    return [$storedName, $original];
}

/**
 * Read + validate a registration submission from any array (public form
 * or admin edit). Returns ['data' => array, 'errors' => string[]].
 *
 * $allowCustom lets stored "Other" values (already collapsed) pass
 * validation — used by the admin edit form.
 */
function bdst_collect_registration(array $src, array $files = [], bool $allowCustom = false): array
{
    $errors = [];
    $get = static fn (string $key): string => trim((string) ($src[$key] ?? ''));

    $data = [
        'full_name'               => $get('full_name'),
        'gender'                  => $get('gender'),
        'age_group'               => $get('age_group'),
        'phone'                   => $get('phone'),
        'email'                   => strtolower($get('email')),
        'region'                  => $get('region'),
        'district'                => $get('district'),
        'professional_status'     => $get('professional_status'),
        'professional_status_other' => $get('professional_status_other'),
        'firm_name'               => $get('firm_name'),
        'experience_years'        => $get('experience_years'),
        'education'               => $get('education'),
        'education_other'         => $get('education_other'),
        'service_areas'           => bdst_json_list((array) ($src['service_areas'] ?? [])),
        'service_areas_other'     => $get('service_areas_other'),
        'industries'              => bdst_json_list((array) ($src['industries'] ?? [])),
        'industries_other'        => $get('industries_other'),
        'client_segments'         => bdst_json_list((array) ($src['client_segments'] ?? [])),
        'client_segments_other'   => $get('client_segments_other'),
        'clients_served'          => $get('clients_served'),
        'service_nature'          => bdst_json_list((array) ($src['service_nature'] ?? [])),
        'service_nature_other'    => $get('service_nature_other'),
        'has_certification'       => $get('has_certification') === 'yes' ? 1 : 0,
        'certifications'          => bdst_json_list((array) ($src['certifications'] ?? [])),
        'certifications_other'    => $get('certifications_other'),
        'zones'                   => bdst_json_list((array) ($src['zones'] ?? [])),
        'declaration'             => isset($src['declaration']) ? 1 : 0,
    ];

    // Validate raw single-selects against the allowed lists (before collapsing).
    if ($data['professional_status'] === '' || ($data['professional_status'] !== 'Other' && !$allowCustom && !in_array($data['professional_status'], bdst_professional_statuses(), true))) {
        $errors[] = 'Please select your current professional status.';
    }
    if ($data['education'] === '' || ($data['education'] !== 'Other' && !$allowCustom && !in_array($data['education'], bdst_education_levels(), true))) {
        $errors[] = 'Please select your highest education level.';
    }

    // Single-select fields: "Other" collapses into the stored value itself.
    foreach (
        [
            ['professional_status', 'professional_status_other'],
            ['education', 'education_other'],
        ] as [$field, $otherField]
    ) {
        if ($data[$field] === 'Other') {
            if ($data[$otherField] === '') {
                $errors[] = 'Please specify the "Other" value you selected.';
            } else {
                $data[$field] = mb_substr($data[$otherField], 0, 100);
            }
        }
        $data[$otherField] = '';
    }

    // Multi-select fields: "Other" is replaced by the typed value inside the list.
    foreach (
        [
            ['service_areas', 'service_areas_other', bdst_service_areas()],
            ['industries', 'industries_other', bdst_industries()],
            ['client_segments', 'client_segments_other', bdst_client_segments()],
            ['service_nature', 'service_nature_other', bdst_service_natures()],
            ['certifications', 'certifications_other', bdst_certification_types()],
        ] as [$field, $otherField, $allowed]
    ) {
        $list = bdst_decode_list($data[$field]);
        if (in_array('Other', $list, true)) {
            if ($data[$otherField] === '') {
                $errors[] = 'Please specify the "Other" value you selected.';
            } else {
                $list = array_values(array_diff($list, ['Other']));
                $list[] = mb_substr($data[$otherField], 0, 100);
            }
        } elseif ($data[$otherField] !== '') {
            $list[] = mb_substr($data[$otherField], 0, 100);
        } elseif (!$allowCustom) {
            foreach ($list as $item) {
                if (!in_array($item, $allowed, true)) {
                    $errors[] = 'Invalid selection detected.';
                    break;
                }
            }
        }
        $data[$field]         = bdst_json_list($list);
        $data[$otherField]    = '';
    }

    if ($data['full_name'] === '' || mb_strlen($data['full_name']) > 150) {
        $errors[] = 'Please enter your full name.';
    }
    if (!in_array($data['gender'], ['Male', 'Female'], true)) {
        $errors[] = 'Please select your gender.';
    }
    if (!in_array($data['age_group'], bdst_age_groups(), true)) {
        $errors[] = 'Please select your age group.';
    }
    if ($data['phone'] === '' || !preg_match('/^[0-9+\-\s()]{7,20}$/', $data['phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($data['email']) > 150) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!in_array($data['region'], bdst_regions(), true)) {
        $errors[] = 'Please select your region.';
    }
    if ($data['district'] === '' || mb_strlen($data['district']) > 80) {
        $errors[] = 'Please enter your district.';
    }
    if (mb_strlen($data['firm_name']) > 150) {
        $errors[] = 'Firm name is too long.';
    }
    if (!in_array($data['experience_years'], bdst_experience_levels(), true)) {
        $errors[] = 'Please select your years of experience.';
    }
    foreach (
        [
            ['service_areas', 'primary BDS service areas'],
            ['industries', 'industries you have served'],
            ['client_segments', 'client segments'],
            ['service_nature', 'nature of services'],
            ['zones', 'zones where you can deliver services'],
        ] as [$field, $label]
    ) {
        if (count(bdst_decode_list($data[$field])) === 0) {
            $errors[] = "Please select at least one option for: {$label}.";
        }
    }
    if (!in_array($data['clients_served'], bdst_clients_served(), true)) {
        $errors[] = 'Please select how many clients you have served.';
    }
    if (!isset($src['has_certification']) || !in_array($get('has_certification'), ['yes', 'no'], true)) {
        $errors[] = 'Please indicate whether you hold professional certifications.';
    }
    if ($data['has_certification'] === 1 && count(bdst_decode_list($data['certifications'])) === 0) {
        $errors[] = 'Please select at least one certification type.';
    }
    if ($data['has_certification'] === 0) {
        $data['certifications'] = '[]';
        $data['certifications_other'] = '';
    }
    if ($data['declaration'] !== 1) {
        $errors[] = 'You must accept the declaration to submit your registration.';
    }

    return ['data' => $data, 'errors' => $errors];
}

function bdst_reg_status_label(string $status): string
{
    return match ($status) {
        'approved' => 'Approved',
        'rejected' => 'Not Approved',
        'reviewed' => 'Under Review',
        default    => 'Pending Review',
    };
}
