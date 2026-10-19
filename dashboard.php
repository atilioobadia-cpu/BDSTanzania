<?php
declare(strict_types=1);

/**
 * Public member map.
 *
 * Shows approved-member statistics by region and district on an interactive
 * Leaflet map, using the official boundaries in assets/geo/ and the reference
 * table in includes/geo.php. Every figure comes from includes/dashboard.php,
 * which enforces the privacy thresholds (see that file for the rules).
 *
 * The page is indexable; the JSON endpoints it reads are not.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/geo.php';
require_once __DIR__ . '/includes/dashboard.php';

/**
 * Cache-busting token for a page asset, so a changed stylesheet or script is
 * never served from a stale browser or CDN cache. Mirrors bdst_admin_css().
 */
function bdst_dash_asset(string $path): string
{
    $file = __DIR__ . '/' . $path;

    return is_file($file) ? (string) filemtime($file) : '0';
}

$summary = null;
$dbError = false;

try {
    $summary = bdst_dashboard_public(bdst_dashboard_data(bdst_db()));
} catch (Throwable $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    $dbError = true;
}

$regionTotal   = count(bdst_official_regions());
$districtTotal = 0;
foreach (bdst_official_districts() as $districtList) {
    $districtTotal += count($districtList);
}

$tableRows  = [];
$rankedRows = [];
$zeroRows   = [];
$maxCount   = 0;
$topRegion  = null;

if ($summary !== null) {
    // One shared order for the ranked list and the table.
    $tableRows = bdst_dashboard_sort_regions($summary['regions']);
    $maxCount  = (int) $summary['max_count'];

    foreach ($tableRows as $row) {
        if ($row['count'] !== null && (int) $row['count'] > 0) {
            $rankedRows[] = $row;
            if ($topRegion === null) {
                $topRegion = $row;
            }
        } else {
            $zeroRows[] = $row;
        }
    }
}

/**
 * Registration trend (Phase 4). Server-rendered so the numbers are present
 * without JavaScript; dashboard.js swaps the period and redraws the chart.
 */
$trends = [
    'period'    => 'monthly',
    'published' => false,
    'total'     => null,
    'peak'      => 0,
    'buckets'   => [],
];

if ($summary !== null) {
    try {
        $trends = bdst_dashboard_trends(
            bdst_db(),
            (int) $summary['thresholds']['min_total'],
            'monthly'
        );
    } catch (Throwable $e) {
        error_log('Dashboard trends error: ' . $e->getMessage());
    }
}

$trendBuckets  = $trends['published'] ? $trends['buckets'] : [];
$trendSummary  = '';
if ($trendBuckets !== []) {
    $trendFirst   = $trendBuckets[0];
    $trendLast    = $trendBuckets[count($trendBuckets) - 1];
    $trendSummary = $trendFirst['label'] . ' to ' . $trendLast['label'];
}

/** "12 members" / "1 member". */
function bdst_dash_members(int $count): string
{
    return number_format($count) . ($count === 1 ? ' member' : ' members');
}

/** Text for a region's "approved members" cell. */
function bdst_dash_cell(array $region): string
{
    if ($region['count'] === null) {
        return (string) $region['label'];
    }
    return number_format((int) $region['count']);
}

/**
 * One clickable row in the ranked region list.
 *
 * The same markup is produced server-side (so it works without JavaScript)
 * and consumed by dashboard.js, which only filters and highlights it.
 */
function bdst_dash_rank_row(array $row, ?int $rank, int $max, bool $zero): string
{
    $count   = $row['count'] === null ? null : (int) $row['count'];
    $percent = $count === null ? 0 : bdst_dashboard_bar_percent($count, $max);
    $display = $count === null ? (string) $row['label'] : number_format($count);
    $srCount = $count === null ? '' : ($count === 1 ? ' member' : ' members');

    return '<li class="bdst-row' . ($zero ? ' bdst-row--zero' : '') . '"'
        . ' data-code="' . bdst_e($row['code']) . '"'
        . ' data-name="' . bdst_e($row['name']) . '"'
        . ' data-count="' . ($count === null ? '' : $count) . '"'
        . ($zero ? ' data-zero="1"' : '') . '>'
        . '<button type="button" class="bdst-row-btn">'
        . '<span class="bdst-row-rank" aria-hidden="true">' . ($rank === null ? '&ndash;' : (int) $rank) . '</span>'
        . '<span class="bdst-row-body">'
        . '<span class="bdst-row-name">' . bdst_e($row['name']) . '</span>'
        . '<span class="bdst-row-track"><span class="bdst-row-fill" style="width:' . $percent . '%"></span></span>'
        . '</span>'
        . '<span class="bdst-row-count">' . bdst_e($display)
        . ($srCount === '' ? '' : '<span class="visually-hidden">' . $srCount . '</span>')
        . '</span>'
        . '</button>'
        . '</li>';
}

