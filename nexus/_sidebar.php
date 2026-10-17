<?php
declare(strict_types=1);

/**
 * Shared admin sidebar. Call bdst_admin_sidebar('dashboard'|'updates'|
 * 'registrations') right after <body class="admin-body">, and call
 * bdst_admin_nav_toggle() as the first child of .topbar-inner.
 */

/**
 * Hamburger that opens the off-canvas sidebar on small screens.
 *
 * It is rendered inside the topbar rather than next to the sidebar so the two
 * line up on every page, and it is a real <button> so it stays keyboard
 * reachable. Above the mobile breakpoint CSS hides it, and the sidebar is a
 * permanent column again.
 */
function bdst_admin_nav_toggle(): void
{
    ?>
    <button class="nav-toggle" type="button" aria-label="Open navigation"
            aria-controls="adminSidebar" aria-expanded="false" data-nav-toggle>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="3" y1="6" x2="21" y2="6"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
    </button>
    <?php
}

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

    $sectionLabel = function (string $text): void {
        ?>
        <div class="nav-section-label"><?= bdst_e($text) ?></div>
        <?php
    };

    $dashIcon   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>';
    $plusIcon   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>';
    $usersIcon  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
    $siteIcon   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
    $logoutIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
    ?>
    <div class="sidebar-backdrop" data-nav-backdrop aria-hidden="true"></div>
    <aside class="sidebar" id="adminSidebar">
        <a class="sidebar-brand" href="dashboard.php">
            <img src="../assets/images/logos/logo.webp" alt="BDS Tanzania" height="34">
        </a>
        <button class="sidebar-close" type="button" aria-label="Close navigation" data-nav-close>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
        <nav class="sidebar-nav">
            <?php $sectionLabel('Workspace'); ?>
            <?php $link('dashboard', 'dashboard.php', $dashIcon, 'Dashboard'); ?>
            <?php $link('new-update', 'post.php', $plusIcon, 'New Update'); ?>
            <?php $link('registrations', 'registrations.php', $usersIcon, 'Registrations', $pendingCount); ?>
        </nav>
        <div class="sidebar-foot">
            <?php $sectionLabel('System'); ?>
            <?php $link('view-site', '../index.html', $siteIcon, 'View Site'); ?>
            <?php $link('logout', 'logout.php', $logoutIcon, 'Log Out'); ?>
        </div>
    </aside>
    <script>
    /* Mobile sidebar drawer. Everything here is optional: with JavaScript off
       the sidebar is simply always visible, which is the pre-existing desktop
       behaviour.

       The toggle lives in the topbar, which is parsed *after* this include, so
       the wiring has to wait for the document to finish parsing. */
    (function () {
        function init() {
            var toggle = document.querySelector('[data-nav-toggle]');
            var sidebar = document.getElementById('adminSidebar');
            var backdrop = document.querySelector('[data-nav-backdrop]');
            var closeBtn = document.querySelector('[data-nav-close]');
            if (!toggle || !sidebar) {
                return;
            }

            var wide = window.matchMedia('(min-width: 821px)');

            function isOpen() {
                return document.body.classList.contains('nav-open');
            }

            /* One place decides whether the navigation is reachable. On a
               desktop the sidebar is a permanent column, so it must never be
               aria-hidden; on mobile it is hidden until the drawer is opened.
               aria-hidden is belt-and-braces here — visibility: hidden has
               already taken the closed drawer out of the tab order. */
            function sync() {
                var desktop = wide.matches;
                var open = !desktop && isOpen();

                if (desktop) {
                    document.body.classList.remove('nav-open');
                    sidebar.removeAttribute('aria-hidden');
                } else {
                    sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
                }

                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
            }

            function setOpen(open, focusTarget) {
                document.body.classList.toggle('nav-open', open);
                sync();

                if (focusTarget === 'in' && closeBtn) {
                    closeBtn.focus();
                } else if (focusTarget === 'out') {
                    toggle.focus();
                }
            }

            sync();

            toggle.addEventListener('click', function () {
                setOpen(!isOpen(), !isOpen() ? 'in' : 'out');
            });

            if (closeBtn) {
                closeBtn.addEventListener('click', function () { setOpen(false, 'out'); });
            }

            if (backdrop) {
                backdrop.addEventListener('click', function () { setOpen(false, 'out'); });
            }

            // Following a link closes the drawer so the new page is not covered.
            sidebar.addEventListener('click', function (ev) {
                if (ev.target && ev.target.closest && ev.target.closest('a')) {
                    setOpen(false);
                }
            });

            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape' && isOpen()) {
                    setOpen(false, 'out');
                }
            });

            // Growing past the breakpoint must not strand an open drawer.
            if (wide.addEventListener) {
                wide.addEventListener('change', sync);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    </script>
    <?php
}
