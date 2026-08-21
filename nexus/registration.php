<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/../includes/forms.php';
bdst_require_login();

$pdo = bdst_db();
$id  = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM bds_registrations WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$reg = $stmt->fetch();

if ($reg === false) {
    http_response_code(404);
    $notFound = true;
} else {
    $notFound = false;
}

$errors = [];
$saved  = false;

// ---------------------------------------------------------
// Save edits (POST)
// ---------------------------------------------------------
if (!$notFound && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bdst_csrf_verify($_POST['csrf'] ?? null)) {
        $errors['csrf'] = 'Security token expired. Please try again.';
    } else {
        // declaration is implied for admin edits.
        $_POST['declaration'] = 'yes';

        $result   = bdst_collect_registration($_POST, []);
        $data     = $result['data'];
        $errors   = $result['errors'];
        $newStatus = (string) ($_POST['status'] ?? '');
        if (!in_array($newStatus, bdst_registration_statuses(), true)) {
            $errors['status'] = 'Please choose a valid status.';
        }

        if ($errors === []) {
            $sql = "UPDATE bds_registrations SET
                        full_name = :full_name,
                        gender = :gender,
                        age_group = :age_group,
                        phone = :phone,
                        email = :email,
                        region = :region,
                        district = :district,
                        professional_status = :professional_status,
                        firm_name = :firm_name,
                        experience_years = :experience_years,
                        education = :education,
                        service_areas = :service_areas,
                        industries = :industries,
                        client_segments = :client_segments,
                        clients_served = :clients_served,
                        service_nature = :service_nature,
                        has_certification = :has_certification,
                        certifications = :certifications,
                        zones = :zones,
                        status = :status,
                        admin_notes = :admin_notes,
                        reviewed_at = NOW()
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
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
                ':has_certification'   => (string) $data['has_certification'],
                ':certifications'      => $data['certifications'],
                ':zones'               => $data['zones'],
                ':status'              => $newStatus,
                ':admin_notes'         => trim((string) ($_POST['admin_notes'] ?? '')),
                ':id'                  => $id,
            ]);
            bdst_flash('success', 'Registration ' . $reg['reference_no'] . ' saved.');
            header('Location: registration.php?id=' . $id);
            exit;
        }
    }

    // Re-render with submitted values.
    if (isset($data)) {
        foreach ($data as $key => $value) {
            if ($key === 'has_certification') {
                continue;
            }
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $reg[$key] = (string) $value;
        }
        $reg['has_certification'] = !empty($data['has_certification']) ? 'yes' : 'no';
    }
}

/**
 * Split a stored JSON list into [standard options present] and [custom values].
 *
 * @return array{0: string[], 1: string[]}
 */
function reg_split_list(?string $json, array $options): array
{
    $values = bdst_decode_list($json);
    $std = [];
    $custom = [];
    foreach ($values as $value) {
        if (in_array($value, $options, true)) {
            $std[] = $value;
        } else {
            $custom[] = $value;
        }
    }
    return [$std, $custom];
}

/** Render a checkbox grid of options with stored ones checked. */
function reg_admin_chips(string $name, array $options, array $checked): void
{
    foreach ($options as $option) {
        $isChecked = in_array($option, $checked, true);
        ?>
        <label class="achip">
            <input type="checkbox" name="<?= bdst_e($name) ?>[]" value="<?= bdst_e($option) ?>"<?= $isChecked ? ' checked' : '' ?>>
            <span><?= bdst_e($option) ?></span>
        </label>
        <?php
    }
}

$flash = bdst_flash_consume();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= bdst_admin_title($notFound ? 'Not Found' : $reg['reference_no']) ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/images/fav.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">
<?php bdst_admin_sidebar('registrations'); ?>

