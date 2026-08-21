<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/forms.php';
require __DIR__ . '/_layout.php';

$email    = trim((string) ($_GET['email'] ?? ''));
$checked  = false;
$reg      = null;
$notFound = false;
$invalid  = false;

if ($email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $checked = true;
        $invalid = true;
    } else {
        try {
            $stmt = bdst_db()->prepare(
                "SELECT reference_no, full_name, status, submitted_at, region, district
                 FROM bds_registrations
                 WHERE email = :email
                 ORDER BY id DESC
                 LIMIT 1"
            );
            $stmt->execute([':email' => strtolower($email)]);
            $reg = $stmt->fetch() ?: null;
            $checked = true;
            $notFound = $reg === null;
        } catch (Throwable $e) {
            error_log('Status check error: ' . $e->getMessage());
            $checked = true;
            $notFound = true;
        }
    }
}

$metaDescription = 'Check the status of your BDS Tanzania membership registration using your email address.';
bdst_reg_page_start('Check Registration Status | BDS Tanzania Membership', $metaDescription, 'Check Status');
?>

<section class="tj-blog-section section-gap">
    <div class="container">
        <div class="reg-panel wow fadeInUp" data-wow-delay=".1s" style="text-align:left;">
            <div style="text-align:center;margin-bottom:26px;">
                <span class="reg-panel-icon info"><i class="fa-solid fa-magnifying-glass"></i></span>
                <h2>Check Your Registration Status</h2>
                <p>Enter the email address you used when registering — your reference number and current status will be shown.</p>
            </div>

            <form method="get" action="check.php">
                <div class="reg-field">
                    <label class="reg-label" for="email">
                        <span class="reg-label-main">Email Address <span class="reg-req">*</span></span>
                    </label>
                    <input class="reg-control" type="email" id="email" name="email" maxlength="150"
                           placeholder="The email used during registration" required
                           value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div style="text-align:center;">
                    <button type="submit" class="reg-btn reg-btn-primary">
                        <i class="fa-solid fa-magnifying-glass"></i> Check Status
                    </button>
                </div>
            </form>

            <?php if ($checked): ?>
                <?php if ($reg !== null): ?>
                    <div style="margin-top:34px;background:#ecf0f0;border-radius:14px;padding:28px 30px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                            <div>
                                <small style="letter-spacing:.08em;color:#67787a;font-weight:700;text-transform:uppercase;font-size:11.5px;">Reference</small>
                                <div style="font-size:20px;font-weight:800;color:#0c1e21;"><?= htmlspecialchars($reg['reference_no'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div style="font-size:14.5px;color:#364e52;margin-top:4px;">
                                    <?= htmlspecialchars($reg['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                    &middot; <?= htmlspecialchars($reg['region'], ENT_QUOTES, 'UTF-8') ?>,
                                    <?= htmlspecialchars($reg['district'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div style="font-size:13px;color:#67787a;margin-top:2px;">
                                    Submitted: <?= htmlspecialchars(date('j F Y', strtotime((string) $reg['submitted_at'])), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <span class="reg-badge reg-badge-<?= htmlspecialchars($reg['status'], ENT_QUOTES, 'UTF-8') ?>" style="font-size:15px;padding:10px 24px;">
                                <?= htmlspecialchars(bdst_reg_status_label($reg['status']), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <?php if ($reg['status'] === 'pending'): ?>
                            <p style="margin:18px 0 0;color:#364e52;font-size:14px;line-height:1.7;">
                                <i class="fa-solid fa-clock" style="color:#1997C6;"></i>
                                Your registration is in the queue for review by the Secretariat. Decisions are communicated
                                formally in writing once the review is complete.
                            </p>
                        <?php elseif ($reg['status'] === 'reviewed'): ?>
                            <p style="margin:18px 0 0;color:#364e52;font-size:14px;line-height:1.7;">
                                <i class="fa-solid fa-eye" style="color:#1997C6;"></i>
                                Your application has been reviewed. The final admission decision will be communicated to you shortly.
                            </p>
                        <?php elseif ($reg['status'] === 'approved'): ?>
                            <p style="margin:18px 0 0;color:#364e52;font-size:14px;line-height:1.7;">
                                <i class="fa-solid fa-circle-check" style="color:#1e7e34;"></i>
                                Congratulations! Your membership has been approved. The Secretariat will contact you with
                                the next steps to activate your membership.
                            </p>
                        <?php else: ?>
                            <p style="margin:18px 0 0;color:#364e52;font-size:14px;line-height:1.7;">
                                <i class="fa-solid fa-circle-xmark" style="color:#c0392b;"></i>
                                For details regarding this decision, please contact the Secretariat at
                                <a href="mailto:info@bdst.or.tz" style="color:#1997C6;">info@bdst.or.tz</a>.
                            </p>
                        <?php endif; ?>
                    </div>
                <?php elseif ($notFound): ?>
                    <div class="reg-alert reg-alert-error" style="margin-top:30px;" role="alert">
                        <strong><i class="fa-solid fa-circle-exclamation"></i> No registration found for this email.</strong><br>
                        Please double-check the address you used when registering. If you cannot find yours, contact
                        the Secretariat at
                        <a href="mailto:info@bdst.or.tz" style="color:#1997C6;">info@bdst.or.tz</a>.
                    </div>
                <?php elseif ($invalid): ?>
                    <div class="reg-alert reg-alert-error" style="margin-top:30px;" role="alert">
                        <strong><i class="fa-solid fa-circle-exclamation"></i> That email address does not look valid.</strong><br>
                        Please check it and try again.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php bdst_reg_page_end(); ?>