?>
<!doctype html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Member Map SEO -->
    <meta name="description" content="Interactive map of BDS Tanzania members by region and district. Aggregate member counts only - no names or addresses.">
    <meta name="keywords" content="BDS Tanzania members map, business development professionals Tanzania, member distribution by region, BDS Tanzania statistics">
    <meta name="author" content="BDS Tanzania">
    <meta name="copyright" content="BDS Tanzania. Developed by Atilio Obadia.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://www.bdst.or.tz/dashboard">

    <meta property="og:type" content="website">
    <meta property="og:title" content="Member Map | BDS Tanzania Members by Region">
    <meta property="og:description" content="Where BDS Tanzania members are based, region by region. Aggregate member counts only.">
    <meta property="og:url" content="https://www.bdst.or.tz/dashboard">
    <meta property="og:site_name" content="BDS Tanzania">
    <meta property="og:image" content="https://www.bdst.or.tz/assets/images/logos/logo.webp">
    <meta property="og:copyright" content="BDS Tanzania. Developed by Atilio Obadia.">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Member Map | BDS Tanzania Members by Region">
    <meta name="twitter:description" content="Where BDS Tanzania members are based, region by region. Aggregate member counts only.">
    <meta name="twitter:image" content="https://www.bdst.or.tz/assets/images/logos/logo.webp">
    <meta name="twitter:creator" content="@BDS_Tanzania">

    <title>Member Map | BDS Tanzania Members by Region and District</title>

    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Dataset",
            "name": "BDS Tanzania members by region",
            "description": "Aggregate counts of approved BDS Tanzania members by region and district. Individual members are not identified.",
            "url": "https://www.bdst.or.tz/dashboard",
            "spatialCoverage": { "@type": "Place", "name": "Tanzania" },
            "creator": { 
                "@type": "Organization", 
                "name": "Business Development Society of Tanzania",
                "copyrightHolder": { "@type": "Organization", "name": "BDS Tanzania" },
                "author": { "@type": "Person", "name": "Atilio Obadia", "role": "Developer" }
            },
            "copyrightHolder": { "@type": "Organization", "name": "BDS Tanzania" },
            "copyrightYear": "2026",
            "license": "https://www.bdst.or.tz/terms-and-conditions.html",
            "isAccessibleForFree": true
        }
    </script>

    <link rel="shortcut icon" type="image/x-icon" href="assets/images/fav.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Map tiles (Street + Satellite) - open the connections early so the
         first map tiles arrive faster. -->
    <link rel="preconnect" href="https://tile.openstreetmap.org">
    <link rel="preconnect" href="https://server.arcgisonline.com">
    <link rel="dns-prefetch" href="https://tile.openstreetmap.org">
    <link rel="dns-prefetch" href="https://server.arcgisonline.com">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/font-awesome-pro.min.css">
    <link rel="stylesheet" href="assets/css/animate.min.css">
    <link rel="stylesheet" href="assets/css/bexon-icons.css">
    <link rel="stylesheet" href="assets/css/meanmenu.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/vendor/leaflet/leaflet.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= bdst_dash_asset('assets/css/dashboard.css') ?>">
</head>

