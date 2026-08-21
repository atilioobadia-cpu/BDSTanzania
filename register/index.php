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
    // Honeypot: real users never fill this hidden field.
    if (!empty($_POST['website'])) {
        header('Location: ../index.html');
        exit;
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

                $stmt = $pdo->prepare(
                    "INSERT INTO bds_registrations
                        (full_name, gender, age_group, phone, email, region, district,
                         professional_status, firm_name, experience_years, education,
                         service_areas, industries, client_segments, clients_served,
                         service_nature, has_certification, certifications, zones,
                         declaration, cv_path, cv_original_name, status)
                     VALUES
                        (:full_name, :gender, :age_group, :phone, :email, :region, :district,
                         :professional_status, :firm_name, :experience_years, :education,
                         :service_areas, :industries, :client_segments, :clients_served,
                         :service_nature, :has_certification, :certifications, :zones,
                         :declaration, '', '', 'pending')"
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
                    $errors[] = $e->getMessage();
                }

                if (!$errors) {
                    require_once __DIR__ . '/../includes/reg-mail.php';
                    try {
                        bdst_send_registration_emails($pdo, (int) $id);
                    } catch (Throwable $mailError) {
                        error_log('Registration mail error: ' . $mailError->getMessage());
                    }

                    session_regenerate_id(true);
                    $_SESSION['reg_csrf'] = bin2hex(random_bytes(32));
                    header('Location: success.php?ref=' . urlencode($ref));
                    exit;
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
            <h1>BDS Tanzania Membership Registration</h1>
            <p>
                Thank you for your interest in joining BDS Tanzania, the national association of business development
                service professionals. This form collects your professional profile — expertise, experience, sector
                specialization and geographic coverage — so the Secretariat can review your eligibility and determine
                the right membership category for you.
            </p>
            <p>
                All information you provide is treated with strict confidentiality and used only for legitimate
                association purposes such as member profiling, program coordination and professional engagement within
                the BDS Tanzania network.
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
            <input type="text" name="website" value="" tabindex="-1" aria-hidden="true" class="reg-hp">

            <div class="reg-form-head">
                <div>
                    <h2>Registration Form</h2>
                    <p>Fields marked <span class="reg-req">*</span> are required.</p>
                </div>
            </div>

            <?php reg_section('01', 'Personal Information', 'Tell us who you are and how we can reach you.'); ?>
            <div class="row">
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="full_name">Full Name <span class="reg-req">*</span></label>
                        <input class="reg-control" type="text" id="full_name" name="full_name" maxlength="150"
                               placeholder="e.g. Amina Juma Mwakalinga" required
                               value="<?= reg_old($old, 'full_name') ?>">
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="phone">Phone Number <span class="reg-req">*</span></label>
                        <input class="reg-control" type="tel" id="phone" name="phone" maxlength="20"
                               placeholder="e.g. +255 712 345 678" required
                               value="<?= reg_old($old, 'phone') ?>">
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="email">Email Address <span class="reg-req">*</span></label>
                        <input class="reg-control" type="email" id="email" name="email" maxlength="150"
                               placeholder="you@example.com" required
                               value="<?= reg_old($old, 'email') ?>">
                        <small class="reg-hint">Your registration confirmation will be sent here.</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="reg-field">
                        <span class="reg-label">Gender <span class="reg-req">*</span></span>
                        <div class="reg-chip-row">
                            <label class="reg-chip reg-choice"><input type="radio" name="gender" value="Male"<?= reg_pill_checked($old, 'gender', 'Male') ?> required><span><i class="fa-solid fa-mars"></i> Male</span></label>
                            <label class="reg-chip reg-choice"><input type="radio" name="gender" value="Female"<?= reg_pill_checked($old, 'gender', 'Female') ?>><span><i class="fa-solid fa-venus"></i> Female</span></label>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="reg-field">
                        <label class="reg-label" for="age_group">Age Group <span class="reg-req">*</span></label>
                        <select class="reg-control" id="age_group" name="age_group" required>
                            <option value="">Select...</option>
                            <?php foreach (bdst_age_groups() as $opt): ?>
                                <option value="<?= reg_e($opt) ?>"<?= reg_selected($old, 'age_group', $opt) ?>><?= reg_e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <?php reg_section('02', 'Location', 'Where you are based in Tanzania.'); ?>
            <div class="row">
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="region">Region <span class="reg-req">*</span></label>
                        <select class="reg-control" id="region" name="region" required>
                            <option value="">Select region...</option>
                            <?php foreach (bdst_regions() as $opt): ?>
                                <option value="<?= reg_e($opt) ?>"<?= reg_selected($old, 'region', $opt) ?>><?= reg_e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="district">District <span class="reg-req">*</span></label>
                        <input class="reg-control" type="text" id="district" name="district" maxlength="80"
                               placeholder="e.g. Ilala" required
                               value="<?= reg_old($old, 'district') ?>">
                    </div>
                </div>
            </div>

            <?php reg_section('03', 'Professional Background', 'Your current professional standing and experience.'); ?>
            <div class="row">
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="professional_status">Current Professional Status <span class="reg-req">*</span></label>
                        <select class="reg-control" id="professional_status" name="professional_status"
                                data-other-target="wrap-professional_status_other" required>
                            <option value="">Select status...</option>
                            <?php foreach (bdst_professional_statuses() as $opt): ?>
                                <option value="<?= reg_e($opt) ?>"<?= reg_selected($old, 'professional_status', $opt) ?>><?= reg_e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="reg-other-wrap" id="wrap-professional_status_other">
                        <label class="reg-label" for="professional_status_other">Specify Your Status <span class="reg-req">*</span></label>
                        <input class="reg-control" type="text" id="professional_status_other"
                               name="professional_status_other" maxlength="100"
                               placeholder="Describe your professional status"
                               value="<?= reg_old($old, 'professional_status_other') ?>">
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="firm_name">Firm / Organization Name <span class="reg-opt">(if you operate in a firm)</span></label>
                        <input class="reg-control" type="text" id="firm_name" name="firm_name" maxlength="150"
                               placeholder="e.g. Bright Advisory Ltd"
                               value="<?= reg_old($old, 'firm_name') ?>">
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="experience_years">Years of Experience in Business Advisory / Consulting <span class="reg-req">*</span></label>
                        <select class="reg-control" id="experience_years" name="experience_years" required>
                            <option value="">Select...</option>
                            <?php foreach (bdst_experience_levels() as $opt): ?>
                                <option value="<?= reg_e($opt) ?>"<?= reg_selected($old, 'experience_years', $opt) ?>><?= reg_e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="clients_served">Clients Served in the Past 3 Years <span class="reg-req">*</span></label>
                        <select class="reg-control" id="clients_served" name="clients_served" required>
                            <option value="">Select...</option>
                            <?php foreach (bdst_clients_served() as $opt): ?>
                                <option value="<?= reg_e($opt) ?>"<?= reg_selected($old, 'clients_served', $opt) ?>><?= reg_e(bdst_clients_served_label($opt)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="reg-field">
                        <label class="reg-label" for="education">Highest Education Level <span class="reg-req">*</span></label>
                        <select class="reg-control" id="education" name="education"
                                data-other-target="wrap-education_other" required>
                            <option value="">Select...</option>
                            <?php foreach (bdst_education_levels() as $opt): ?>
                                <option value="<?= reg_e($opt) ?>"<?= reg_selected($old, 'education', $opt) ?>><?= reg_e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="reg-other-wrap" id="wrap-education_other">
                        <label class="reg-label" for="education_other">Specify Your Education <span class="reg-req">*</span></label>
                        <input class="reg-control" type="text" id="education_other"
                               name="education_other" maxlength="100"
                               placeholder="Describe your highest qualification"
                               value="<?= reg_old($old, 'education_other') ?>">
                    </div>
                </div>
            </div>

            <?php reg_section('04', 'Expertise & Services', 'Select all options that apply to you.'); ?>
            <div class="reg-field">
                <span class="reg-label">Primary BDS Service Areas <span class="reg-req">*</span> <span class="reg-opt">(multiple allowed)</span></span>
                <div class="reg-chip-grid">
                    <?php foreach (bdst_service_areas() as $opt): ?>
                        <?php reg_chip('service_areas', $opt, reg_chip_checked($old, 'service_areas', $opt)); ?>
                    <?php endforeach; ?>
                    <?php reg_chip('service_areas', 'Other', reg_chip_checked($old, 'service_areas', 'Other'), 'wrap-service_areas_other'); ?>
                </div>
                <div class="reg-other-wrap" id="wrap-service_areas_other">
                    <input class="reg-control" type="text" name="service_areas_other" maxlength="100"
                           placeholder="Specify other service areas"
                           value="<?= reg_old($old, 'service_areas_other') ?>">
                </div>
            </div>

            <div class="reg-field">
                <span class="reg-label">Industries You Have Experience Serving <span class="reg-req">*</span> <span class="reg-opt">(multiple allowed)</span></span>
                <div class="reg-chip-grid">
                    <?php foreach (bdst_industries() as $opt): ?>
                        <?php reg_chip('industries', $opt, reg_chip_checked($old, 'industries', $opt)); ?>
                    <?php endforeach; ?>
                    <?php reg_chip('industries', 'Other', reg_chip_checked($old, 'industries', 'Other'), 'wrap-industries_other'); ?>
                </div>
                <div class="reg-other-wrap" id="wrap-industries_other">
                    <input class="reg-control" type="text" name="industries_other" maxlength="100"
                           placeholder="Specify other industries"
                           value="<?= reg_old($old, 'industries_other') ?>">
                </div>
            </div>

            <div class="reg-field">
                <span class="reg-label">Client Segment You Mostly Serve <span class="reg-req">*</span> <span class="reg-opt">(select one)</span></span>
                <div class="reg-chip-grid">
                    <?php foreach (bdst_client_segments() as $opt): ?>
                        <?php reg_choice('client_segments', $opt, reg_pill_checked($old, 'client_segments', $opt), $old, 'client_segments'); ?>
                    <?php endforeach; ?>
                    <?php reg_choice('client_segments', 'Other', reg_pill_checked($old, 'client_segments', 'Other'), $old, 'client_segments', 'wrap-client_segments_other'); ?>
                </div>
                <div class="reg-other-wrap" id="wrap-client_segments_other">
                    <input class="reg-control" type="text" name="client_segments_other" maxlength="100"
                           placeholder="Describe your client segment"
                           value="<?= reg_old($old, 'client_segments_other') ?>">
                </div>
            </div>

            <div class="reg-field">
                <span class="reg-label">Nature of Services You Mostly Deliver <span class="reg-req">*</span> <span class="reg-opt">(multiple allowed)</span></span>
                <div class="reg-chip-grid">
                    <?php foreach (bdst_service_natures() as $opt): ?>
                        <?php reg_chip('service_nature', $opt, reg_chip_checked($old, 'service_nature', $opt)); ?>
                    <?php endforeach; ?>
                    <?php reg_chip('service_nature', 'Other', reg_chip_checked($old, 'service_nature', 'Other'), 'wrap-service_nature_other'); ?>
                </div>
                <div class="reg-other-wrap" id="wrap-service_nature_other">
                    <input class="reg-control" type="text" name="service_nature_other" maxlength="100"
                           placeholder="Specify other services"
                           value="<?= reg_old($old, 'service_nature_other') ?>">
                </div>
            </div>

            <?php reg_section('05', 'Professional Certifications', 'Do you hold any professional certifications?'); ?>
            <div class="reg-field">
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
            </div>
            <div id="cert-block" class="reg-cert-block">
                <div class="reg-field">
                    <span class="reg-label">Type of Certification <span class="reg-req">*</span> <span class="reg-opt">(multiple allowed)</span></span>
                    <div class="reg-chip-grid">
                        <?php foreach (bdst_certification_types() as $opt): ?>
                            <?php reg_chip('certifications', $opt, reg_chip_checked($old, 'certifications', $opt)); ?>
                        <?php endforeach; ?>
                        <?php reg_chip('certifications', 'Other', reg_chip_checked($old, 'certifications', 'Other'), 'wrap-certifications_other'); ?>
                    </div>
                    <div class="reg-other-wrap" id="wrap-certifications_other">
                        <input class="reg-control" type="text" name="certifications_other" maxlength="100"
                               placeholder="Specify other certification"
                               value="<?= reg_old($old, 'certifications_other') ?>">
                    </div>
                </div>
            </div>

            <?php reg_section('06', 'Geographic Coverage', 'Zones where you can deliver services.'); ?>
            <div class="reg-field">
                <div class="reg-chip-grid">
                    <?php foreach (bdst_zones() as $opt): ?>
                        <?php reg_chip('zones', $opt, reg_chip_checked($old, 'zones', $opt)); ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php reg_section('07', 'Declaration & CV', 'Final step before submission.'); ?>
            <div class="reg-field">
                <label class="reg-declare">
                    <input type="checkbox" name="declaration" value="1"<?= $old['declaration'] ? ' checked' : '' ?> required>
                    <span>I hereby declare that the information provided in this form is true and correct to the best
                        of my knowledge, and I commit to abide by the code of conduct and professional standards of
                        BDS Tanzania. <span class="reg-req">*</span></span>
                </label>
            </div>

            <div class="reg-field">
                <span class="reg-label">Upload Your CV <span class="reg-req">*</span></span>
                <label class="reg-file-box" id="cvBox">
                    <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx" required>
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <strong>Click to upload or drag your CV here</strong>
                    <small>PDF or Word document &middot; Maximum size 10 MB</small>
                    <span class="reg-file-name" id="cvName"></span>
                </label>
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
</section>

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

    // Prevent double submission
    document.getElementById('regForm').addEventListener('submit', function () {
        var btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.classList.add('is-loading');
        btn.innerHTML = '<span>Submitting...</span>';
    });
})();
</script>

<?php bdst_reg_page_end(); ?>
