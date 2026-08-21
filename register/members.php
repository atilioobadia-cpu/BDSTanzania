<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/forms.php';
require __DIR__ . '/_layout.php';

$search = trim((string) ($_GET['q'] ?? ''));
$region = trim((string) ($_GET['region'] ?? ''));
$members = [];
$dbError = false;

try {
    $pdo    = bdst_db();
    $where  = "status = 'approved'";
    $params = [];

    if ($search !== '') {
        $where .= " AND (full_name LIKE :q1 OR professional_status LIKE :q2 OR firm_name LIKE :q3
                      OR service_areas LIKE :q4 OR industries LIKE :q5)";
        $q = '%' . $search . '%';
        $params += [':q1' => $q, ':q2' => $q, ':q3' => $q, ':q4' => $q, ':q5' => $q];
    }
    if ($region !== '' && in_array($region, bdst_regions(), true)) {
        $where .= ' AND region = :region';
        $params[':region'] = $region;
    }

    $stmt = $pdo->prepare(
        "SELECT full_name, professional_status, firm_name, region, district,
                experience_years, service_areas, industries, client_segments, zones
         FROM bds_registrations
         WHERE $where
         ORDER BY full_name ASC"
    );
    $stmt->execute($params);
    $members = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Member directory error: ' . $e->getMessage());
    $dbError = true;
}

function member_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $initials !== '' ? $initials : 'BD';
}

function member_tags(array $row, string $column, int $limit): array
{
    $tags = bdst_decode_list($row[$column] ?? null);
    return array_slice($tags, 0, $limit);
}

$metaDescription = 'Explore the BDS Tanzania member directory - verified business development service professionals across all regions and zones of Tanzania.';
bdst_reg_page_start('Member Directory | BDS Tanzania Professionals', $metaDescription, 'Member Directory');
?>

<section class="tj-blog-section section-gap">
    <div class="container">
        <div class="reg-intro wow fadeInUp" data-wow-delay=".1s">
            <h2 style="font-size:30px;font-weight:800;color:#0c1e21;margin-bottom:14px;">BDS Tanzania Member Directory</h2>
            <p>
                Verified business development service professionals admitted by BDS Management. Members are profiled by
                expertise, sector experience and geographic coverage — connect through the Secretariat for engagements,
                programs and partnerships.
            </p>
        </div>

        <form class="reg-toolbar wow fadeInUp" data-wow-delay=".15s" method="get" action="members.php">
            <div class="search-box">
                <input type="text" name="q" placeholder="Search by name, profession or expertise..."
                       value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <select name="region">
                <option value="">All Regions</option>
                <?php foreach (bdst_regions() as $regionOption): ?>
                    <option value="<?= htmlspecialchars($regionOption, ENT_QUOTES, 'UTF-8') ?>"<?= $region === $regionOption ? ' selected' : '' ?>>
                        <?= htmlspecialchars($regionOption, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="reg-btn reg-btn-primary"><i class="tji-search"></i> Search</button>
        </form>

        <?php if ($dbError): ?>
            <div class="member-empty wow fadeInUp">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <h3>Directory Temporarily Unavailable</h3>
                <p>Please try again shortly.</p>
            </div>
        <?php elseif ($members === []): ?>
            <div class="member-empty wow fadeInUp">
                <i class="fa-solid fa-users"></i>
                <h3>No Members Found</h3>
                <p>
                    <?= $search !== '' || $region !== ''
                        ? 'No members match your search. Try different keywords or clear the filters.'
                        : 'The directory is being prepared. Approved members will appear here soon.' ?>
                </p>
                <?php if ($search !== '' || $region !== ''): ?>
                    <a class="reg-btn reg-btn-outline" href="members.php" style="margin-top:16px;">Clear Filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="member-grid">
                <?php foreach ($members as $member): ?>
                    <div class="member-card wow fadeInUp" data-wow-delay=".1s">
                        <span class="member-avatar"><?= htmlspecialchars(member_initials((string) $member['full_name']), ENT_QUOTES, 'UTF-8') ?></span>
                        <h3 class="member-name"><?= htmlspecialchars((string) $member['full_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <div class="member-role">
                            <?= htmlspecialchars((string) $member['professional_status'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if (($member['firm_name'] ?? '') !== ''): ?>
                                &middot; <?= htmlspecialchars((string) $member['firm_name'], ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </div>
                        <div class="member-meta">
                            <span><i class="tji-location"></i> <?= htmlspecialchars((string) $member['district'], ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars((string) $member['region'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span><i class="fa-solid fa-briefcase"></i> <?= htmlspecialchars((string) $member['experience_years'], ENT_QUOTES, 'UTF-8') ?> experience</span>
                            <span><i class="fa-solid fa-map-location-dot"></i>
                                <?= count(bdst_decode_list($member['zones'] ?? null)) ?> zone(s) covered</span>
                        </div>
                        <div class="member-tags">
                            <?php foreach (member_tags($member, 'service_areas', 4) as $tag): ?>
                                <span class="member-tag"><?= htmlspecialchars((string) $tag, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                            <?php foreach (member_tags($member, 'industries', 2) as $tag): ?>
                                <span class="member-tag"><?= htmlspecialchars((string) $tag, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                            <?php $extra = count(bdst_decode_list($member['service_areas'] ?? null)) + count(bdst_decode_list($member['industries'] ?? null)) - 6; ?>
                            <?php if ($extra > 0): ?>
                                <span class="member-tag">+<?= $extra ?> more</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php bdst_reg_page_end(); ?>
