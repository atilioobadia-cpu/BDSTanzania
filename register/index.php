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
    'client_segments' => [], 'client_segments_other' => '', 'clients_served' => '',
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
                $stmt->execute($data);
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
 * Render one styled checkbox chip.
 */
function reg_chip(string $name, string $value, string $checked, string $reveal = ''): void
{
    echo '<label class="reg-pill reg-chip">';
    echo '<input type="checkbox" name="' . reg_e($name) . '[]" value="' . reg_e($value) . '"' . $checked;
    if ($reveal !== '') {
        echo ' data-reveal="' . reg_e($reveal) . '"';
    }
    echo '><span>' . reg_e($value) . '</span></label>';
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

<!-- Registration Section -->
<section class="tj-blog-section section-gap">
    <div class="container">
        <div class="reg-intro wow fadeInUp" data-wow-delay=".1s">
            <h2 class="title" style="font-size:30px;font-weight:800;color:#0c1e21;margin-bottom:18px;">
                BDS Tanzania Member Registration Form
            </h2>
            <p>
                Thank you for your interest in joining BDS Tanzania, a fully registered association for business
                development service professionals in Tanzania. This form collects essential professional information
                about prospective members, including areas of expertise, experience, sector specialization and
                geographic coverage. The information provided helps the association build a reliable and structured
                database of business development service providers, support networking among professionals and
                facilitate the effective deployment of members in various programs, partnerships and advisory
                initiatives.
            </p>
            <p>
                Please be assured that all personal and professional information collected through this form will be
                handled with the highest level of confidentiality and professionalism. The data will be used strictly
                for legitimate association purposes such as member profiling, coordination of activities, professional
                engagement and communication within the BDS Tanzania network. The association is committed to ethical
                data management and will not share or use your information for any unauthorized or unrelated purposes.
            </p>
            <span class="reg-confidential"><i class="fa-solid fa-shield-halved"></i> Your information is kept confidential</span>
        </div>

        <div class="reg-form-shell">
            <?php if ($errors !== []): ?>
                <div class="reg-alert reg-alert-error wow fadeInUp" role="alert">
                    <strong><i class="fa-solid fa-circle-exclamation"></i> Please correct the following:</strong>
                    <ul>
                        <?php foreach (array_unique($errors) as $error): ?>
                            <li><?= reg_e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form id="regForm" method="post" action="index.php" enctype="multipart/form-data" autocomplete="on">
                <input type="hidden" name="csrf" value="<?= reg_e($_SESSION['reg_csrf']) ?>">
                <input type="hidden" name="form_time" value="<?= time() ?>">
                <input type="text" name="website" value="" tabindex="-1" aria-hidden="true" class="reg-hp">

                <!-- 1. Personal Information -->
                <div class="reg-card wow fadeInUp" data-wow-delay=".15s">
                    <div class="reg-section-head">
                        <span class="reg-section-num">01</span>
                        <div>
                            <h3 class="reg-section-title">Personal Information</h3>
                            <span class="reg-section-sub">Tell us who you are and how we can reach you.</span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="reg-field">
                                <label class="reg-label" for="full_name">Member Full Name <span class="reg-req">*</span></label>
                                <input class="reg-control" type="text" id="full_name" name="full_name" maxlength="150"
                                       placeholder="e.g. Amina Juma Mwakalinga" required
                                       value="<?= reg_old($old, 'full_name') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="reg-field">
                                <label class="reg-label" for="phone">Phone Number <span class="reg-req">*</span></label>
                                <input class="reg-control" type="tel" id="phone" name="phone" maxlength="20"
                                       placeholder="e.g. +255 712 345 678" required
                                       value="<?= reg_old($old, 'phone') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="reg-field">
                                <label class="reg-label" for="email">Email Address <span class="reg-req">*</span></label>
                                <input class="reg-control" type="email" id="email" name="email" maxlength="150"
                                       placeholder="you@example.com" required
                                       value="<?= reg_old($old, 'email') ?>">
                                <small style="color:#67787a;font-size:12.5px;">Your registration confirmation will be sent here.</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="reg-field">
                                <span class="reg-label">Gender <span class="reg-req">*</span></span>
                                <div class="reg-pills">
                                    <label class="reg-pill">
                                        <input type="radio" name="gender" value="Male"<?= reg_pill_checked($old, 'gender', 'Male') ?> required><span><i class="fa-solid fa-mars"></i> Male</span>
                                    </label>
                                    <label class="reg-pill">
                                        <input type="radio" name="gender" value="Female"<?= reg_pill_checked($old, 'gender', 'Female') ?>><span><i class="fa-solid fa-venus"></i> Female</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="reg-field">
                                <label class="reg-label" for="age_group">Age Group <span class="reg-req">*</span></label>
                                <select class="reg-control" id="age_group" name="age_group" required>
                                    <option value="">Select...</option>
                                    <?php foreach (bdst_age_groups() as $group): ?>
                                        <option value="<?= reg_e($group) ?>"<?= reg_selected($old, 'age_group', $group) ?>><?= reg_e($group) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Location -->
                <div class="reg-card wow fadeInUp" data-wow-delay=".2s">
                    <div class="reg-section-head">
                        <span class="reg-section-num">02</span>
                        <div>
                            <h3 class="reg-section-title">Location</h3>
                            <span class="reg-section-sub">Where you are based in Tanzania.</span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="reg-field">
                                <label class="reg-label" for="region">Region <span class="reg-req">*</span></label>
                                <select class="reg-control" id="region" name="region" required>
                                    <option value="">Select region...</option>
                                    <?php foreach (bdst_regions() as $regionOption): ?>
                                        <option value="<?= reg_e($regionOption) ?>"<?= reg_selected($old, 'region', $regionOption) ?>><?= reg_e($regionOption) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="reg-field">
                                <label class="reg-label" for="district">District <span class="reg-req">*</span></label>
                                <input class="reg-control" type="text" id="district" name="district" maxlength="80"
                                       placeholder="e.g. Ilala" required
                                       value="<?= reg_old($old, 'district') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Professional Background -->
                <div class="reg-card wow fadeInUp" data-wow-delay=".25s">
                    <div class="reg-section-head">
                        <span class="reg-section-num">03</span>
                        <div>
                            <h3 class="reg-section-title">Professional Background</h3>
                            <span class="reg-section-sub">Your current professional standing and experience.</span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="reg-field">
                                <label class="reg-label" for="professional_status">Current Professional Status <span class="reg-req">*</span></label>
                                <select class="reg-control" id="professional_status" name="professional_status"
                                        data-other-target="wrap-professional_status_other" required>
                                    <option value="">Select status...</option>
                                    <?php foreach (bdst_professional_statuses() as $statusOption): ?>
                                        <option value="<?= reg_e($statusOption) ?>"<?= reg_selected($old, 'professional_status', $statusOption) ?>><?= reg_e($statusOption) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="reg-field">
                                <div class="reg-other-wrap" id="wrap-professional_status_other">
                                    <label class="reg-label" for="professional_status_other">Specify your status <span class="reg-req">*</span></label>
                                    <input class="reg-control" type="text" id="professional_status_other"
                                           name="professional_status_other" maxlength="100"
                                           placeholder="Describe your professional status"
                                           value="<?= reg_old($old, 'professional_status_other') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="reg-field">
                                <label class="reg-label" for="firm_name">Firm / Organization Name <span class="reg-opt">(if you operate in a firm)</span></label>
                                <input class="reg-control" type="text" id="firm_name" name="firm_name" maxlength="150"
                                       placeholder="e.g. Bright Advisory Ltd"
                                       value="<?= reg_old($old, 'firm_name') ?>">
                            </div>
                            <div class="reg-field">
                                <label class="reg-label" for="experience_years">Years of Experience in Business Advisory / Consulting <span class="reg-req">*</span></label>
                                <select class="reg-control" id="experience_years" name="experience_years" required>
                                    <option value="">Select...</option>
                                    <?php foreach (bdst_experience_levels() as $level): ?>
                                        <option value="<?= reg_e($level) ?>"<?= reg_selected($old, 'experience_years', $level) ?>><?= reg_e($level) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="reg-field">
                                <label class="reg-label" for="education">Highest Education Level <span class="reg-req">*</span></label>
                                <select class="reg-control" id="education" name="education"
                                        data-other-target="wrap-education_other" required>
                                    <option value="">Select...</option>
                                    <?php foreach (bdst_education_levels() as $eduOption): ?>
                                        <option value="<?= reg_e($eduOption) ?>"<?= reg_selected($old, 'education', $eduOption) ?>><?= reg_e($eduOption) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="reg-field">
                                <div class="reg-other-wrap" id="wrap-education_other">
                                    <label class="reg-label" for="education_other">Specify your education <span class="reg-req">*</span></label>
                                    <input class="reg-control" type="text" id="education_other"
                                           name="education_other" maxlength="100"
                                           placeholder="Describe your highest qualification"
                                           value="<?= reg_old($old, 'education_other') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="reg-field">
                                <label class="reg-label" for="clients_served">Clients Served in the Past 3 Years <span class="reg-req">*</span></label>
                                <select class="reg-control" id="clients_served" name="clients_served" required>
                                    <option value="">Select...</option>
                                    <?php foreach (bdst_clients_served() as $range): ?>
                                        <option value="<?= reg_e($range) ?>"<?= reg_selected($old, 'clients_served', $range) ?>><?= reg_e($range) ?> clients</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Expertise & Services -->
                <div class="reg-card wow fadeInUp" data-wow-delay=".3s">
                    <div class="reg-section-head">
                        <span class="reg-section-num">04</span>
                        <div>
                            <h3 class="reg-section-title">Expertise &amp; Services</h3>
                            <span class="reg-section-sub">Select all options that apply to you.</span>
                        </div>
                    </div>

                    <div class="reg-field">
                        <span class="reg-label">Primary BDS Service Areas <span class="reg-req">*</span> <span class="reg-opt">(multiple allowed)</span></span>
                        <div class="reg-pills">
                            <?php foreach (bdst_service_areas() as $area): ?>
                                <?php reg_chip('service_areas', $area, reg_chip_checked($old, 'service_areas', $area)); ?>
                            <?php endforeach; ?>
                            <?php reg_chip('service_areas', 'Other', reg_chip_checked($old, 'service_areas', 'Other'), 'wrap-service_areas_other'); ?>
                        </div>
                        <div class="reg-other-wrap" id="wrap-service_areas_other">
                            <input class="reg-control" type="text" name="service_areas_other" maxlength="100"
                                   placeholder="Specify other service areas" style="margin-top:10px;"
                                   value="<?= reg_old($old, 'service_areas_other') ?>">
                        </div>
                    </div>

                    <div class="reg-field">
                        <span class="reg-label">Industries You Have Experience Serving <span class="reg-req">*</span> <span class="reg-opt">(multiple allowed)</span></span>
                        <div class="reg-pills">
                            <?php foreach (bdst_industries() as $industry): ?>
                                <?php reg_chip('industries', $industry, reg_chip_checked($old, 'industries', $industry)); ?>
                            <?php endforeach; ?>
                            <?php reg_chip('industries', 'Other', reg_chip_checked($old, 'industries', 'Other'), 'wrap-industries_other'); ?>
                        </div>
                        <div class="reg-other-wrap" id="wrap-industries_other">
                            <input class="reg-control" type="text" name="industries_other" maxlength="100"
                                   placeholder="Specify other industries" style="margin-top:10px;"
                                   value="<?= reg_old($old, 'industries_other') ?>">
                        </div>
                    </div>

                    <div class="reg-field">
                        <span class="reg-label">Client Segments You Mostly Serve <span class="reg-req">*</span> <span class="reg-opt">(multiple allowed)</span></span>
                        <div class="reg-pills">
                            <?php foreach (bdst_client_segments() as $segment): ?>
                                <?php reg_chip('client_segments', $segment, reg_chip_checked($old, 'client_segments', $segment)); ?>
                            <?php endforeach; ?>
                            <?php reg_chip('client_segments', 'Other', reg_chip_checked($old, 'client_segments', 'Other'), 'wrap-client_segments_other'); ?>
                        </div>
                        <div class="reg-other-wrap" id="wrap-client_segments_other">
                            <input class="reg-control" type="text" name="client_segments_other" maxlength="100"
                                   placeholder="Specify other client segments" style="margin-top:10px;"
                                   value="<?= reg_old($old, 'client_segments_other') ?>">
                        </div>
                    </div>

                    <div class="reg-field">
                        <span class="reg-label">Nature of Services You Mostly Deliver <span class="reg-req">*</span> <span class="reg-opt">(multiple allowed)</span></span>
                        <div class="reg-pills">
                            <?php foreach (bdst_service_natures() as $nature): ?>
                                <?php reg_chip('service_nature', $nature, reg_chip_checked($old, 'service_nature', $nature)); ?>
                            <?php endforeach; ?>
                            <?php reg_chip('service_nature', 'Other', reg_chip_checked($old, 'service_nature', 'Other'), 'wrap-service_nature_other'); ?>
                        </div>
                        <div class="reg-other-wrap" id="wrap-service_nature_other">
                            <input class="reg-control" type="text" name="service_nature_other" maxlength="100"
                                   placeholder="Specify other services" style="margin-top:10px;"
                                   value="<?= reg_old($old, 'service_nature_other') ?>">
                        </div>
                    </div>
                </div>

                <!-- 5. Certifications -->
                <div class="reg-card wow fadeInUp" data-wow-delay=".35s">
                    <div class="reg-section-head">
                        <span class="reg-section-num">05</span>
                        <div>
                            <h3 class="reg-section-title">Professional Certifications</h3>
                            <span class="reg-section-sub">Do you hold any professional certifications?</span>
                        </div>
                    </div>

                    <div class="reg-field">
                        <span class="reg-label">Do you hold any professional certifications? <span class="reg-req">*</span></span>
                        <div class="reg-pills">
                            <label class="reg-pill">
                                <input type="radio" name="has_certification" value="yes"<?= reg_pill_checked($old, 'has_certification', 'yes') ?> data-cert-toggle required><span>Yes</span>
                            </label>
                            <label class="reg-pill">
                                <input type="radio" name="has_certification" value="no"<?= reg_pill_checked($old, 'has_certification', 'no') ?> data-cert-toggle><span>No</span>
                            </label>
                        </div>
                    </div>

                    <div id="cert-block" style="display:none;">
                        <div class="reg-field">
                            <span class="reg-label">Type of Certification <span class="reg-req">*</span> <span class="reg-opt">(multiple allowed)</span></span>
                            <div class="reg-pills">
                                <?php foreach (bdst_certification_types() as $cert): ?>
                                    <?php reg_chip('certifications', $cert, reg_chip_checked($old, 'certifications', $cert)); ?>
                                <?php endforeach; ?>
                                <?php reg_chip('certifications', 'Other', reg_chip_checked($old, 'certifications', 'Other'), 'wrap-certifications_other'); ?>
                            </div>
                            <div class="reg-other-wrap" id="wrap-certifications_other">
                                <input class="reg-control" type="text" name="certifications_other" maxlength="100"
                                       placeholder="Specify other certification" style="margin-top:10px;"
                                       value="<?= reg_old($old, 'certifications_other') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. Coverage -->
                <div class="reg-card wow fadeInUp" data-wow-delay=".4s">
                    <div class="reg-section-head">
                        <span class="reg-section-num">06</span>
                        <div>
                            <h3 class="reg-section-title">Geographic Coverage</h3>
                            <span class="reg-section-sub">Zones where you can deliver services.</span>
                        </div>
                    </div>

                    <div class="reg-field">
                        <span class="reg-label">Zones Where You Can Deliver Services <span class="reg-req">*</span> <span class="reg-opt">(multiple allowed)</span></span>
                        <div class="reg-pills">
                            <?php foreach (bdst_zones() as $zone): ?>
                                <?php reg_chip('zones', $zone, reg_chip_checked($old, 'zones', $zone)); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- 7. Declaration & CV -->
                <div class="reg-card wow fadeInUp" data-wow-delay=".45s">
                    <div class="reg-section-head">
                        <span class="reg-section-num">07</span>
                        <div>
                            <h3 class="reg-section-title">Declaration &amp; CV</h3>
                            <span class="reg-section-sub">Final step before submission.</span>
                        </div>
                    </div>

                    <div class="reg-field">
                        <label class="reg-declare">
                            <input type="checkbox" name="declaration" value="1"<?= ($old['declaration'] ?? 0) == 1 ? ' checked' : '' ?> required>
                            <p>I hereby declare that the information provided in this form is true and correct to the best
                                of my knowledge and I commit to abide by the code of conduct and professional standards
                                of BDS Tanzania.</p>
                        </label>
                    </div>

                    <div class="reg-field">
                        <span class="reg-label">Upload Your CV <span class="reg-req">*</span></span>
                        <label class="reg-file-box" id="cvBox">
                            <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx" required>
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <strong>Click to upload or drag your CV here</strong>
                            <small>PDF or Word document &middot; Maximum size 10 MB</small>
                            <div class="reg-file-name" id="cvName"></div>
                        </label>
                    </div>

                    <div class="reg-submit-row">
                        <button type="submit" class="reg-submit-btn" id="submitBtn">
                            <span>Submit Registration</span>
                            <i class="tji-arrow-right-long"></i>
                        </button>
                        <p class="reg-note">
                            After submitting you will receive a confirmation email with your unique reference number.
                        </p>
                    </div>
                </div>
            </form>
        </div>
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

    // Checkbox "Other" reveals
    document.querySelectorAll('input[type="checkbox"][data-reveal]').forEach(function (box) {
        var target = document.getElementById(box.getAttribute('data-reveal'));
        if (!target) return;
        var apply = function () {
            target.classList.toggle('is-visible', box.checked);
        };
        box.addEventListener('change', apply);
        apply();
    });

    // Certifications block toggles with Yes/No
    var certBlock = document.getElementById('cert-block');
    var syncCert = function () {
        var picked = document.querySelector('input[name="has_certification"]:checked');
        var on = !!picked && picked.value === 'yes';
        certBlock.style.display = on ? '' : 'none';
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
        btn.innerHTML = '<span>Submitting...</span>';
    });
})();
</script>

<?php bdst_reg_page_end(); ?>
