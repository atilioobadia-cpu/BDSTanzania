<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/forms.php';
require __DIR__ . '/_layout.php';

$ref    = trim((string) ($_GET['ref'] ?? ''));
$reg    = null;

if ($ref !== '') {
    try {
        $stmt = bdst_db()->prepare(
            "SELECT reference_no, full_name, email, status, submitted_at
             FROM bds_registrations
             WHERE reference_no = :ref
             LIMIT 1"
        );
        $stmt->execute([':ref' => $ref]);
        $reg = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('Registration lookup error: ' . $e->getMessage());
    }
}

$metaDescription = 'Your BDS Tanzania membership registration has been received. Keep your reference number for follow-up.';
bdst_reg_page_start('Registration Received | BDS Tanzania Membership', $metaDescription, 'Registration Received');
?>

<section class="tj-blog-section section-gap">
    <div class="container">
        <?php if ($reg !== null): ?>
            <div class="reg-panel wow fadeInUp" data-wow-delay=".1s">
                <span class="reg-panel-icon ok"><i class="fa-solid fa-check"></i></span>
                <h2>Registration Successfully Submitted!</h2>
                <p>
                    Thank you, <strong style="color:#0c1e21;"><?= htmlspecialchars($reg['full_name'], ENT_QUOTES, 'UTF-8') ?></strong>.
                    Your registration has been sent to BDS Management and is now
                    <span class="reg-badge reg-badge-<?= htmlspecialchars($reg['status'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars(bdst_reg_status_label($reg['status']), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </p>

                <div class="reg-ref-box">
                    <small>Your Reference Number</small>
                    <strong><?= htmlspecialchars($reg['reference_no'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <ul class="reg-steps">
                    <li><i class="fa-solid fa-circle-check"></i> A confirmation email has been sent to
                        <strong><?= htmlspecialchars($reg['email'], ENT_QUOTES, 'UTF-8') ?></strong>.</li>
                    <li><i class="fa-solid fa-magnifying-glass"></i> The Secretariat will review your application for
                        eligibility and membership category.</li>
                    <li><i class="fa-solid fa-key"></i> Keep your reference number safe ??? use it any time to check your
                        registration status.</li>
                    <li><i class="fa-solid fa-envelope-open-text"></i> Admission decisions are communicated formally in
                        writing.</li>
                </ul>

                <div class="reg-actions">
                    <a class="reg-btn reg-btn-primary" href="check.php?email=<?= urlencode($reg['email']) ?>">
                        <i class="fa-solid fa-magnifying-glass"></i> Check Status
                    </a>
                    <a class="reg-btn reg-btn-outline" href="../index.html">
                        <i class="tji-home"></i> Back to Home
                    </a>
                </div>
            </div>

            <dialog id="regConfirmModal" class="reg-modal">
                <div class="reg-modal-box">
                    <button type="button" class="reg-modal-close" id="regModalClose" aria-label="Close">&times;</button>
                    <span class="reg-panel-icon ok"><i class="fa-solid fa-check"></i></span>
                    <h3>Registration Received!</h3>
                    <p>
                        Thank you, <strong><?= htmlspecialchars($reg['full_name'], ENT_QUOTES, 'UTF-8') ?></strong>.
                        Your application has been submitted successfully. Keep your reference number safe ???
                        you can use it at any time to check your registration status.
                    </p>
                    <div class="reg-ref-box">
                        <small>Reference Number</small>
                        <strong><?= htmlspecialchars($reg['reference_no'], ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div class="reg-actions">
                        <a class="reg-btn reg-btn-primary" href="check.php?email=<?= urlencode($reg['email']) ?>">Check Status</a>
                        <button type="button" class="reg-btn reg-btn-outline" id="regModalOk">Continue</button>
                    </div>
                </div>
            </dialog>
            <script>
                (function () {
                    var m = document.getElementById('regConfirmModal');
                    if (!m || sessionStorage.getItem('regModalShown')) {
                        return;
                    }
                    sessionStorage.setItem('regModalShown', '1');
                    m.showModal();
                    document.getElementById('regModalOk').addEventListener('click', function () { m.close(); });
                    document.getElementById('regModalClose').addEventListener('click', function () { m.close(); });
                })();
            </script>
        <?php else: ?>
            <div class="reg-panel wow fadeInUp" data-wow-delay=".1s">
                <span class="reg-panel-icon info"><i class="fa-solid fa-circle-info"></i></span>
                <h2>Reference Not Found</h2>
                <p>We could not find a registration for this link. If you have just submitted the form, please check
                    the confirmation email we sent you, or verify your status using your reference number.</p>
                <div class="reg-actions">
                    <a class="reg-btn reg-btn-primary" href="check.php">
                        <i class="fa-solid fa-magnifying-glass"></i> Check Your Status
                    </a>
                    <a class="reg-btn reg-btn-outline" href="index.php">
                        <i class="fa-solid fa-user-plus"></i> Register
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php bdst_reg_page_end(); ?>