<main class="main">
    <header class="topbar">
        <div class="topbar-inner">
            <div class="page-title-block">
                <?php if ($notFound): ?>
                    <h1>Registration Not Found</h1>
                    <p>The requested registration does not exist.</p>
                <?php else: ?>
                    <h1><?= bdst_e($reg['reference_no']) ?></h1>
                    <p>Submitted <?= bdst_e(date('F j, Y \a\t g:i A', strtotime($reg['submitted_at']))) ?></p>
                <?php endif; ?>
            </div>
            <div class="row-actions">
                <a href="registrations.php" class="btn btn-ghost">&larr; All Registrations</a>
                <?php if (!$notFound && $reg['cv_path'] !== null && $reg['cv_path'] !== ''): ?>
                    <a href="cv.php?id=<?= $id ?>" class="btn btn-primary">Download CV</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="page">
        <?php if ($flash): ?>
            <div class="alert alert-<?= bdst_e($flash['type']) ?>"><?= bdst_e($flash['message']) ?></div>
        <?php endif; ?>

        <?php if ($notFound): ?>
            <div class="panel"><div class="empty-state"><p>This registration no longer exists.</p></div></div>
        <?php else: ?>
            <?php if ($errors !== []): ?>
                <div class="alert alert-error">
                    <strong>Please fix the highlighted fields:</strong><br>
                    &bull; <?= implode('<br>&bull; ', array_map('bdst_e', $errors)) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="registration.php?id=<?= $id ?>">
                <input type="hidden" name="csrf" value="<?= bdst_e(bdst_csrf_token()) ?>">
                <input type="hidden" name="declaration" value="yes">

                <div class="panel">
                    <h2 class="panel-title">Applicant</h2>
                    <div class="form-grid">
                        <div class="field">
                            <label>Full Name *</label>
                            <input type="text" name="full_name" maxlength="150" required value="<?= bdst_e($reg['full_name']) ?>">
                            <?php if (isset($errors['full_name'])): ?><small class="field-error"><?= bdst_e($errors['full_name']) ?></small><?php endif; ?>
                        </div>
                        <div class="field">
                            <label>Gender *</label>
                            <select name="gender" required>
                                <option value="Male"<?= $reg['gender'] === 'Male' ? ' selected' : '' ?>>Male</option>
                                <option value="Female"<?= $reg['gender'] === 'Female' ? ' selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Age Group *</label>
                            <select name="age_group" required>
                                <?php foreach (bdst_age_groups() as $opt): ?>
                                    <option value="<?= bdst_e($opt) ?>"<?= $reg['age_group'] === $opt ? ' selected' : '' ?>><?= bdst_e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Phone Number *</label>
                            <input type="text" name="phone" maxlength="20" required value="<?= bdst_e($reg['phone']) ?>">
                            <?php if (isset($errors['phone'])): ?><small class="field-error"><?= bdst_e($errors['phone']) ?></small><?php endif; ?>
                        </div>
                        <div class="field">
                            <label>Email Address *</label>
                            <input type="email" name="email" maxlength="150" required value="<?= bdst_e($reg['email']) ?>">
                            <?php if (isset($errors['email'])): ?><small class="field-error"><?= bdst_e($errors['email']) ?></small><?php endif; ?>
                        </div>
                        <div class="field">
                            <label>Region *</label>
                            <select name="region" required>
                                <?php foreach (bdst_regions() as $opt): ?>
                                    <option value="<?= bdst_e($opt) ?>"<?= $reg['region'] === $opt ? ' selected' : '' ?>><?= bdst_e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>District *</label>
                            <input type="text" name="district" maxlength="100" required value="<?= bdst_e($reg['district']) ?>">
                            <?php if (isset($errors['district'])): ?><small class="field-error"><?= bdst_e($errors['district']) ?></small><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2 class="panel-title">Professional Profile</h2>
                    <div class="form-grid">
                        <div class="field">
                            <label>Professional Status *</label>
                            <select name="professional_status" required>
                                <?php foreach (bdst_professional_statuses() as $opt): ?>
                                    <option value="<?= bdst_e($opt) ?>"<?= $reg['professional_status'] === $opt ? ' selected' : '' ?>><?= bdst_e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Firm / Organisation</label>
                            <input type="text" name="firm_name" maxlength="150" value="<?= bdst_e((string) $reg['firm_name']) ?>">
                        </div>
                        <div class="field">
                            <label>Experience *</label>
                            <select name="experience_years" required>
                                <?php foreach (bdst_experience_levels() as $opt): ?>
                                    <option value="<?= bdst_e($opt) ?>"<?= $reg['experience_years'] === $opt ? ' selected' : '' ?>><?= bdst_e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Highest Education Level *</label>
                            <select name="education" required>
                                <?php foreach (bdst_education_levels() as $opt): ?>
                                    <option value="<?= bdst_e($opt) ?>"<?= $reg['education'] === $opt ? ' selected' : '' ?>><?= bdst_e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Clients Served *</label>
                            <select name="clients_served" required>
                                <?php foreach (bdst_clients_served() as $opt): ?>
                                    <option value="<?= bdst_e($opt) ?>"<?= $reg['clients_served'] === $opt ? ' selected' : '' ?>><?= bdst_e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2 class="panel-title">Expertise &amp; Coverage</h2>
                    <?php
                    [$areasStd, $areasCustom]     = reg_split_list($reg['service_areas'] ?? null, bdst_service_areas());
                    [$indsStd, $indsCustom]       = reg_split_list($reg['industries'] ?? null, bdst_industries());
                    [$segStd, $segCustom]         = reg_split_list($reg['client_segments'] ?? null, bdst_client_segments());
                    [$natStd, $natCustom]         = reg_split_list($reg['service_nature'] ?? null, bdst_service_natures());
                    [$certsStd, $certsCustom]     = reg_split_list($reg['certifications'] ?? null, bdst_certification_types());
                    [$zonesStd, $zonesCustom]     = reg_split_list($reg['zones'] ?? null, bdst_zones());
                    ?>
                    <div class="field">
                        <label>Service Areas Offered *</label>
                        <div class="achip-grid"><?php reg_admin_chips('service_areas', bdst_service_areas(), $areasStd); ?></div>
                        <input type="text" name="service_areas_other" placeholder="Other (comma separated)"
                               value="<?= bdst_e(implode(', ', $areasCustom)) ?>">
                        <?php if (isset($errors['service_areas'])): ?><small class="field-error"><?= bdst_e($errors['service_areas']) ?></small><?php endif; ?>
                    </div>
                    <div class="field">
                        <label>Industries Served *</label>
                        <div class="achip-grid"><?php reg_admin_chips('industries', bdst_industries(), $indsStd); ?></div>
                        <input type="text" name="industries_other" placeholder="Other (comma separated)"
                               value="<?= bdst_e(implode(', ', $indsCustom)) ?>">
                        <?php if (isset($errors['industries'])): ?><small class="field-error"><?= bdst_e($errors['industries']) ?></small><?php endif; ?>
                    </div>
                    <div class="field">
                        <label>Client Segments *</label>
                        <div class="achip-grid"><?php reg_admin_chips('client_segments', bdst_client_segments(), $segStd); ?></div>
                        <input type="text" name="client_segments_other" placeholder="Other (comma separated)"
                               value="<?= bdst_e(implode(', ', $segCustom)) ?>">
                        <?php if (isset($errors['client_segments'])): ?><small class="field-error"><?= bdst_e($errors['client_segments']) ?></small><?php endif; ?>
                    </div>
                    <div class="field">
                        <label>Nature of Services *</label>
                        <div class="achip-grid"><?php reg_admin_chips('service_nature', bdst_service_natures(), $natStd); ?></div>
                        <input type="text" name="service_nature_other" placeholder="Other (comma separated)"
                               value="<?= bdst_e(implode(', ', $natCustom)) ?>">
                        <?php if (isset($errors['service_nature'])): ?><small class="field-error"><?= bdst_e($errors['service_nature']) ?></small><?php endif; ?>
                    </div>
                    <div class="field">
                        <label>Zones Covered *</label>
                        <div class="achip-grid"><?php reg_admin_chips('zones', bdst_zones(), $zonesStd); ?></div>
                        <input type="text" name="zones_other" placeholder="Other (comma separated)"
                               value="<?= bdst_e(implode(', ', $zonesCustom)) ?>">
                        <?php if (isset($errors['zones'])): ?><small class="field-error"><?= bdst_e($errors['zones']) ?></small><?php endif; ?>
                    </div>
                    <div class="form-grid">
                        <div class="field">
                            <label>Professional Certification? *</label>
                            <select name="has_certification" id="hasCert" required>
                                <option value="yes"<?= $reg['has_certification'] === 'yes' ? ' selected' : '' ?>>Yes</option>
                                <option value="no"<?= $reg['has_certification'] === 'no' ? ' selected' : '' ?>>No</option>
                            </select>
                        </div>
                    </div>
                    <div id="certBlock" class="field"<?= $reg['has_certification'] === 'no' ? ' hidden' : '' ?>>
                        <label>Certifications Held</label>
                        <div class="achip-grid"><?php reg_admin_chips('certifications', bdst_certification_types(), $certsStd); ?></div>
                        <input type="text" name="certifications_other" placeholder="Other (comma separated)"
                               value="<?= bdst_e(implode(', ', $certsCustom)) ?>">
                    </div>
                </div>

                <div class="panel">
                    <h2 class="panel-title">Review Decision</h2>
                    <div class="form-grid">
                        <div class="field">
                            <label>Status *</label>
                            <select name="status" required>
                                <?php foreach (bdst_registration_statuses() as $opt): ?>
                                    <option value="<?= bdst_e($opt) ?>"<?= $reg['status'] === $opt ? ' selected' : '' ?>><?= bdst_e(ucfirst($opt)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <label>Admin Notes (internal)</label>
                        <textarea name="admin_notes" rows="4" placeholder="Internal notes about this application..."><?= bdst_e((string) $reg['admin_notes']) ?></textarea>
                    </div>
                    <div class="row-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <?php if ($reg['cv_path'] !== null && $reg['cv_path'] !== ''): ?>
                            <a href="cv.php?id=<?= $id ?>" class="btn btn-ghost">Download CV</a>
                        <?php else: ?>
                            <span class="td-muted">No CV uploaded</span>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <div class="panel panel-danger">
                <h2 class="panel-title">Danger Zone</h2>
                <p class="td-muted">Deleting removes this registration and its uploaded CV permanently.</p>
                <form method="post" action="registration_delete.php" onsubmit="return confirm('Delete this registration permanently?');">
                    <input type="hidden" name="csrf" value="<?= bdst_e(bdst_csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-danger">Delete Registration</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
(function () {
    var sel = document.getElementById('hasCert');
    var block = document.getElementById('certBlock');
    if (!sel || !block) return;
    var sync = function () { block.hidden = sel.value === 'no'; };
    sel.addEventListener('change', sync);
})();
</script>
</body>
</html>
