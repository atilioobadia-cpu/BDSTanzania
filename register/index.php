<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/forms.php';
require __DIR__ . '/_layout.php';

if (empty($_SESSION['reg_csrf'])) {
    $_SESSION['reg_csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$old = [
    'full_name' => '', 'gender' => '', 'age_group' => '', 'phone' => '', 'email' => '',
    'region' => '', 'district' => '', 'professional_status' => '', 'professional_status_other' => '',
    'firm_name' => '', 'experience_years' => '', 'education' => '', 'education_other' => '',
    'service_areas' => [], 'service_areas_other' => '', 'industries' => [], 'industries_other' => '',
    'client_segments' => '', 'client_segments_other' => '', 'clients_served' => '',
    'service_nature' => [], 'service_nature_other' => '', 'has_certification' => '',
    'certifications' => [], 'certifications_other' => '', 'zones' => [], 'declaration' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot: real users never fill this hidden field (bots do).
    // Humans who trip it (e.g. aggressive autofill) get the normal error
    // path so they are never silently bounced to another page.
    if (!empty($_POST['hc_token'])) {
        $errors[] = 'Your submission could not be verified. Please clear the form and try again.';
    }

    if (!hash_equals($_SESSION['reg_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $errors[] = 'Your session expired. Please submit the form again.';
    }

    // Time trap: submissions faster than 2 seconds are bots.
    $elapsed = time() - (int) ($_POST['form_time'] ?? 0);
    if ($elapsed > 0 && $elapsed < 2) {
        $errors[] = 'Please take a moment to complete the form before submitting.';
    }

    if ($_POST === []) {
        $errors[] = 'The upload exceeded the server limit. Please attach a smaller CV (max 10 MB) and try again.';
    } else {
        ['data' => $data, 'errors' => $collected] = bdst_collect_registration($_POST, $_FILES);
        $errors = array_merge($errors, $collected);

        foreach ($old as $k => $default) {
            if (isset($_POST[$k])) {
                $old[$k] = is_array($_POST[$k]) ? array_map('strval', $_POST[$k]) : (string) $_POST[$k];
            }
        }
        $old['declaration'] = isset($_POST['declaration']) ? 1 : 0;

        if (!$errors) {
            try {
                $pdo = bdst_db();

                // One application per email address.
                $dupe = $pdo->prepare("SELECT reference_no FROM bds_registrations WHERE email = :email LIMIT 1");
                $dupe->execute([':email' => strtolower($data['email'])]);
                if ($dupe->fetch()) {
                    $errors['email'] = 'This email address is already registered. Use the "Check Registration Status" '
                        . 'button above to follow your existing application, or contact info@bdst.or.tz.';
                }

                if (!$errors) {
                $stmt = $pdo->prepare(
                    "INSERT INTO bds_registrations
                        (full_name, gender, age_group, phone, email, region, district,
                         professional_status, firm_name, experience_years, education,
                         service_areas, industries, client_segments, clients_served,
                         service_nature, has_certification, certifications, zones,
                         declaration, cv_path, cv_original_name, status, reviewed_at)
                     VALUES
                        (:full_name, :gender, :age_group, :phone, :email, :region, :district,
                         :professional_status, :firm_name, :experience_years, :education,
                         :service_areas, :industries, :client_segments, :clients_served,
                         :service_nature, :has_certification, :certifications, :zones,
                         :declaration, '', '', 'approved', NOW())"
                );
                $stmt->execute([
                    ':full_name'           => $data['full_name'],
                    ':gender'              => $data['gender'],
                    ':age_group'           => $data['age_group'],
                    ':phone'               => $data['phone'],
                    ':email'               => $data['email'],
                    ':region'              => $data['region'],
                    ':district'            => $data['district'],
                    ':professional_status' => $data['professional_status'],
                    ':firm_name'           => $data['firm_name'],
                    ':experience_years'    => $data['experience_years'],
                    ':education'           => $data['education'],
                    ':service_areas'       => $data['service_areas'],
                    ':industries'          => $data['industries'],
                    ':client_segments'     => $data['client_segments'],
                    ':clients_served'      => $data['clients_served'],
                    ':service_nature'      => $data['service_nature'],
                    ':has_certification'   => (int) $data['has_certification'],
                    ':certifications'      => $data['certifications'],
                    ':zones'               => $data['zones'],
                    ':declaration'         => (int) $data['declaration'],
                ]);
                $id  = (int) $pdo->lastInsertId();
                $ref = bdst_reg_reference($id);

                try {
                    [$storedName, $originalName] = bdst_store_cv($_FILES['cv'] ?? [], $ref);
                    $upd = $pdo->prepare(
                        "UPDATE bds_registrations SET reference_no = :ref, cv_path = :cv, cv_original_name = :cvo WHERE id = :id"
                    );
                    $upd->execute([':ref' => $ref, ':cv' => $storedName, ':cvo' => $originalName, ':id' => $id]);
                } catch (RuntimeException $e) {
                    $pdo->prepare("DELETE FROM bds_registrations WHERE id = :id")->execute([':id' => $id]);
                    $errors['cv'] = $e->getMessage();
                }

                if (!$errors) {
                    require_once __DIR__ . '/../includes/reg-mail.php';
                    try {
                        bdst_send_registration_emails($pdo, (int) $id);
                    } catch (Throwable $mailError) {
                        error_log('Registration mail error: ' . $mailError->getMessage());
                    }

                    // Auto-approval: send the membership documents pack immediately.
                    try {
                        bdst_send_approval_email($pdo, (int) $id);
                    } catch (Throwable $approvalError) {
                        error_log('Approval mail error: ' . $approvalError->getMessage());
                    }

                    session_regenerate_id(true);
                    $_SESSION['reg_csrf'] = bin2hex(random_bytes(32));
                    header('Location: success.php?ref=' . urlencode($ref));
                    exit;
                }
                }
            } catch (Throwable $dbError) {
                error_log('Registration save error: ' . $dbError->getMessage());
                $errors[] = 'We could not save your registration right now. Please try again shortly.';
            }
        }
    }
}

function reg_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function reg_old(array $old, string $key): string
{
    return reg_e(is_array($old[$key] ?? null) ? '' : (string) ($old[$key] ?? ''));
}

function reg_selected(array $old, string $key, string $value): string
{
    return (is_array($old[$key] ?? null) ? '' : (string) ($old[$key] ?? '')) === $value ? ' selected' : '';
}

function reg_pill_checked(array $old, string $key, string $value): string
{
    return (is_array($old[$key] ?? null) ? '' : (string) ($old[$key] ?? '')) === $value ? ' checked' : '';
}

function reg_chip_checked(array $old, string $key, string $value): string
{
    return in_array($value, (array) ($old[$key] ?? []), true) ? ' checked' : '';
}

/** Inline error text for one field (rendered under the control). */
function reg_field_error(array $errors, string $key): string
{
    return isset($errors[$key])
        ? '<small class="field-error">' . reg_e($errors[$key]) . '</small>'
        : '';
}

/** Class suffix marking a control that failed validation. */
function reg_err_class(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' has-error' : '';
}

/**
 * Render one styled multi-select chip.
 */
function reg_chip(string $name, string $value, string $checked, string $reveal = ''): void
{
    echo '<label class="reg-chip">';
    echo '<input type="checkbox" name="' . reg_e($name) . '[]" value="' . reg_e($value) . '"' . $checked;
    if ($reveal !== '') {
        echo ' data-reveal="' . reg_e($reveal) . '"';
    }
    echo '><span>' . reg_e($value) . '</span></label>';
}

/**
 * Render one single-choice pill (radio).
 */
function reg_choice(string $name, string $value, string $checked, array $old, string $key, string $reveal = ''): void
{
    echo '<label class="reg-chip reg-choice">';
    echo '<input type="radio" name="' . reg_e($name) . '" value="' . reg_e($value) . '"' . $checked;
    if ($reveal !== '') {
        echo ' data-reveal="' . reg_e($reveal) . '"';
    } else {
        echo ' data-clear="' . reg_e($name) . '-other"';
    }
        echo '><span>' . reg_e($value) . '</span></label>';
}

/** Section heading inside the single card. */
function reg_section(string $num, string $title, string $sub = ''): void
{
    echo '<div class="reg-sec">';
    echo '<span class="reg-sec-num">' . reg_e($num) . '</span>';
    echo '<div class="reg-sec-text"><h3>' . reg_e($title) . '</h3>';
    if ($sub !== '') {
        echo '<p>' . reg_e($sub) . '</p>';
    }
    echo '</div></div>';
}

$metaDescription = 'Register as a member of BDS Tanzania - the national association of business development service providers. Submit your professional profile, expertise and CV online.';
ob_start();
?>
<style>
    /* page-specific fine-tuning lives in register.css */
</style>
<?php
$extraHead = ob_get_clean();

bdst_reg_page_start('BDS Tanzania Member Registration | Apply Online', $metaDescription, 'Member Registration', $extraHead);
?>

<!-- Registration -->
<section class="reg-section">
    <div class="container">

        <div class="reg-intro">
            <h1>BDS Tanzania Member Registration Form</h1>
            <p>
                Thank you for your interest in joining BDS Tanzania, a fully registered association for business
                development service professionals in Tanzania. This form is designed to collect essential professional
                information about prospective members, including their areas of expertise, experience, sector
                specialization and geographic coverage. The information provided will help the association build a
                reliable and structured database of business development service providers, support networking among
                professionals and facilitate the effective deployment of members in various programs, partnerships
                and advisory initiatives.
            </p>
            <p>
                Please be assured that all personal and professional information collected through this form will be
                handled with the highest level of confidentiality and professionalism. The data will be used strictly
                for legitimate association purposes such as member profiling, coordination of activities, professional
                engagement and communication within the BDS Tanzania network. The association is committed to ethical
                data management and will not share or use your information for any unauthorized or unrelated purposes.
            </p>
        </div>

        <?php if ($errors !== []): ?>
            <div class="reg-alert reg-alert-error" role="alert">
                <strong><i class="fa-solid fa-circle-exclamation"></i> Please correct the following:</strong>
                <ul>
                    <?php foreach (array_unique($errors) as $error): ?>
                        <li><?= reg_e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form id="regForm" class="reg-form" method="post" action="index.php" enctype="multipart/form-data" autocomplete="on" novalidate>
            <input type="hidden" name="csrf" value="<?= reg_e($_SESSION['reg_csrf']) ?>">
            <input type="hidden" name="form_time" value="<?= time() ?>">
            <input type="text" name="hc_token" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="reg-hp">

            <div class="reg-form-head">
                <div>
                    <h2>Registration Form</h2>
                    <p>Fields marked <span class="reg-req">*</span> are required.</p>
                </div>
                <a class="reg-btn reg-btn-outline reg-head-status" href="check.php">
                    <i class="fa-solid fa-magnifying-glass"></i> Check Registration Status
                </a>
            </div>

            <?php reg_section('01', 'Personal Information', 'Tell us who you are and how we can reach you.'); ?>
            <div class="row">
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="full_name">Full Name <span class="reg-req">*</span></label>
                        <input class="reg-control<?= reg_err_class($errors, 'full_name') ?>" type="text" id="full_name" name="full_name" maxlength="150"
                               placeholder="e.g. Amina Juma Mwakalinga" required
                               value="<?= reg_old($old, 'full_name') ?>">
                        <?= reg_field_error($errors, 'full_name') ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="phone">Phone Number <span class="reg-req">*</span></label>
                        <input class="reg-control<?= reg_err_class($errors, 'phone') ?>" type="tel" id="phone" name="phone" maxlength="20"
                               placeholder="e.g. +255 712 345 678" required
                               value="<?= reg_old($old, 'phone') ?>">
                        <?= reg_field_error($errors, 'phone') ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="email">Email Address <span class="reg-req">*</span></label>
                        <input class="reg-control<?= reg_err_class($errors, 'email') ?>" type="email" id="email" name="email" maxlength="150"
                               placeholder="you@example.com" required
                               value="<?= reg_old($old, 'email') ?>">
                        <small class="reg-hint">Your registration confirmation will be sent here.</small>
                        <?= reg_field_error($errors, 'email') ?>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="reg-field<?= reg_err_class($errors, 'gender') ?>">
                        <span class="reg-label">
                            <span class="reg-label-main">Gender <span class="reg-req">*</span></span>
                        </span>
                        <div class="reg-chip-row">
                            <label class="reg-chip reg-choice"><input type="radio" name="gender" value="Male"<?= reg_pill_checked($old, 'gender', 'Male') ?> required><span><i class="fa-solid fa-mars"></i> Male</span></label>
                            <label class="reg-chip reg-choice"><input type="radio" name="gender" value="Female"<?= reg_pill_checked($old, 'gender', 'Female') ?>><span><i class="fa-solid fa-venus"></i> Female</span></label>
                        </div>
                        <?= reg_field_error($errors, 'gender') ?>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="reg-field">
                        <label class="reg-label" for="age_group">
                            <span class="reg-label-main">Age Group <span class="reg-req">*</span></span>
                        </label>
                        <select class="reg-control<?= reg_err_class($errors, 'age_group') ?>" id="age_group" name="age_group" required>
                            <option value="">Select...</option>
                            <?php foreach (bdst_age_groups() as $opt): ?>
                                <option value="<?= reg_e($opt) ?>"<?= reg_selected($old, 'age_group', $opt) ?>><?= reg_e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= reg_field_error($errors, 'age_group') ?>
                    </div>
                </div>
            </div>

            <?php reg_section('02', 'Location', 'Where you are based in Tanzania.'); ?>
            <div class="row">
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="region">
                            <span class="reg-label-main">Region <span class="reg-req">*</span></span>
                        </label>
                        <select class="reg-control<?= reg_err_class($errors, 'region') ?>" id="region" name="region" required>
                            <option value="">Select region...</option>
                            <?php foreach (bdst_regions() as $opt): ?>
                                <option value="<?= reg_e($opt) ?>"<?= reg_selected($old, 'region', $opt) ?>><?= reg_e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= reg_field_error($errors, 'region') ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="district">District <span class="reg-req">*</span></label>
                        <?php $oldRegion = bdst_normalize_region((string) $old['region']) ?? (string) $old['region']; $districtOptions = bdst_districts_by_region($oldRegion); ?>
                        <select class="reg-control<?= reg_err_class($errors, 'district') ?>" id="district" name="district" required<?= $districtOptions === [] ? ' disabled' : '' ?>>
                            <option value=""><?= $districtOptions === [] ? 'Select region first...' : 'Select district...' ?></option>
                            <?php foreach ($districtOptions as $opt): ?>
                                <option value="<?= reg_e($opt) ?>"<?= reg_selected($old, 'district', $opt) ?>><?= reg_e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= reg_field_error($errors, 'district') ?>
                    </div>
                </div>
            </div>

            <?php reg_section('03', 'Professional Background', 'Your current professional standing and experience.'); ?>
            <div class="row">
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="professional_status">
                            <span class="reg-label-main">Current Professional Status <span class="reg-req">*</span></span>
                        </label>
                        <select class="reg-control<?= reg_err_class($errors, 'professional_status') ?>" id="professional_status" name="professional_status"
                                data-other-target="wrap-professional_status_other" required>
                            <option value="">Select status...</option>
                            <?php foreach (bdst_professional_statuses() as $opt): ?>
                                <option value="<?= reg_e($opt) ?>"<?= reg_selected($old, 'professional_status', $opt) ?>><?= reg_e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= reg_field_error($errors, 'professional_status') ?>
                    </div>
                    <div class="reg-other-wrap" id="wrap-professional_status_other">
                        <label class="reg-label" for="professional_status_other">Specify Your Status <span class="reg-req">*</span></label>
                        <input class="reg-control<?= reg_err_class($errors, 'professional_status_other') ?>" type="text" id="professional_status_other"
                               name="professional_status_other" maxlength="100"
                               placeholder="Describe your professional status"
                               value="<?= reg_old($old, 'professional_status_other') ?>">
                        <?= reg_field_error($errors, 'professional_status_other') ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="firm_name">
                            <span class="reg-label-main">Firm / Organization Name <span class="reg-opt">(if you operate within a firm)</span></span>
                        </label>
                        <input class="reg-control" type="text" id="firm_name" name="firm_name" maxlength="150"
                               placeholder="e.g. Bright Advisory Ltd"
                               value="<?= reg_old($old, 'firm_name') ?>">
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="experience_years">
                            <span class="reg-label-main">Years of Experience in Business Advisory / Consulting <span class="reg-req">*</span></span>
                        </label>
                        <select class="reg-control<?= reg_err_class($errors, 'experience_years') ?>" id="experience_years" name="experience_years" required>
                            <option value="">Select...</option>
                            <?php foreach (bdst_experience_levels() as $opt): ?>
                                <option value="<?= reg_e($opt) ?>"<?= reg_selected($old, 'experience_years', $opt) ?>><?= reg_e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= reg_field_error($errors, 'experience_years') ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="clients_served">
                            <span class="reg-label-main">Clients Served in the Past 3 Years <span class="reg-req">*</span></span>
                        </label>
                        <select class="reg-control<?= reg_err_class($errors, 'clients_served') ?>" id="clients_served" name="clients_served" required>
                            <option value="">Select...</option>
                            <?php foreach (bdst_clients_served() as $opt): ?>
                                <option value="<?= reg_e($opt) ?>"<?= reg_selected($old, 'clients_served', $opt) ?>><?= reg_e(bdst_clients_served_label($opt)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= reg_field_error($errors, 'clients_served') ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="education">
                            <span class="reg-label-main">Highest Education Level <span class="reg-req">*</span></span>
                        </label>
                        <select class="reg-control<?= reg_err_class($errors, 'education') ?>" id="education" name="education"
                                data-other-target="wrap-education_other" required>
                            <option value="">Select...</option>
                            <?php foreach (bdst_education_levels() as $opt): ?>
                                <option value="<?= reg_e($opt) ?>"<?= reg_selected($old, 'education', $opt) ?>><?= reg_e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= reg_field_error($errors, 'education') ?>
                    </div>
                    <div class="reg-other-wrap" id="wrap-education_other">
                        <label class="reg-label" for="education_other">Specify Your Education <span class="reg-req">*</span></label>
                        <input class="reg-control<?= reg_err_class($errors, 'education_other') ?>" type="text" id="education_other"
                               name="education_other" maxlength="100"
                               placeholder="Describe your highest qualification"
                               value="<?= reg_old($old, 'education_other') ?>">
                        <?= reg_field_error($errors, 'education_other') ?>
                    </div>
                </div>
            </div>

            <?php reg_section('04', 'Expertise & Services', 'Select all options that apply to you.'); ?>
            <div class="reg-field<?= reg_err_class($errors, 'service_areas') ?>">
                <span class="reg-label">
                    <span class="reg-label-main">Primary BDS Service Areas <span class="reg-req">*</span></span>
                </span>
                <div class="reg-chip-grid">
                    <?php foreach (bdst_service_areas() as $opt): ?>
                        <?php reg_chip('service_areas', $opt, reg_chip_checked($old, 'service_areas', $opt)); ?>
                    <?php endforeach; ?>
                    <?php reg_chip('service_areas', 'Other', reg_chip_checked($old, 'service_areas', 'Other'), 'wrap-service_areas_other'); ?>
                </div>
                <div class="reg-other-wrap" id="wrap-service_areas_other">
                    <input class="reg-control<?= reg_err_class($errors, 'service_areas_other') ?>" type="text" name="service_areas_other" maxlength="100"
                           placeholder="Specify other service areas"
                           value="<?= reg_old($old, 'service_areas_other') ?>">
                    <?= reg_field_error($errors, 'service_areas_other') ?>
                </div>
                <?= reg_field_error($errors, 'service_areas') ?>
            </div>

            <div class="reg-field<?= reg_err_class($errors, 'industries') ?>">
                <span class="reg-label">
                    <span class="reg-label-main">Industries You Have Experience Serving <span class="reg-req">*</span></span>
                </span>
                <div class="reg-chip-grid">
                    <?php foreach (bdst_industries() as $opt): ?>
                        <?php reg_chip('industries', $opt, reg_chip_checked($old, 'industries', $opt)); ?>
                    <?php endforeach; ?>
                    <?php reg_chip('industries', 'Other', reg_chip_checked($old, 'industries', 'Other'), 'wrap-industries_other'); ?>
                </div>
                <div class="reg-other-wrap" id="wrap-industries_other">
                    <input class="reg-control<?= reg_err_class($errors, 'industries_other') ?>" type="text" name="industries_other" maxlength="100"
                           placeholder="Specify other industries"
                           value="<?= reg_old($old, 'industries_other') ?>">
                    <?= reg_field_error($errors, 'industries_other') ?>
                </div>
                <?= reg_field_error($errors, 'industries') ?>
            </div>

            <div class="reg-field<?= reg_err_class($errors, 'client_segments') ?>">
                <span class="reg-label">
                    <span class="reg-label-main">Client Segment You Mostly Serve <span class="reg-req">*</span></span>
                </span>
                <div class="reg-chip-grid">
                    <?php foreach (bdst_client_segments() as $opt): ?>
                        <?php reg_choice('client_segments', $opt, reg_pill_checked($old, 'client_segments', $opt), $old, 'client_segments'); ?>
                    <?php endforeach; ?>
                    <?php reg_choice('client_segments', 'Other', reg_pill_checked($old, 'client_segments', 'Other'), $old, 'client_segments', 'wrap-client_segments_other'); ?>
                </div>
                <div class="reg-other-wrap" id="wrap-client_segments_other">
                    <input class="reg-control<?= reg_err_class($errors, 'client_segments_other') ?>" type="text" name="client_segments_other" maxlength="100"
                           placeholder="Describe your client segment"
                           value="<?= reg_old($old, 'client_segments_other') ?>">
                    <?= reg_field_error($errors, 'client_segments_other') ?>
                </div>
                <?= reg_field_error($errors, 'client_segments') ?>
            </div>

            <div class="reg-field<?= reg_err_class($errors, 'service_nature') ?>">
                <span class="reg-label">
                    <span class="reg-label-main">Nature of Services You Mostly Deliver <span class="reg-req">*</span></span>
                </span>
                <div class="reg-chip-grid">
                    <?php foreach (bdst_service_natures() as $opt): ?>
                        <?php reg_chip('service_nature', $opt, reg_chip_checked($old, 'service_nature', $opt)); ?>
                    <?php endforeach; ?>
                    <?php reg_chip('service_nature', 'Other', reg_chip_checked($old, 'service_nature', 'Other'), 'wrap-service_nature_other'); ?>
                </div>
                <div class="reg-other-wrap" id="wrap-service_nature_other">
                    <input class="reg-control<?= reg_err_class($errors, 'service_nature_other') ?>" type="text" name="service_nature_other" maxlength="100"
                           placeholder="Specify other services"
                           value="<?= reg_old($old, 'service_nature_other') ?>">
                    <?= reg_field_error($errors, 'service_nature_other') ?>
                </div>
                <?= reg_field_error($errors, 'service_nature') ?>
            </div>

            <?php reg_section('05', 'Professional Certifications', 'Formal credentials that strengthen your professional profile.'); ?>
            <div class="reg-field<?= reg_err_class($errors, 'has_certification') ?>">
                <span class="reg-label">
                    <span class="reg-label-main">Do You Hold Any Professional Certifications? <span class="reg-req">*</span></span>
                </span>
                <div class="reg-chip-row">
                    <label class="reg-chip reg-choice">
                        <input type="radio" name="has_certification" value="yes"<?= reg_pill_checked($old, 'has_certification', 'yes') ?> data-cert-toggle required>
                        <span><i class="fa-solid fa-circle-check"></i> Yes</span>
                    </label>
                    <label class="reg-chip reg-choice">
                        <input type="radio" name="has_certification" value="no"<?= reg_pill_checked($old, 'has_certification', 'no') ?> data-cert-toggle>
                        <span><i class="fa-solid fa-circle-xmark"></i> No</span>
                    </label>
                </div>
                <?= reg_field_error($errors, 'has_certification') ?>
            </div>
            <div id="cert-block" class="reg-cert-block">
                <div class="reg-field<?= reg_err_class($errors, 'certifications') ?>">
                    <span class="reg-label">
                        <span class="reg-label-main">Type of Certification <span class="reg-req">*</span></span>
                    </span>
                    <div class="reg-chip-grid">
                        <?php foreach (bdst_certification_types() as $opt): ?>
                            <?php reg_chip('certifications', $opt, reg_chip_checked($old, 'certifications', $opt)); ?>
                        <?php endforeach; ?>
                        <?php reg_chip('certifications', 'Other', reg_chip_checked($old, 'certifications', 'Other'), 'wrap-certifications_other'); ?>
                    </div>
                    <div class="reg-other-wrap" id="wrap-certifications_other">
                        <input class="reg-control<?= reg_err_class($errors, 'certifications_other') ?>" type="text" name="certifications_other" maxlength="100"
                               placeholder="Specify other certification"
                               value="<?= reg_old($old, 'certifications_other') ?>">
                        <?= reg_field_error($errors, 'certifications_other') ?>
                    </div>
                    <?= reg_field_error($errors, 'certifications') ?>
                </div>
            </div>

<?php reg_section('06', 'Geographic Coverage', 'Zones where you can deliver services.'); ?>
            <div class="reg-field<?= reg_err_class($errors, 'zones') ?>">
                <span class="reg-label">
                    <span class="reg-label-main">Zones Where You Can Deliver Services <span class="reg-req">*</span></span>
                </span>
                <div class="reg-chip-grid">
                    <?php foreach (bdst_zones() as $opt): ?>
                        <?php reg_chip('zones', $opt, reg_chip_checked($old, 'zones', $opt)); ?>
                    <?php endforeach; ?>
                </div>
                <?= reg_field_error($errors, 'zones') ?>
            </div>

            <?php reg_section('07', 'Declaration & CV', 'Final step before submission.'); ?>
            <div class="reg-field<?= reg_err_class($errors, 'declaration') ?>">
                <label class="reg-declare">
                    <input type="checkbox" name="declaration" value="1"<?= $old['declaration'] ? ' checked' : '' ?> required>
                    <span>I hereby declare that the information provided in this form is true and correct to the best
                        of my knowledge, and I commit to abide by the code of conduct and professional standards of
                        BDS Tanzania. <span class="reg-req">*</span></span>
                </label>
                <?= reg_field_error($errors, 'declaration') ?>
            </div>

            <div class="reg-field<?= reg_err_class($errors, 'cv') ?>">
                <span class="reg-label">Upload Your CV <span class="reg-req">*</span></span>
                <label class="reg-file-box" id="cvBox">
                    <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx" required>
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <strong>Click to upload or drag your CV here</strong>
                    <small>PDF or Word document &middot; Maximum size 10 MB</small>
                    <span class="reg-file-name" id="cvName"></span>
                </label>
                <?= reg_field_error($errors, 'cv') ?>
            </div>

            <div class="reg-submit-row">
                <button type="submit" class="reg-submit-btn" id="submitBtn">
                    <span>Submit Registration</span>
                    <i class="tji-arrow-right-long"></i>
                </button>
                <p class="reg-note">
                    After submitting you will receive a confirmation email with your unique reference number, which you
                    can use to check your registration status at any time.
                </p>
            </div>
        </form>
    </div>
<?php /* Official districts per region, for the region-filtered District dropdown. */ ?>
<script type="application/json" id="bdst-districts-data"><?= json_encode(bdst_official_districts(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
(function () {
    // Dropdown "Other" reveals
    document.querySelectorAll('select[data-other-target]').forEach(function (sel) {
        var target = document.getElementById(sel.getAttribute('data-other-target'));
        if (!target) return;
        var apply = function () {
            target.classList.toggle('is-visible', sel.value === 'Other');
        };
        sel.addEventListener('change', apply);
        apply();
    });

    // Checkbox / radio "Other" reveals
    document.querySelectorAll('input[data-reveal]').forEach(function (box) {
        var target = document.getElementById(box.getAttribute('data-reveal'));
        if (!target) return;
        var apply = function () {
            target.classList.toggle('is-visible', box.checked);
        };
        box.addEventListener('change', apply);
        apply();
    });

    // Single-choice groups: choosing any non-Other option clears its Other input
    document.querySelectorAll('input[data-clear]').forEach(function (box) {
        box.addEventListener('change', function () {
            var wrapId = 'wrap-' + box.getAttribute('data-clear');
            var wrap = document.getElementById(wrapId);
            if (!wrap) return;
            var input = wrap.querySelector('input[type="text"]');
            if (input && input.value !== '') {
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            wrap.classList.remove('is-visible');
        });
    });

    // Certifications block toggles with Yes/No
    var certBlock = document.getElementById('cert-block');
    var syncCert = function () {
        var picked = document.querySelector('input[name="has_certification"]:checked');
        var on = !!picked && picked.value === 'yes';
        certBlock.classList.toggle('is-visible', on);
        certBlock.querySelectorAll('input').forEach(function (input) {
            input.disabled = !on;
        });
    };
    document.querySelectorAll('input[name="has_certification"]').forEach(function (radio) {
        radio.addEventListener('change', syncCert);
    });
    syncCert();

    // CV filename preview + drag & drop styling
    var cvInput = document.getElementById('cv');
    var cvBox = document.getElementById('cvBox');
    var cvName = document.getElementById('cvName');
    cvInput.addEventListener('change', function () {
        cvName.textContent = cvInput.files.length ? cvInput.files[0].name : '';
        cvBox.classList.toggle('has-file', cvInput.files.length > 0);
    });
    ['dragenter', 'dragover'].forEach(function (evt) {
        cvBox.addEventListener(evt, function (e) {
            e.preventDefault();
            cvBox.classList.add('dragover');
        });
    });
    ['dragleave', 'drop'].forEach(function (evt) {
        cvBox.addEventListener(evt, function (e) {
            e.preventDefault();
            cvBox.classList.remove('dragover');
        });
    });
    cvBox.addEventListener('drop', function (e) {
        if (e.dataTransfer.files.length) {
            cvInput.files = e.dataTransfer.files;
            cvInput.dispatchEvent(new Event('change'));
        }
    });

    // Live validation feedback + prevent double submission
    var regForm = document.getElementById('regForm');
    var emailField = document.getElementById('email');
    var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

    function regShowError(el, msg) {
        regClearError(el);
        el.classList.add('has-error');
        var holder = el.closest('.reg-field') || el.parentNode;
        var small = document.createElement('small');
        small.className = 'field-error';
        small.textContent = msg;
        holder.appendChild(small);
    }

    function regClearError(el) {
        el.classList.remove('has-error');
        var holder = el.closest('.reg-field') || el.parentNode;
        holder.querySelectorAll(':scope > .field-error').forEach(function (n) { n.remove(); });
    }

    if (emailField) {
        emailField.addEventListener('blur', function () {
            var v = emailField.value.trim();
            if (v === '' || emailRe.test(v)) {
                regClearError(emailField);
            } else {
                regShowError(emailField, 'Please enter a valid email address.');
            }
        });
        emailField.addEventListener('input', function () { regClearError(emailField); });
    }

    regForm.addEventListener('submit', function (e) {
        var firstBad = null;

        function fail(el, msg) {
            regShowError(el, msg);
            if (!firstBad) { firstBad = el; }
        }

        // required text inputs & selects
        regForm.querySelectorAll('input[required]:not([type="file"]):not([type="checkbox"]):not([type="radio"]), select[required]').forEach(function (el) {
            if (el.disabled) {
                return;
            }
            if (el.value.trim() === '') {
                fail(el, el.tagName === 'SELECT' ? 'This selection is required.' : 'This field is required.');
            } else if (el === emailField && !emailRe.test(el.value.trim())) {
                fail(el, 'Please enter a valid email address.');
            } else {
                regClearError(el);
            }
        });

        // radio groups
        ['gender', 'has_certification'].forEach(function (name) {
            var checked = regForm.querySelector('input[name="' + name + '"]:checked');
            if (!checked) {
                var any = regForm.querySelector('input[name="' + name + '"]');
                if (any) { fail(any, name === 'gender' ? 'Please select your gender.' : 'Please select Yes or No.'); }
            }
        });

        // declaration
        var decl = regForm.querySelector('input[name="declaration"]');
        if (decl && !decl.checked) {
            fail(decl, 'You must accept the declaration to submit.');
        }

        // CV file
        var cv = document.getElementById('cv');
        if (cv && cv.files.length === 0) {
            fail(cv, 'Please attach your CV (PDF or Word document).');
        }

        if (firstBad) {
            e.preventDefault();
            firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstBad.focus({ preventScroll: true });
            return;
        }

        var btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.classList.add('is-loading');
        btn.innerHTML = '<span>Submitting...</span>';
    });

    // clear error state while the user fixes fields
    regForm.addEventListener('input', function (e) {
        if (e.target.classList && e.target.classList.contains('has-error')) {
            regClearError(e.target);
        }
    });
    regForm.addEventListener('change', function (e) {
        if (e.target.name === 'declaration' || e.target.id === 'cv') {
            regClearError(e.target);
        }
        if (e.target.type === 'radio' || e.target.type === 'checkbox') {
            regClearError(e.target);
        }
    });
})();

// ------------------------------------------------------------------
// Searchable dropdowns (runs after the theme's deferred scripts)
// ------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {
    var combos = [];

    function closeAll(except) {
        combos.forEach(function (c) {
            if (c !== except) {
                c.classList.remove('is-open');
            }
        });
    }

    function buildCombo(sel) {
        if (sel.dataset.comboBuilt) {
            return;
        }
        sel.dataset.comboBuilt = '1';

        var placeholder = '';
        var options = [];
        Array.prototype.forEach.call(sel.options, function (opt) {
            if (opt.value === '' && placeholder === '') {
                placeholder = opt.text;
            } else {
                options.push({ value: opt.value, text: opt.text });
            }
        });

        var wrap = document.createElement('div');
        wrap.className = 'reg-combo';
        wrap.innerHTML =
            '<button type="button" class="reg-combo-toggle" aria-haspopup="listbox">' +
            '<span class="reg-combo-label"></span>' +
            '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>' +
            '<div class="reg-combo-panel">' +
            '<div class="reg-combo-search"><input type="text" placeholder="Type to search..." aria-label="Search options">' +
            '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></div>' +
            '<ul class="reg-combo-list" role="listbox"></ul></div>';

        sel.parentNode.insertBefore(wrap, sel);
        sel.style.display = 'none';

        var label = wrap.querySelector('.reg-combo-label');
        var list = wrap.querySelector('.reg-combo-list');
        var search = wrap.querySelector('.reg-combo-search input');

        options.forEach(function (opt) {
            var li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.dataset.value = opt.value;
            li.textContent = opt.text;
            list.appendChild(li);
        });

        function sync() {
            var has = sel.value !== '';
            label.textContent = has ? sel.options[sel.selectedIndex].text : placeholder;
            label.classList.toggle('is-placeholder', !has);
            Array.prototype.forEach.call(list.children, function (li) {
                li.classList.toggle('is-selected', li.dataset.value === sel.value);
            });
            wrap.classList.toggle('is-disabled', !!sel.disabled);
            wrap.classList.toggle('has-error', sel.classList.contains('has-error'));
            wrap.querySelector('.reg-combo-toggle').disabled = !!sel.disabled;
            if (sel.disabled) {
                wrap.classList.remove('is-open');
            }
        }

        function applyFilter() {
            var q = search.value.trim().toLowerCase();
            var visible = 0;
            Array.prototype.forEach.call(list.children, function (li) {
                var hit = q === '' || li.textContent.toLowerCase().indexOf(q) !== -1;
                li.classList.toggle('is-hidden', !hit);
                if (hit) {
                    visible++;
                }
            });
            var empty = list.querySelector('.reg-combo-empty');
            if (visible === 0 && !empty) {
                empty = document.createElement('li');
                empty.className = 'reg-combo-empty';
                empty.textContent = 'No matches found';
                list.appendChild(empty);
            } else if (visible > 0 && empty) {
                empty.remove();
            }
        }

        wrap.querySelector('.reg-combo-toggle').addEventListener('click', function () {
            if (sel.disabled) {
                return;
            }
            var opening = !wrap.classList.contains('is-open');
            closeAll(wrap);
            wrap.classList.toggle('is-open', opening);
            if (opening) {
                search.value = '';
                applyFilter();
                setTimeout(function () { search.focus(); }, 40);
            }
        });

        search.addEventListener('input', applyFilter);
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var first = list.querySelector('li:not(.is-hidden):not(.reg-combo-empty)');
                if (first) {
                    first.click();
                }
            } else if (e.key === 'Escape') {
                wrap.classList.remove('is-open');
            }
        });

        list.addEventListener('click', function (e) {
            var li = e.target.closest('li');
            if (!li || li.classList.contains('reg-combo-empty') || li.classList.contains('is-hidden')) {
                return;
            }
            sel.value = li.dataset.value;
            sync();
            wrap.classList.remove('is-open');
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // keep native select authoritative for "other" reveals etc.
        sel._comboSync = sync;
        sel.addEventListener('change', sync);

        combos.push(wrap);
        sync();
        return wrap;
    }

    function destroyCombo(sel) {
        if (!sel.dataset.comboBuilt) {
            return;
        }
        var wrap = sel.previousElementSibling;
        if (wrap && wrap.classList.contains('reg-combo')) {
            var idx = combos.indexOf(wrap);
            if (idx !== -1) {
                combos.splice(idx, 1);
            }
            wrap.remove();
        }
        if (sel._comboSync) {
            sel.removeEventListener('change', sel._comboSync);
            delete sel._comboSync;
        }
        sel.style.display = '';
        delete sel.dataset.comboBuilt;
    }

    function refreshCombo(sel) {
        destroyCombo(sel);
        buildCombo(sel);
    }

    document.querySelectorAll('select.reg-control').forEach(buildCombo);

    // District is a searchable dropdown whose options depend on the chosen Region.
    var regionSel = document.getElementById('region');
    var districtSel = document.getElementById('district');
    if (regionSel && districtSel) {
        var districtData = {};
        try {
            var dataEl = document.getElementById('bdst-districts-data');
            if (dataEl) {
                districtData = JSON.parse(dataEl.textContent) || {};
            }
        } catch (e) {
            districtData = {};
        }

        var clearDistrictError = function () {
            districtSel.classList.remove('has-error');
            var field = districtSel.closest('.reg-field');
            if (field) {
                field.querySelectorAll(':scope > .field-error').forEach(function (n) { n.remove(); });
            }
        };

        var fillDistricts = function (preserve) {
            var list = districtData[regionSel.value] || [];
            var current = preserve ? districtSel.value : '';

            districtSel.innerHTML = '';
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = list.length ? 'Select district...' : 'Select region first...';
            districtSel.appendChild(placeholder);

            list.forEach(function (name) {
                var opt = document.createElement('option');
                opt.value = name;
                opt.textContent = name;
                districtSel.appendChild(opt);
            });

            if (current !== '' && list.indexOf(current) !== -1) {
                districtSel.value = current;
            }
            districtSel.disabled = list.length === 0;
            refreshCombo(districtSel);
        };

        fillDistricts(true);
        regionSel.addEventListener('change', function () {
            clearDistrictError();
            fillDistricts(false);
        });
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.reg-combo')) {
            closeAll(null);
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAll(null);
        }
    });
});
</script>

<?php bdst_reg_page_end(); ?>
