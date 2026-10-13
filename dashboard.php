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

$summary = null;
$dbError = false;

try {
    $summary = bdst_dashboard_public(bdst_dashboard_data(bdst_db()));
} catch (Throwable $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    $dbError = true;
}

$tableRows = [];
if ($summary !== null) {
    $tableRows = $summary['regions'];
    usort($tableRows, static function (array $a, array $b): int {
        $ac = $a['count'];
        $bc = $b['count'];
        if ($ac === null && $bc !== null) {
            return 1;
        }
        if ($ac !== null && $bc === null) {
            return -1;
        }
        if ($ac !== $bc) {
            return $bc <=> $ac;
        }
        return strcmp($a['name'], $b['name']);
    });
}

/** Text for a region's "approved members" cell. */
function bdst_dash_cell(array $region): string
{
    if ($region['band'] === 'zero') {
        return '0';
    }
    if ($region['count'] === null) {
        return (string) $region['label'];
    }
    return (string) $region['count'];
}

?>
<!doctype html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Member Map SEO -->
    <meta name="description" content="Interactive map of BDS Tanzania members by region and district. Aggregate, anonymised figures only - no member names or addresses.">
    <meta name="keywords" content="BDS Tanzania members map, business development professionals Tanzania, member distribution by region, BDS Tanzania statistics">
    <meta name="author" content="BDS Tanzania">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://www.bdst.or.tz/dashboard.php">

    <meta property="og:type" content="website">
    <meta property="og:title" content="Member Map | BDS Tanzania Members by Region">
    <meta property="og:description" content="Where BDS Tanzania members are based, region by region. Aggregate figures only.">
    <meta property="og:url" content="https://www.bdst.or.tz/dashboard.php">
    <meta property="og:site_name" content="BDS Tanzania">
    <meta property="og:image" content="https://www.bdst.or.tz/assets/images/logos/logo.webp">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Member Map | BDS Tanzania Members by Region">
    <meta name="twitter:description" content="Where BDS Tanzania members are based, region by region. Aggregate figures only.">
    <meta name="twitter:image" content="https://www.bdst.or.tz/assets/images/logos/logo.webp">

    <title>Member Map | BDS Tanzania Members by Region and District</title>

    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Dataset",
            "name": "BDS Tanzania members by region",
            "description": "Aggregate counts of approved BDS Tanzania members by region and district. Individual members are not identified.",
            "url": "https://www.bdst.or.tz/dashboard.php",
            "spatialCoverage": { "@type": "Place", "name": "Tanzania" },
            "creator": { "@type": "Organization", "name": "Business Development Society of Tanzania" },
            "isAccessibleForFree": true
        }
    </script>

    <link rel="shortcut icon" type="image/x-icon" href="assets/images/fav.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/font-awesome-pro.min.css">
    <link rel="stylesheet" href="assets/css/animate.min.css">
    <link rel="stylesheet" href="assets/css/bexon-icons.css">
    <link rel="stylesheet" href="assets/css/meanmenu.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/vendor/leaflet/leaflet.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>