<body>
    <div class="body-overlay"></div>

    <!-- Skip link: first focusable element so keyboard/Screen-reader users can
         jump past the shared header straight to the dashboard content. -->
    <a class="bdst-skiplink" href="#primary">Skip to main content</a>

    <!-- Back to top -->
    <div class="back-to-top-wrapper">
        <button id="back_to_top" type="button" class="back-to-top-btn" aria-label="Back to top">
            <span><i class="tji-arrow-up-long"></i></span>
        </button>
    </div>

    <!-- Search Popup -->
    <div class="search-popup-overlay"></div>

    <!-- Offcanvas Menu -->
    <div class="tj-offcanvas-area d-none d-lg-block">
        <div class="hamburger_bg"></div>
        <div class="hamburger_wrapper">
            <div class="hamburger_inner">
                <div class="hamburger_top d-flex align-items-center justify-content-between">
                    <div class="hamburger_logo">
                        <a href="index.html" class="mobile_logo">
                            <img src="assets/images/logos/logo.webp" alt="BDS Tanzania Logo" width="160" height="60">
                        </a>
                    </div>
                    <div class="hamburger_close">
                        <button class="hamburger_close_btn" type="button" aria-label="Close menu">
                            <i class="fa-thin fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="offcanvas-text">
                    <p>
                        Tanzania's national professional home for business development service providers; setting standards, building capacity, powering enterprise growth.
                    </p>
                </div>

                <div class="hamburger-search-area">
                    <h3 class="hamburger-title">Search BDS Tanzania</h3>
                    <div class="hamburger_search">
                        <form method="get" action="updates.php">
                            <button type="submit" aria-label="Search">
                                <i class="tji-search"></i>
                            </button>
                            <input type="search" autocomplete="off" name="s" placeholder="Search updates, services, membership...">
                        </form>
                    </div>
                </div>

                <div class="hamburger-infos">
                    <h3 class="hamburger-title">Contact Information</h3>
                    <div class="contact-info">
                        <div class="contact-item">
                            <span class="subtitle">Phone</span>
                            <a class="contact-link" href="tel:+255655211777">+255655211777</a>
                        </div>
                        <div class="contact-item">
                            <span class="subtitle">Email</span>
                            <a class="contact-link" href="mailto:info@bdst.or.tz">info@bdst.or.tz</a>
                        </div>
                        <div class="contact-item">
                            <span class="subtitle">Location</span>
                            <span class="contact-link">Dodoma, Tanzania</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hamburger-socials">
                <h3 class="hamburger-title">Follow BDS Tanzania</h3>
                <div class="social-links style-3">
                    <ul>
                        <li><a href="#" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a></li>
                        <li><a href="#" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a></li>
                        <li><a href="#" target="_blank" rel="noopener noreferrer" aria-label="X"><i class="fa-brands fa-x-twitter"></i></a></li>
                        <li><a href="#" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Hamburger Menu -->
    <div class="hamburger-area d-lg-none">
        <div class="hamburger_bg"></div>
        <div class="hamburger_wrapper">
            <div class="hamburger_inner">
                <div class="hamburger_top d-flex align-items-center justify-content-between">
                    <div class="hamburger_logo">
                        <a href="index.html" class="mobile_logo">
                            <img src="assets/images/logos/logo.webp" alt="BDS Tanzania Logo" width="160" height="60">
                        </a>
                    </div>
                    <div class="hamburger_close">
                        <button class="hamburger_close_btn" type="button" aria-label="Close menu">
                            <i class="fa-thin fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="hamburger_menu">
                    <div class="mobile_menu"></div>
                </div>

                <div class="hamburger-infos">
                    <h3 class="hamburger-title">Contact Information</h3>
                    <div class="contact-info">
                        <div class="contact-item">
                            <span class="subtitle">Phone</span>
                            <a class="contact-link" href="tel:+255655211777">+255655211777</a>
                        </div>
                        <div class="contact-item">
                            <span class="subtitle">Email</span>
                            <a class="contact-link" href="mailto:info@bdst.or.tz">info@bdst.or.tz</a>
                        </div>
                        <div class="contact-item">
                            <span class="subtitle">Location</span>
                            <span class="contact-link">Dodoma, Tanzania</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hamburger-socials">
                <h3 class="hamburger-title">Follow BDS Tanzania</h3>
                <div class="social-links style-3">
                    <ul>
                        <li><a href="#" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a></li>
                        <li><a href="#" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a></li>
                        <li><a href="#" target="_blank" rel="noopener noreferrer" aria-label="X"><i class="fa-brands fa-x-twitter"></i></a></li>
                        <li><a href="#" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Header -->
    <header class="header-area header-1 section-gap-x">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="header-wrapper">
                        <div class="site_logo">
                            <a class="logo" href="index.html">
                                <img src="assets/images/logos/logo.webp" alt="BDS Tanzania Logo" width="160" height="60">
                            </a>
                        </div>

                        <div class="menu-area d-none d-lg-inline-flex align-items-center">
                            <nav id="mobile-menu" class="mainmenu">
                                <ul>
                                    <li><a href="index.html">Home</a></li>

                                    <li class="has-dropdown">
                                        <a href="#">About</a>
                                        <ul class="sub-menu">
                                            <li><a href="about.html">About BDS Tanzania</a></li>
                                            <li><a href="leadership.html">Leadership</a></li>
                                        </ul>
                                    </li>

                                    <li class="has-dropdown">
                                        <a href="#">Services</a>
                                        <ul class="sub-menu">
                                            <li><a href="business-advisory-support.html">Business Advisory Support</a></li>
                                            <li><a href="capacity-building.html">Capacity Building</a></li>
                                            <li><a href="sme-support.html">SME Support</a></li>
                                            <li><a href="professional-development.html">Professional Development</a></li>
                                        </ul>
                                    </li>

                                    <li class="has-dropdown">
                                        <a href="#">Membership</a>
                                        <ul class="sub-menu">
                                            <li><a href="why-join.html">Why Join</a></li>
                                            <li><a href="membership-benefits.html">Membership Benefits</a></li>
                                            <li><a href="how-to-join.html">How to Join</a></li>
                                            <li><a href="register/">Become a Member</a></li>
                                            <li><a href="register/members.php">Member Directory</a></li>
                                            <li><a href="/dashboard">Member Map</a></li>
                                        </ul>
                                    </li>

                                    <li class="has-dropdown">
                                        <a href="#">Updates</a>
                                        <ul class="sub-menu">
                                            <li><a href="updates.php">Latest Updates</a></li>
                                            <li><a href="gallery.html">Gallery</a></li>
                                        </ul>
                                    </li>
                                    <li><a href="contact.html">Contact Us</a></li>
                                </ul>
                            </nav>
                        </div>

                        <div class="header-right-item d-none d-lg-inline-flex">
                            <a class="header-contact d-none d-xl-inline-flex" href="tel:+255655211777">
                                <span class="call-icon"><i class="tji-phone"></i></span>
                                <span class="call-text">+255655211777</span>
                            </a>

                            <div class="header-button">
                                <a class="tj-primary-btn" href="why-join.html">
                                    <span class="btn-text"><span>Why Join Us</span></span>
                                    <span class="btn-icon"><i class="tji-arrow-right-long"></i></span>
                                </a>
                            </div>

                            <div class="menu_bar menu_offcanvas d-none d-lg-inline-flex">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>

                        <div class="menu_bar mobile_menu_bar d-lg-none">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="search_popup">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-8">
                        <div class="tj_search_wrapper">
                            <div class="search_form">
                                <form action="updates.php" method="get">
                                    <div class="search_input">
                                        <div class="search-box">
                                            <input class="search-form-input" type="text" name="s" placeholder="Search BDS Tanzania website" required>
                                            <button type="submit" aria-label="Search">
                                                <i class="tji-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Sticky Header -->
    <header class="header-area header-1 header-duplicate header-sticky section-gap-x">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="header-wrapper">
                        <div class="site_logo">
                            <a class="logo" href="index.html">
                                <img src="assets/images/logos/logo.webp" alt="BDS Tanzania Logo" width="160" height="60">
                            </a>
                        </div>

                        <div class="menu-area d-none d-lg-inline-flex align-items-center">
                            <nav class="mainmenu">
                                <ul>
                                    <li><a href="index.html">Home</a></li>

                                    <li class="has-dropdown">
                                        <a href="#">About</a>
                                        <ul class="sub-menu">
                                            <li><a href="about.html">About BDS Tanzania</a></li>
                                            <li><a href="leadership.html">Leadership</a></li>
                                        </ul>
                                    </li>

                                    <li class="has-dropdown">
                                        <a href="#">Services</a>
                                        <ul class="sub-menu">
                                            <li><a href="business-advisory-support.html">Business Advisory Support</a></li>
                                            <li><a href="capacity-building.html">Capacity Building</a></li>
                                            <li><a href="sme-support.html">SME Support</a></li>
                                            <li><a href="professional-development.html">Professional Development</a></li>
                                        </ul>
                                    </li>

                                    <li class="has-dropdown">
                                        <a href="#">Membership</a>
                                        <ul class="sub-menu">
                                            <li><a href="why-join.html">Why Join</a></li>
                                            <li><a href="membership-benefits.html">Membership Benefits</a></li>
                                            <li><a href="how-to-join.html">How to Join</a></li>
                                            <li><a href="register/">Become a Member</a></li>
                                            <li><a href="register/members.php">Member Directory</a></li>
                                            <li><a href="/dashboard">Member Map</a></li>
                                        </ul>
                                    </li>

                                    <li class="has-dropdown">
                                        <a href="#">Updates</a>
                                        <ul class="sub-menu">
                                            <li><a href="updates.php">Latest Updates</a></li>
                                            <li><a href="gallery.html">Gallery</a></li>
                                        </ul>
                                    </li>
                                    <li><a href="contact.html">Contact Us</a></li>
                                </ul>
                            </nav>
                        </div>

                        <div class="header-right-item d-none d-lg-inline-flex">
                            <div class="header-search">
                                <button class="search" type="button" aria-label="Open search">
                                    <i class="tji-search"></i>
                                </button>
                                <button type="button" class="search_close_btn" aria-label="Close search">
                                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M17 1L1 17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        <path d="M1 1L17 17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="header-button">
                                <a class="tj-primary-btn" href="why-join.html">
                                    <span class="btn-text"><span>Why Join Us</span></span>
                                    <span class="btn-icon"><i class="tji-arrow-right-long"></i></span>
                                </a>
                            </div>

                            <div class="menu_bar menu_offcanvas d-none d-lg-inline-flex">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>

                        <div class="menu_bar mobile_menu_bar d-lg-none">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="search_popup">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-8">
                        <div class="tj_search_wrapper">
                            <div class="search_form">
                                <form action="updates.php" method="get">
                                    <div class="search_input">
                                        <div class="search-box">
                                            <input class="search-form-input" type="text" name="s" placeholder="Search BDS Tanzania website" required>
                                            <button type="submit" aria-label="Search">
                                                <i class="tji-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main id="primary" class="site-main" tabindex="-1">
        <!-- Breadcrumb -->
        <section class="tj-page-header section-gap-x" data-bg-image="assets/images/bg/1.jpg">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="tj-page-header-content text-center">
                            <h1 class="tj-page-title">Member Map</h1>
                            <div class="tj-page-link">
                                <span><i class="tji-home"></i></span>
                                <span><a href="index.html">Home</a></span>
                                <span><i class="tji-arrow-right"></i></span>
                                <span><span>Member Map</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="page-header-overlay" data-bg-image="assets/images/shape/pheader-overlay.jpg"></div>
        </section>

        <!-- National Overview -->
        <div class="bdst-surface">
        <section class="bdst-dash-intro">
            <div class="container">
                <div class="bdst-page-head">
                    <div class="bdst-page-headtext sec-heading">
                        <span class="sub-title wow fadeInUp" data-wow-delay=".1s">
                            <i class="tji-box"></i>National Overview
                        </span>
                        <h2 class="sec-title wow fadeInUp" data-wow-delay=".2s">BDS Tanzania Members Across the Country</h2>
                        <p class="bdst-page-sub wow fadeInUp" data-wow-delay=".3s">
                            Where our approved members are based, region by region &mdash; a clear
                            view of the society's national footprint.
                        </p>
                    </div>
                    <a class="bdst-badge wow fadeInUp" data-wow-delay=".4s" href="privacy-policy.html"
                       title="Only aggregate counts are published. Read the privacy policy.">
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        <span class="bdst-badge-text">Privacy Protected <em>(Aggregated)</em></span>
                    </a>
                </div>
            </div>
        </section>

        <?php if ($dbError): ?>
            <section class="bdst-dash-main">
                <div class="container">
                    <div class="bdst-notice">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <div>
                            <strong>Statistics are temporarily unavailable.</strong>
                            Please try again shortly.
                        </div>
                    </div>
                </div>
            </section>
        <?php else: ?>

        <!-- Key figures -->
        <section class="bdst-dash-kpis">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-3 col-sm-6">
                        <div class="bdst-kpi bdst-kpi--members wow fadeInUp" data-wow-delay=".1s">
                            <span class="bdst-kpi-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="bdst-kpi-body">
                                <span class="bdst-kpi-value" id="bdst-kpi-members"><?= number_format((int) $summary['totals']['members']) ?></span>
                                <span class="bdst-kpi-label">Approved members</span>
                            </span>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <div class="bdst-kpi wow fadeInUp" data-wow-delay=".2s">
                            <span class="bdst-kpi-icon"><i class="fa-solid fa-map-location-dot"></i></span>
                            <span class="bdst-kpi-body">
                                <span class="bdst-kpi-value" id="bdst-kpi-regions"><?= number_format((int) ($summary['totals']['regions_covered'] ?? 0)) ?></span>
                                <span class="bdst-kpi-label">Regions represented</span>
                                <span class="bdst-kpi-sub">of <?= (int) $regionTotal ?> regions</span>
                            </span>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <div class="bdst-kpi wow fadeInUp" data-wow-delay=".3s">
                            <span class="bdst-kpi-icon"><i class="fa-solid fa-location-dot"></i></span>
                            <span class="bdst-kpi-body">
                                <span class="bdst-kpi-value" id="bdst-kpi-districts"><?= number_format((int) ($summary['totals']['districts_covered'] ?? 0)) ?></span>
                                <span class="bdst-kpi-label">Districts represented</span>
                                <span class="bdst-kpi-sub">of <?= (int) $districtTotal ?> districts</span>
                            </span>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <div class="bdst-kpi wow fadeInUp" data-wow-delay=".4s">
                            <span class="bdst-kpi-icon"><i class="fa-solid fa-trophy"></i></span>
                            <span class="bdst-kpi-body">
                                <span class="bdst-kpi-value is-text" id="bdst-kpi-top"><?= $topRegion ? bdst_e($topRegion['name']) : '&ndash;' ?></span>
                                <span class="bdst-kpi-label">Largest region</span>
                                <span class="bdst-kpi-sub" id="bdst-kpi-top-sub"><?= $topRegion ? bdst_e(bdst_dash_members((int) $topRegion['count'])) : 'No members yet' ?></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Map + ranked region panel -->
        <section class="bdst-dash-main">
            <div class="container">
                <?php if (!$summary['published']): ?>
                    <div class="bdst-notice bdst-notice--gate">
                        <i class="fa-solid fa-shield-halved"></i>
                        <div>
                            <strong>Geographic detail is not published.</strong>
                            A cohort floor is configured, so the regional breakdown appears only once
                            the register passes it. Check back soon.
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <div class="col-xl-7">
                        <div class="bdst-map-card">
                            <h2 class="bdst-card-title">Member distribution</h2>
                            <p class="bdst-card-sub">Each green pin is a region, sized by its number of approved members. Choose one to see its districts.</p>
                            <div class="bdst-map-frame">
                                <div id="bdst-map" role="region" aria-label="Map of Tanzania with a green pin for each region, sized by its number of approved members"></div>
                                <div class="bdst-map-pills" id="bdst-map-pills" role="group" aria-label="Map base layer">
                                    <button type="button" class="bdst-map-pill is-active" data-base="street" aria-pressed="true">Street</button>
                                    <button type="button" class="bdst-map-pill" data-base="satellite" aria-pressed="false">Satellite</button>
                                </div>
                                <div class="bdst-map-legend" id="bdst-map-legend">
                                    <span class="bdst-map-legend-title">Region shading</span>
                                    <ul class="bdst-legend" id="bdst-legend">
                                        <?php
                                        $legendEntries = $summary['published']
                                            ? $summary['legend']
                                            : [['key' => 'withheld', 'label' => 'Not published']];
                                        ?>
                                        <?php foreach ($legendEntries as $entry): ?>
                                            <li>
                                                <span class="bdst-swatch bdst-swatch--<?= bdst_e($entry['key']) ?>"></span>
                                                <span><?= bdst_e($entry['label']) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                            <noscript>
                                <p class="bdst-map-fallback">The interactive map needs JavaScript. The ranked list beside it and the table further down carry the same figures.</p>
                            </noscript>
                            <div class="bdst-map-fallback" id="bdst-map-fallback" hidden>
                                <p>The interactive map could not be displayed. The ranked list and the table further down carry the same figures.</p>
                            </div>
                            <div class="bdst-map-status" id="bdst-map-status" role="status" aria-live="polite"></div>
                            <div class="bdst-pin-key" id="bdst-pin-key" hidden></div>
                        </div>
                    </div>
                    <div class="col-xl-5">
                        <div class="bdst-panel">
                            <h2 class="bdst-card-title" id="bdst-detail-title">Regions by membership</h2>
                            <p class="bdst-card-sub" id="bdst-detail-note">Click a row or a pin to see a region's districts.</p>
                            <div class="bdst-tools">
                                <label class="visually-hidden" for="bdst-region-select">Choose a region</label>
                                <select id="bdst-region-select" class="bdst-select reg-native">
                                    <option value="">All regions</option>
                                    <?php foreach ($tableRows as $region): ?>
                                        <option value="<?= bdst_e($region['code']) ?>"><?= bdst_e($region['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="visually-hidden" for="bdst-region-filter">Filter regions by name</label>
                                <input id="bdst-region-filter" class="bdst-input" type="search" placeholder="Filter regions&hellip;" autocomplete="off">
                            </div>

                            <div class="bdst-view" id="bdst-region-view">
                                <div class="bdst-rows-head" aria-hidden="true">
                                    <span>#</span>
                                    <span>Region</span>
                                    <span>Members</span>
                                </div>
                                <div class="bdst-rows-scroll">
                                    <ol class="bdst-rows" id="bdst-rank-list">
                                        <?php if (!$rankedRows): ?>
                                            <li class="bdst-empty">No approved members are recorded yet.</li>
                                        <?php endif; ?>
                                        <?php foreach ($rankedRows as $index => $row): ?>
                                            <?= bdst_dash_rank_row($row, $index + 1, $maxCount, false) ?>
                                        <?php endforeach; ?>
                                    </ol>
                                    <?php if ($zeroRows): ?>
                                        <button type="button" class="bdst-zero-toggle" id="bdst-zero-toggle">
                                            Show regions with no members (<?= count($zeroRows) ?>)
                                        </button>
                                        <ol class="bdst-rows" id="bdst-rank-zero" hidden>
                                            <?php foreach ($zeroRows as $row): ?>
                                                <?= bdst_dash_rank_row($row, null, $maxCount, true) ?>
                                            <?php endforeach; ?>
                                        </ol>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="bdst-view" id="bdst-district-view" hidden>
                                <ul class="bdst-rows bdst-rows--plain" id="bdst-detail-list"></ul>
                                <button type="button" class="bdst-back" id="bdst-detail-reset">
                                    <i class="tji-arrow-left-long"></i> Back to all regions
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick insights -->
        <section class="bdst-dash-insights">
            <div class="container">
                <div class="bdst-insight-card">
                    <div class="bdst-insight-grid">
                        <div class="bdst-insight-block">
                            <h2 class="bdst-card-title">Member share by region</h2>
                            <p class="bdst-card-sub">How the membership is spread across the areas it reaches.</p>
                            <div class="bdst-share-bar" id="bdst-share-bar" role="img"
                                 aria-label="Share of approved members by region"></div>
                            <ul class="bdst-share-legend" id="bdst-share-legend"></ul>
                            <p class="bdst-share-note" id="bdst-share-note">&nbsp;</p>
                        </div>
                        <div class="bdst-insight-block">
                            <h2 class="bdst-card-title">Geographic coverage</h2>
                            <p class="bdst-card-sub">
                                How many of Tanzania's official regions and districts are represented.
                            </p>
                            <div class="bdst-coverage" data-total-regions="<?= (int) $regionTotal ?>"
                                 data-total-districts="<?= (int) $districtTotal ?>">
                                <div class="bdst-coverage-row">
                                    <span class="bdst-coverage-label">Regions</span>
                                    <span class="bdst-coverage-track"><span class="bdst-coverage-fill" id="bdst-coverage-regions"></span></span>
                                    <span class="bdst-coverage-value" id="bdst-coverage-regions-text">0 / <?= (int) $regionTotal ?></span>
                                </div>
                                <div class="bdst-coverage-row">
                                    <span class="bdst-coverage-label">Districts</span>
                                    <span class="bdst-coverage-track"><span class="bdst-coverage-fill" id="bdst-coverage-districts"></span></span>
                                    <span class="bdst-coverage-value" id="bdst-coverage-districts-text">0 / <?= (int) $districtTotal ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Registration trend -->
        <section class="bdst-dash-trends">
            <div class="container">
                <div class="bdst-trend-card">
                    <div class="bdst-trend-head">
                        <div class="bdst-trend-headtext">
                            <h2 class="bdst-card-title">Registration trend</h2>
                            <p class="bdst-card-sub">
                                Approved members added over time &mdash; new members per period and the
                                running total. Aggregate counts only.
                            </p>
                        </div>
                        <div class="bdst-trend-tools">
                            <div class="bdst-segmented" role="group" aria-label="Trend period">
                                <button type="button" class="bdst-seg is-active" data-period="monthly" aria-pressed="true">Monthly</button>
                                <button type="button" class="bdst-seg" data-period="yearly" aria-pressed="false">Yearly</button>
                            </div>
                            <button type="button" class="bdst-icon-btn" id="bdst-refresh">
                                <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                                <span class="visually-hidden">Refresh figures</span>
                            </button>
                        </div>
                    </div>

                    <div class="bdst-chart" id="bdst-trend-chart" role="img"
                         aria-label="Bar chart of approved members added per period">
                        <p class="bdst-chart-empty" id="bdst-trend-empty"<?= $trendBuckets ? ' hidden' : '' ?>>
                            No approved members recorded yet, so there is no trend to show.
                        </p>
                        <div class="bdst-bars-wrap" id="bdst-trend-bars"></div>
                    </div>

                    <details class="bdst-table-disclosure" id="bdst-trend-data"<?= $trendBuckets ? '' : ' hidden' ?>>
                        <summary>Show the numbers<span id="bdst-trend-range"><?= $trendSummary !== '' ? ' (' . bdst_e($trendSummary) . ')' : '' ?></span></summary>
                        <div class="table-responsive">
                            <table class="bdst-table">
                                <caption class="visually-hidden">Approved members added per period</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Period</th>
                                        <th scope="col">New members</th>
                                        <th scope="col">Total to date</th>
                                    </tr>
                                </thead>
                                <tbody id="bdst-trend-rows">
                                    <?php foreach ($trendBuckets as $bucket): ?>
                                        <tr>
                                            <th scope="row"><?= bdst_e($bucket['label']) ?></th>
                                            <td class="bdst-table-num"><?= number_format((int) $bucket['count']) ?></td>
                                            <td class="bdst-table-num"><?= number_format((int) $bucket['cumulative']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>

                    <p class="bdst-trend-status" id="bdst-updated" role="status" aria-live="polite">
                        Figures generated <?= gmdate('j F Y, H:i', strtotime((string) $summary['generated_at'])) ?> UTC.
                    </p>
                </div>
            </div>
        </section>

        <script type="application/json" id="bdst-dashboard-config">
            <?= json_encode([
                'regionsUrl'   => 'api/dashboard/regions.php',
                'districtsUrl' => 'api/dashboard/districts.php',
                'trendsUrl'    => 'api/dashboard/trends.php',
                'summaryUrl'   => 'api/dashboard/summary.php',
                'regionsGeo'   => 'assets/geo/tz-regions.geojson?v=2',
                'districtsGeo' => 'assets/geo/tz-districts.geojson?v=2',
                'tiles'        => [
                    'street' => [
                        'url'         => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                        'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    ],
                    'satellite' => [
                        'url'         => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                        'attribution' => 'Tiles &copy; Esri &mdash; Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community',
                    ],
                ],
                'minCohort'    => (int) $summary['thresholds']['min_cohort'],
                'minTotal'     => (int) $summary['thresholds']['min_total'],
                'published'    => (bool) $summary['published'],
                'refreshSeconds' => 45,
                'exportName'   => 'bds-tanzania-members-by-region',
                'trend'        => [
                    'period'    => $trends['period'],
                    'published' => (bool) ($trends['published'] ?? false),
                    'peak'      => (int) $trends['peak'],
                    'buckets'   => $trendBuckets,
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        </script>

        <!-- Data table -->
        <section class="bdst-dash-table">
            <div class="container">
                <div class="bdst-table-head">
                    <div>
                        <h2 class="bdst-card-title">Member totals by region</h2>
                        <p class="bdst-card-sub">The same figures as the map, as a table &mdash; including regions with no members.</p>
                    </div>
                    <button type="button" class="bdst-btn-ghost" id="bdst-export-csv">
                        <i class="fa-solid fa-file-csv" aria-hidden="true"></i> Download CSV
                    </button>
                </div>
                <details class="bdst-table-disclosure">
                    <summary>Show the full table (<?= count($tableRows) ?> regions)</summary>
                    <div class="table-responsive">
                        <table class="bdst-table">
                            <caption class="visually-hidden">Approved BDS Tanzania members by region</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Region</th>
                                    <th scope="col">Approved members</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableRows as $row): ?>
                                    <tr>
                                        <th scope="row">
                                            <span class="bdst-row-swatch bdst-swatch--<?= bdst_e($row['band']) ?>"></span>
                                            <?= bdst_e($row['name']) ?>
                                        </th>
                                        <td class="bdst-table-num<?= $row['count'] === null ? ' bdst-cell-muted' : '' ?>">
                                            <?= bdst_e(bdst_dash_cell($row)) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
                <p class="bdst-map-status" id="bdst-table-updated">
                    Figures generated <?= gmdate('j F Y, H:i', strtotime((string) $summary['generated_at'])) ?> UTC.
                    Updated automatically as registrations are approved.
                </p>
            </div>
        </section>

        <?php endif; ?>
        </div><!-- /.bdst-surface -->
    </main>

    <!-- Footer -->
    <footer class="tj-footer-section footer-4 section-gap-x">
        <div class="footer-top">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <div class="footer-cta"></div>
                    </div>
                </div>
            </div>

            <div class="footer-main-area">
                <div class="container">
                    <div class="row justify-content-between">

                        <div class="col-xl-3 col-md-6">
                            <div class="footer-widget wow fadeInUp" data-wow-delay=".1s">
                                <h3 class="title">About BDS Tanzania</h3>
                                <div class="footer-text">
                                    <p>
                                        BDS Tanzania (the Business Development Society of Tanzania) is the national professional association uniting business development service providers, enterprise advisors and institutional partners to promote standards, capacity building, advocacy and practical
                                        enterprise support for inclusive economic growth in Tanzania.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="footer-widget footer-col-2 widget-nav-menu wow fadeInUp" data-wow-delay=".3s">
                                <h3 class="title">Our Programme Areas</h3>
                                <ul>
                                    <li><a href="business-advisory-support.html">Business Advisory Support</a></li>
                                    <li><a href="capacity-building.html">Capacity Building</a></li>
                                    <li><a href="sme-support.html">SME Support</a></li>
                                    <li><a href="professional-development.html">Professional Development</a></li>
                                    <li><a href="why-join.html">Membership</a></li>
                                    <li><a href="contact.html">Get in Touch</a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="footer-widget footer-col-3 widget-nav-menu wow fadeInUp" data-wow-delay=".5s">
                                <h3 class="title">Quick Links</h3>
                                <ul>
                                    <li><a href="about.html">About BDS Tanzania</a></li>
                                    <li><a href="leadership.html">Leadership</a></li>
                                    <li><a href="how-to-join.html">How to Join</a></li>
                                    <li><a href="updates.php">Updates</a></li>
                                    <li><a href="contact.html">Contact Us</a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="footer-widget widget-contact style-2 wow fadeInUp" data-wow-delay=".7s">
                                <h3 class="title">Contact Us</h3>
                                <div class="footer-contact-info">
                                    <div class="contact-item">
                                        <span><i class="tji-location"></i> Dodoma, Tanzania</span>
                                    </div>
                                    <div class="contact-item">
                                        <span><i class="tji-phone"></i> <a href="tel:+255655211777">+255655211777</a></span>
                                    </div>
                                    <div class="contact-item">
                                        <span><i class="tji-phone"></i> <a href="tel:+255621371328">+255621371328</a></span>
                                    </div>
                                    <div class="contact-item">
                                        <span><i class="fa-regular fa-envelope"></i> <a href="mailto:info@bdst.or.tz">info@bdst.or.tz</a></span>
                                    </div>
                                    <div class="contact-item">
                                        <span><i class="tji-clock"></i> Monday - Saturday</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="tj-copyright-area-4">
                <div class="container">
                    <div class="row">
                        <div class="col-12">
                            <div class="copyright-content-area">
                                <div class="copyright-text">
                                    <p>&copy; 2026 <a href="https://www.bdst.or.tz/" target="_blank" rel="noopener noreferrer">BDS Tanzania</a>. All rights reserved.</p>
                                </div>

                                <div class="social-links style-2">
                                    <ul>
                                        <li><a href="#" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a></li>
                                        <li><a href="#" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a></li>
                                        <li><a href="#" target="_blank" rel="noopener noreferrer" aria-label="X"><i class="fa-brands fa-x-twitter"></i></a></li>
                                        <li><a href="#" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a></li>
                                    </ul>
                                </div>

                                <div class="copyright-menu">
                                    <ul>
                                        <li><a href="privacy-policy.html">Privacy Policy</a></li>
                                        <li><a href="terms-and-conditions.html">Terms &amp; Conditions</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- JS -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/wow.min.js"></script>
    <script src="assets/js/meanmenu.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/vendor/leaflet/leaflet.js"></script>
    <script src="assets/js/dashboard.js?v=<?= bdst_dash_asset('assets/js/dashboard.js') ?>"></script>
</body>

</html>