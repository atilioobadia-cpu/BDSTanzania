<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/forms.php';
require __DIR__ . '/../includes/approval-docs.php';
require __DIR__ . '/_layout.php';

/**
 * Accepts "0019", "19", or a full "BDS-TZ-2026-0019" paste and returns the
 * canonical reference (or '' when nothing usable was entered).
 */
function bdst_normalize_reference(string $raw, string $prefix): string
{
    $raw = strtoupper(trim($raw));
    if ($raw === '') {
        return '';
    }
    if (str_starts_with($raw, 'BDS-TZ-')) {
        return $raw;
    }
    if (!ctype_digit(ltrim($raw, '0')) || ltrim($raw, '0') === '') {
        return '';
    }
    if (strlen($raw) > 10) {
        return '';
    }

    return $prefix . str_pad((string) (int) $raw, 4, '0', STR_PAD_LEFT);
}

$refPrefix = 'BDS-TZ-' . date('Y') . '-';
$refInput  = trim((string) ($_GET['ref'] ?? ''));
$checked   = false;
$reg       = null;
$notFound  = false;

if ($refInput !== '') {
    $ref = bdst_normalize_reference($refInput, $refPrefix);
    $checked = true;
    if ($ref === '') {
        $notFound = true;
    } else {
        try {
            $stmt = bdst_db()->prepare(
                "SELECT id, reference_no, full_name, status, submitted_at, region, district, rejection_reason
                 FROM bds_registrations
                 WHERE reference_no = :ref
                 LIMIT 1"
            );
            $stmt->execute([':ref' => $ref]);
            $reg = $stmt->fetch() ?: null;
            $notFound = $reg === null;
        } catch (Throwable $e) {
            error_log('Status check error: ' . $e->getMessage());
            $notFound = true;
        }
    }
}

$metaDescription = 'Check the status of your BDS Tanzania membership registration using your reference number.';
bdst_reg_page_start('Check Registration Status | BDS Tanzania Membership', $metaDescription, 'Check Status');
?>

<section class="tj-blog-section section-gap">
    <div class="container">
        <div class="reg-panel wow fadeInUp" data-wow-delay=".1s">
            <div style="margin-bottom:26px;">
                <span class="reg-panel-icon info"><i class="fa-solid fa-magnifying-glass"></i></span>
                <h2>Check Your Registration Status</h2>
                <p>Enter your reference number — it is on the confirmation email you received after registering.</p>
            </div>

            <form method="get" action="check.php">
                <div class="reg-field">
                    <label class="reg-label" for="refNum">
                        <span class="reg-label-main">Reference Number <span class="reg-req">*</span></span>
                    </label>
                    <div class="ref-input-group">
                        <span class="ref-prefix"><?= htmlspecialchars($refPrefix, ENT_QUOTES, 'UTF-8') ?></span>
                        <input class="reg-control ref-num" type="text" id="refNum" name="ref"
                               maxlength="4" inputmode="numeric" pattern="[0-9]*" autocomplete="off"
                               placeholder="0000" required
                               value="<?= htmlspecialchars(preg_replace('/^' . preg_quote($refPrefix, '/') . '/', '', strtoupper(trim($refInput))), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <small class="reg-hint">Just type your number — for example <strong>0019</strong>. You can also paste the full reference.</small>
                </div>
                <div style="text-align:center;">
                    <button type="submit" class="reg-btn reg-btn-primary">
                        <i class="fa-solid fa-magnifying-glass"></i> Check Status
                    </button>
                </div>
            </form>

            <?php if ($checked && $reg !== null): ?>
                <div style="margin-top:34px;background:#ecf0f0;border-radius:14px;padding:28px 30px;text-align:left;">
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
                        <p style="margin:14px 0 0;color:#364e52;font-size:14px;line-height:1.7;">
                            <i class="fa-solid fa-circle-check" style="color:#1e7e34;"></i>
                            Congratulations! Your membership has been approved. The Secretariat will contact you with
                            the next steps to activate your membership.
                        </p>

                        <?php $docs = bdst_approval_docs(); ?>
                        <?php if ($docs !== []): ?>
                            <div class="doc-section">
                                <h3 class="doc-section-title"><i class="fa-solid fa-file-shield"></i> Your Membership Documents</h3>
                                <p class="doc-section-sub">The same documents were sent to your email after approval — download them here any time.</p>
                                <ul class="doc-list">
                                    <?php foreach ($docs as $slug => $doc): ?>
                                        <li class="doc-item">
                                            <span class="doc-icon"><i class="fa-solid fa-file-pdf"></i></span>
                                            <span class="doc-meta">
                                                <strong><?= htmlspecialchars((string) $doc['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <small><?= htmlspecialchars((string) $doc['description'], ENT_QUOTES, 'UTF-8') ?></small>
                                            </span>
                                            <a class="doc-btn" href="download.php?ref=<?= urlencode((string) $reg['reference_no']) ?>&amp;doc=<?= urlencode((string) $slug) ?>">
                                                <i class="fa-solid fa-download"></i> Download
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="margin:18px 0 0;color:#364e52;font-size:14px;line-height:1.7;">
                            <i class="fa-solid fa-circle-xmark" style="color:#c0392b;"></i>
                            Unfortunately, your application has been <strong>Rejected</strong>.
                        </p>
                        <?php $reason = trim((string) ($reg['rejection_reason'] ?? '')); ?>
                        <?php if ($reason !== ''): ?>
                            <div style="margin-top:14px;background:#fdf7f6;border-left:4px solid #c0392b;border-radius:0 10px 10px 0;padding:14px 18px;">
                                <small style="display:block;letter-spacing:.08em;color:#c0392b;font-weight:700;text-transform:uppercase;font-size:11px;">Reason</small>
                                <p style="margin:6px 0 0;color:#364e52;font-size:14px;line-height:1.7;"><?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        <?php endif; ?>
                        <p style="margin:14px 0 0;color:#364e52;font-size:14px;line-height:1.7;">
                            For details or to re-apply, please contact the Secretariat at
                            <a href="mailto:info@bdst.or.tz" style="color:#1997C6;">info@bdst.or.tz</a>.
                        </p>
                    <?php endif; ?>
                </div>
            <?php elseif ($checked && $notFound): ?>
                <div class="reg-alert reg-alert-error" style="margin-top:30px;" role="alert">
                    <strong><i class="fa-solid fa-circle-exclamation"></i> No registration found for that reference number.</strong><br>
                    Please double-check it against your confirmation email. If you still cannot find it, contact
                    the Secretariat at
                    <a href="mailto:info@bdst.or.tz" style="color:#1997C6;">info@bdst.or.tz</a>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php bdst_reg_page_end(); ?>