<body>
    <div class="body-overlay"></div>

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
                    <h5 class="hamburger-title">Search BDS Tanzania</h5>
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
                    <h5 class="hamburger-title">Contact Information</h5>
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
                <h5 class="hamburger-title">Follow BDS Tanzania</h5>
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
                    <h5 class="hamburger-title">Contact Information</h5>
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
                <h5 class="hamburger-title">Follow BDS Tanzania</h5>
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
                                            <li><a href="dashboard.php">Member Map</a></li>
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
                                            <li><a href="dashboard.php">Member Map</a></li>
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

    <main id="primary" class="site-main">
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

        <!-- Intro -->
        <section class="section-gap">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-9">
                        <div class="sec-heading text-center">
                            <span class="sub-title wow fadeInUp" data-wow-delay=".1s">
                                <i class="tji-box"></i>National Overview
                            </span>
                            <h2 class="sec-title wow fadeInUp" data-wow-delay=".2s">BDS Tanzania Members Across the Country</h2>
                            <p class="wow fadeInUp" data-wow-delay=".3s">
                                Where our approved members are based, region by region. Only aggregate
                                figures are published &mdash; individual members are never identified, and
                                the map uses administrative reference points rather than anybody's address.
                            </p>
                        </div>
                    </div>
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

        <!-- Summary -->
        <section class="bdst-dash-stats">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-4 col-md-6">
                        <div class="bdst-stat-card wow fadeInUp" data-wow-delay=".1s">
                            <span class="bdst-stat-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="bdst-stat-value"><?= number_format((int) $summary['totals']['members']) ?></span>
                            <span class="bdst-stat-label">Approved members</span>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="bdst-stat-card wow fadeInUp" data-wow-delay=".2s">
                            <span class="bdst-stat-icon"><i class="fa-solid fa-map-location-dot"></i></span>
                            <?php if ($summary['totals']['regions_covered'] === null): ?>
                                <span class="bdst-stat-value is-suppressed">Not published yet</span>
                            <?php else: ?>
                                <span class="bdst-stat-value"><?= number_format((int) $summary['totals']['regions_covered']) ?></span>
                            <?php endif; ?>
                            <span class="bdst-stat-label">Regions represented</span>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="bdst-stat-card wow fadeInUp" data-wow-delay=".3s">
                            <span class="bdst-stat-icon"><i class="fa-solid fa-location-dot"></i></span>
                            <?php if ($summary['totals']['districts_covered'] === null): ?>
                                <span class="bdst-stat-value is-suppressed">Not published yet</span>
                            <?php else: ?>
                                <span class="bdst-stat-value"><?= number_format((int) $summary['totals']['districts_covered']) ?></span>
                            <?php endif; ?>
                            <span class="bdst-stat-label">Districts represented</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Map + district panel -->
        <section class="bdst-dash-main">
            <div class="container">
                <?php if (!$summary['published']): ?>
                    <div class="bdst-notice bdst-notice--gate">
                        <i class="fa-solid fa-shield-halved"></i>
                        <div>
                            <strong>Geographic detail is not published yet.</strong>
                            To protect members' privacy, the regional breakdown is shown once there are
                            at least <?= (int) $summary['thresholds']['min_total'] ?> approved members,
                            and an area is only labelled once at least
                            <?= (int) $summary['thresholds']['min_cohort'] ?> members are recorded there.
                            The register is still growing, so please check back soon.
                        </div>
                    </div>
                <?php endif; ?>

                <div class="bdst-notice">
                    <i class="fa-solid fa-lock"></i>
                    <div>
                        <strong>Aggregate figures only.</strong>
                        This page lists counts by area. It never exposes names, contact details or
                        member addresses, and the map's markers are administrative reference points
                        rather than anybody's location. See our
                        <a href="privacy-policy.html">privacy policy</a>.
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-xl-7">
                        <div class="bdst-map-card">
                            <h3 class="bdst-card-title">Member distribution</h3>
                            <p class="bdst-card-sub">Select a region to see its districts. Drag the map or use the zoom buttons to explore.</p>
                            <div id="bdst-map" role="region" aria-label="Map of Tanzania showing the number of approved BDS Tanzania members in each region"></div>
                            <noscript>
                                <p class="bdst-map-fallback">The interactive map needs JavaScript. The table further down this page lists the same figures, and the list below lets you choose a region.</p>
                            </noscript>
                            <div class="bdst-map-fallback" id="bdst-map-fallback" hidden>
                                <p>The interactive map could not be displayed. The table further down this page lists the same figures.</p>
                            </div>
                            <div class="bdst-map-status" id="bdst-map-status" role="status" aria-live="polite"></div>
                            <ul class="bdst-legend" id="bdst-legend">
                                <?php
                                $legendEntries = $summary['published']
                                    ? $summary['legend']
                                    : [['key' => 'withheld', 'label' => 'Not published yet']];
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
                    <div class="col-xl-5">
                        <div class="bdst-detail-card">
                            <h3 class="bdst-card-title" id="bdst-detail-title">District breakdown</h3>
                            <p class="bdst-card-sub" id="bdst-detail-note">Select a region on the map, or use the list above, to see its districts.</p>
                            <div class="bdst-detail-tools">
                                <label class="bdst-detail-hint" for="bdst-region-select">Choose a region</label>
                                <select id="bdst-region-select" class="bdst-select reg-native">
                                    <option value="">All regions</option>
                                    <?php foreach ($summary['regions'] as $region): ?>
                                        <option value="<?= bdst_e($region['code']) ?>"><?= bdst_e($region['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <ul class="bdst-detail-list" id="bdst-detail-list"></ul>
                            <button type="button" class="bdst-detail-back" id="bdst-detail-reset" hidden>
                                <i class="tji-arrow-left-long"></i> Back to the whole country
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <script type="application/json" id="bdst-dashboard-config">
            <?= json_encode([
                'regionsUrl'   => 'api/dashboard/regions.php',
                'districtsUrl' => 'api/dashboard/districts.php',
                'regionsGeo'   => 'assets/geo/tz-regions.geojson',
                'districtsGeo' => 'assets/geo/tz-districts.geojson',
                'tileUrl'      => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                'attribution'  => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                'minCohort'    => (int) $summary['thresholds']['min_cohort'],
                'minTotal'     => (int) $summary['thresholds']['min_total'],
                'published'    => (bool) $summary['published'],
            ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        </script>

        <!-- Data table -->
        <section class="bdst-dash-table">
            <div class="container">
                <h3 class="bdst-card-title">Members by region</h3>
                <p class="bdst-card-sub">The same figures as the map, as a table.</p>
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
                <p class="bdst-map-status">
                    Figures generated <?= gmdate('j F Y, H:i', strtotime((string) $summary['generated_at'])) ?> UTC.
                    Updated automatically as registrations are approved.
                </p>
            </div>
        </section>

        <?php endif; ?>
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
                                <h5 class="title">About BDS Tanzania</h5>
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
                                <h5 class="title">Our Programme Areas</h5>
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
                                <h5 class="title">Quick Links</h5>
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
                                <h5 class="title">Contact Us</h5>
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
    <script src="assets/js/dashboard.js"></script>
</body>

</html>