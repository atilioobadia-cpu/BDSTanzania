<?php
declare(strict_types=1);

/**
 * Shared admin sidebar. Call bdst_admin_sidebar('dashboard'|'updates'|
 * 'registrations') right after <body class="admin-body">.
 */

function bdst_admin_sidebar(string $active): void
{
    $pendingCount = 0;
    try {
        $pendingCount = (int) bdst_db()
            ->query("SELECT COUNT(*) FROM bds_registrations WHERE status = 'pending'")
            ->fetchColumn();
    } catch (Throwable $e) {
        error_log('Sidebar pending count error: ' . $e->getMessage());
    }

    $link = function (string $key, string $href, string $icon, string $label, int $badge = 0) use ($active): void {
        ?>
        <a class="nav-link<?= $key === $active ? ' active' : '' ?>" href="<?= bdst_e($href) ?>">
            <span class="nav-icon"><?= $icon ?></span>
            <span><?= bdst_e($label) ?></span>
            <?php if ($badge > 0): ?>
                <span class="nav-badge"><?= $badge ?></span>
            <?php endif; ?>
        </a>
        <?php
    };

    $dashIcon   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>';
    $plusIcon   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>';
    $usersIcon  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
    $siteIcon   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
    $logoutIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
    ?>
    <aside class="sidebar">
        <a class="sidebar-brand" href="dashboard.php">
            <img src="../assets/images/logos/logo.webp" alt="BDS Tanzania" height="34">
        </a>
        <nav class="sidebar-nav">
            <?php $link('dashboard', 'dashboard.php', $dashIcon, 'Dashboard'); ?>
            <?php $link('new-update', 'post.php', $plusIcon, 'New Update'); ?>
            <?php $link('registrations', 'registrations.php', $usersIcon, 'Registrations', $pendingCount); ?>
        </nav>
        <div class="sidebar-foot">
            <?php $link('view-site', '../index.html', $siteIcon, 'View Site'); ?>
            <?php $link('logout', 'logout.php', $logoutIcon, 'Log Out'); ?>
        </div>
    </aside>
    <?php
}
