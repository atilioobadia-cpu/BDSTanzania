<?php
require __DIR__ . '/includes/db.php';

$search    = trim((string) ($_GET['s'] ?? ''));
$page      = max(1, (int) ($_GET['page'] ?? 1));
$perPage   = 6;
$posts     = [];
$totalPages = 1;

try {
    $pdo = bdst_db();

    $where  = "status = 'published'";
    $params = [];

    if ($search !== '') {
        $where         .= " AND (title LIKE :q1 OR excerpt LIKE :q2 OR content LIKE :q3)";
        $q              = '%' . $search . '%';
        $params[':q1']  = $q;
        $params[':q2']  = $q;
        $params[':q3']  = $q;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM updates WHERE $where");
    $stmt->execute($params);
    $total      = (int) $stmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT id, title, slug, excerpt, content, image_path, published_at
         FROM updates
         WHERE $where
         ORDER BY published_at DESC, id DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll();

    $recentPosts = $pdo->query(
        "SELECT id, title, slug, image_path, published_at
         FROM updates
         WHERE status = 'published'
         ORDER BY published_at DESC, id DESC
         LIMIT 5"
    )->fetchAll();
} catch (Throwable $e) {
    error_log('Updates listing error: ' . $e->getMessage());
    $posts       = [];
    $recentPosts = [];
}

function bdst_reading_time(string $text): int
{
    $words = str_word_count(strip_tags($text));
    return max(1, (int) ceil($words / 200));
}

function bdst_cover_path(array $row): string
{
    $raw  = (string) ($row['image_path'] ?? '');
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded[0])) {
            return (string) $decoded[0];
        }
        return $raw;
    }
    $index = ((int) $row['id'] % 9) + 1;
    return 'assets/images/gallery/gallery-' . $index . '.jpg';
}

$featured = null;
if ($page === 1 && $search === '' && !empty($posts)) {
    $featured = $posts[0];
}
$gridPosts = $featured !== null ? array_slice($posts, 1) : $posts;
?>
<!doctype html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Updates Page SEO -->
    <meta name="description" content="Stay updated with BDS Tanzania news, announcements, events, publications and key developments relevant to members and the wider business ecosystem.">
    <meta name="keywords" content="BDS Tanzania updates, BDS Tanzania news, BDS Tanzania events, announcements Tanzania business development, publications BDS Tanzania">
    <meta name="author" content="BDS Tanzania">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://www.bdst.or.tz/updates.php">

    <meta property="og:type" content="website">
    <meta property="og:title" content="Updates | BDS Tanzania News, Events &amp; Announcements">
    <meta property="og:description" content="Stay updated with BDS Tanzania news, announcements, events, publications and key developments relevant to members and the wider business ecosystem.">
    <meta property="og:url" content="https://www.bdst.or.tz/updates.php">
    <meta property="og:site_name" content="BDS Tanzania">
    <meta property="og:image" content="https://www.bdst.or.tz/assets/images/logos/logo.webp">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Updates | BDS Tanzania News, Events &amp; Announcements">
    <meta name="twitter:description" content="Stay updated with BDS Tanzania news, announcements, events, publications and key developments relevant to members and the wider business ecosystem.">
    <meta name="twitter:image" content="https://www.bdst.or.tz/assets/images/logos/logo.webp">

    <title>Updates | BDS Tanzania News, Events &amp; Announcements</title>

    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "CollectionPage",
            "name": "BDS Tanzania Updates",
            "url": "https://www.bdst.or.tz/updates.php",
            "description": "News, events, announcements and updates from BDS Tanzania."
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

    <style>
        .tj-updates-section {
            padding: 0 0 120px;
        }

        .updates-layout .col-lg-8,
        .updates-layout .col-lg-4 {
            margin-bottom: 40px;
        }

        .update-empty {
            padding: 80px 20px;
            background: #f6f9f9;
            border: 1px dashed rgba(25, 151, 198, 0.35);
            border-radius: 16px;
            color: #5a6772;
            font-size: 16px;
            text-align: center;
        }

        .update-empty i {
            font-size: 42px;
            color: #1997C6;
            display: block;
            margin-bottom: 18px;
        }

        .update-empty h3 {
            font-size: 22px;
            color: #0c1e21;
            margin-bottom: 8px;
        }

        .update-search-note {
            margin-top: 14px;
            color: #67787a;
            font-size: 15px;
        }

        /* ------- Featured post ------- */
        .update-featured {
            background: #fff;
            border: 1px solid #e4ecec;
            border-radius: 18px;
            overflow: hidden;
            margin-bottom: 44px;
            box-shadow: 0 20px 50px -24px rgba(12, 30, 33, 0.25);
            transition: box-shadow .35s ease;
        }

        .update-featured:hover {
            box-shadow: 0 30px 60px -26px rgba(12, 30, 33, 0.3);
        }

        .update-featured__thumb {
            position: relative;
            display: block;
            aspect-ratio: 21 / 9;
            overflow: hidden;
            background: #eef2f3;
        }

        .update-featured__thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform .6s ease;
        }

        .update-featured:hover .update-featured__thumb img {
            transform: scale(1.04);
        }

        .update-featured__date {
            position: absolute;
            right: 16px;
            top: 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.95);
            color: #0c1e21;
            font-size: 12.5px;
            font-weight: 700;
            padding: 8px 15px;
            border-radius: 999px;
            letter-spacing: .3px;
        }

        .update-featured__date i {
            color: #1997C6;
        }

        .update-featured__body {
            padding: 34px 38px 36px;
        }

        .update-featured__tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(25, 151, 198, 0.1);
            color: #1997C6;
            font-size: 12.5px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 6px 13px;
            border-radius: 999px;
        }

        .update-featured__tag::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #1997C6;
        }

        .update-featured__title {
            font-size: 30px;
            line-height: 1.3;
            color: #0c1e21;
            margin: 16px 0 12px;
        }

        .update-featured__title a {
            color: inherit;
            transition: color .3s ease;
        }

        .update-featured__title a:hover {
            color: #1997C6;
        }

        .update-featured__excerpt {
            font-size: 15px;
            line-height: 1.75;
            color: #364e52;
            margin: 0;
        }

        .update-featured__foot {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid #eef3f4;
        }

        .update-featured__foot .update-meta {
            margin-bottom: 0;
        }

        .update-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            margin-bottom: 24px;
            font-size: 13.5px;
            color: #67787a;
        }

        .update-meta span {
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .update-meta i {
            color: #1997C6;
        }

        .update-more {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 700;
            color: #1997C6;
            text-decoration: none;
            padding-bottom: 4px;
            background-image: linear-gradient(#1997C6, #1997C6);
            background-size: 0 2px;
            background-repeat: no-repeat;
            background-position: left bottom;
            transition: background-size .35s ease, color .3s ease;
        }

        .update-more i {
            transition: transform .3s ease;
        }

        .update-more:hover {
            color: #0c1e21;
            background-size: 100% 2px;
        }

        .update-more:hover i {
            transform: translateX(5px);
        }

        /* ------- Standard cards ------- */
        .update-card {
            background: #fff;
            border: 1px solid #e4ecec;
            border-radius: 16px;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: transform .35s ease, box-shadow .35s ease, border-color .35s ease;
        }

        .update-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 24px 50px -24px rgba(12, 30, 33, 0.22);
            border-color: #d4e6ea;
        }

        .update-thumb {
            position: relative;
            display: block;
            aspect-ratio: 16 / 10;
            overflow: hidden;
            background: #eef2f3;
        }

        .update-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform .5s ease;
        }

        .update-card:hover .update-thumb img {
            transform: scale(1.06);
        }

        .update-thumb__date {
            position: absolute;
            left: 14px;
            bottom: 14px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(12, 30, 33, 0.82);
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 999px;
            backdrop-filter: blur(4px);
        }

        .update-body {
            padding: 22px 22px 24px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .update-body .update-meta {
            gap: 14px;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .update-title {
            font-size: 18px;
            line-height: 1.45;
            margin: 0 0 10px;
        }

        .update-title a {
            color: #0c1e21;
            transition: color .3s ease;
        }

        .update-title a:hover {
            color: #1997C6;
        }

        .update-excerpt {
            font-size: 14.5px;
            line-height: 1.7;
            color: #5b6e72;
            margin: 0 0 16px;
            flex: 1;
        }

        .update-body .update-more {
            align-self: flex-start;
        }

        /* ------- Sidebar ------- */
        .updates-sidebar {
            position: sticky;
            top: 120px;
        }

        .side-widget {
            background: #fff;
            border: 1px solid #e4ecec;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 12px 32px -22px rgba(12, 30, 33, 0.16);
        }

        .side-widget__title {
            font-size: 17px;
            color: #0c1e21;
            margin-bottom: 18px;
            padding-bottom: 13px;
            border-bottom: 1px solid #eef3f4;
            position: relative;
        }

        .side-widget__title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -1px;
            width: 46px;
            height: 2px;
            background: #1997C6;
        }

        .side-search {
            position: relative;
        }

        .side-search input {
            width: 100%;
            height: 50px;
            padding: 0 54px 0 16px;
            border: 1px solid #dfe8ea;
            border-radius: 11px;
            font-size: 14px;
            color: #0c1e21;
            background: #fff;
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        .side-search input:focus {
            outline: none;
            border-color: #1997C6;
            box-shadow: 0 0 0 3px rgba(25, 151, 198, 0.14);
        }

        .side-search button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            border: 0;
            border-radius: 9px;
            background: #1997C6;
            color: #fff;
            cursor: pointer;
            transition: background .2s ease;
        }

        .side-search button:hover {
            background: #0c1e21;
        }

        .side-recent {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .side-recent li {
            margin-bottom: 15px;
        }

        .side-recent li:last-child {
            margin-bottom: 0;
        }

        .side-recent a {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
        }

        .side-recent__thumb {
            flex: none;
            width: 74px;
            height: 56px;
            border-radius: 9px;
            overflow: hidden;
            background: #eef2f3;
        }

        .side-recent__thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .side-recent__info {
            min-width: 0;
        }

        .side-recent__title {
            display: block;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.45;
            color: #0c1e21;
            margin-bottom: 4px;
            transition: color .25s ease;
        }

        .side-recent__title:hover {
            color: #1997C6;
        }

        .side-recent__date {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            color: #67787a;
        }

        .side-widget--cta {
            background: linear-gradient(150deg, #0c1e21 0%, #15353a 100%);
            border: 0;
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .side-widget--cta::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(380px 200px at 0% 0%, rgba(25, 151, 198, 0.35), transparent 60%);
        }

        .side-widget--cta h4 {
            position: relative;
            color: #fff;
            font-size: 20px;
            margin-bottom: 10px;
        }

        .side-widget--cta p {
            position: relative;
            color: rgba(255, 255, 255, 0.78);
            font-size: 14px;
            line-height: 1.65;
            margin-bottom: 20px;
        }

        .side-widget--cta .tj-primary-btn {
            position: relative;
        }

        /* ------- Pagination ------- */
        .tj-pagination {
            margin-top: 55px;
        }

        .tj-pagination ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: center;
            gap: 8px;
        }

        .tj-pagination ul li a,
        .tj-pagination ul li span {
            min-width: 46px;
            height: 46px;
            padding: 0 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            border: 1px solid #e0e9ea;
            color: #0c1e21;
            font-weight: 600;
            text-decoration: none;
            transition: all .3s ease;
        }

        .tj-pagination ul li a:hover,
        .tj-pagination ul li span.current {
            background: #1997C6;
            border-color: #1997C6;
            color: #fff;
            box-shadow: 0 8px 20px -8px rgba(25, 151, 198, 0.5);
        }

        .tj-pagination ul li a.disabled {
            opacity: .45;
            pointer-events: none;
        }

        @media (max-width: 991.98px) {
            .tj-updates-section {
                padding-bottom: 90px;
            }

            .updates-sidebar {
                position: static;
            }

            .update-featured__thumb {
                aspect-ratio: 16 / 9;
            }
        }

        /* ------- Clean fonts ------- */
        .sec-heading .sub-title,
        .sec-heading .sec-title,
        .update-search-note,
        .tj-updates-section .update-title a,
        .tj-updates-section .update-featured__title a,
        .tj-updates-section .side-widget__title,
        .tj-updates-section .side-widget--cta h4,
        .tj-updates-section .update-empty h3 {
            font-family: 'Plus Jakarta Sans', 'Mona Sans', sans-serif;
        }

        .sec-heading .sec-title {
            letter-spacing: -.5px;
        }
    </style>
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
        <div class="space-for-header"></div>

        <!-- Breadcrumb -->
        <section class="tj-page-header section-gap-x" data-bg-image="assets/images/bg/1.jpg">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="tj-page-header-content text-center">
                            <h1 class="tj-page-title">Updates</h1>
                            <div class="tj-page-link">
                                <span><i class="tji-home"></i></span>
                                <span><a href="index.html">Home</a></span>
                                <span><i class="tji-arrow-right"></i></span>
                                <span><span>Updates</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="page-header-overlay" data-bg-image="assets/images/shape/pheader-overlay.jpg"></div>
        </section>

        <!-- Updates Intro -->
        <section class="section-gap">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="sec-heading text-center">
                            <span class="sub-title wow fadeInUp" data-wow-delay=".1s">
                                <i class="tji-box"></i>News &amp; Announcements
                            </span>
                            <h2 class="sec-title wow fadeInUp" data-wow-delay=".2s">Latest Updates from BDS Tanzania</h2>
                            <?php if ($search !== ''): ?>
                                <p class="update-search-note wow fadeInUp" data-wow-delay=".3s">
                                    Search results for &ldquo;<strong><?= bdst_e($search) ?></strong>&rdquo;
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Updates Listing -->
        <section class="tj-updates-section">
            <div class="container">
                <?php if (!empty($posts)): ?>
                    <div class="row updates-layout gx-lg-5">
                        <div class="col-lg-8">
                            <?php if ($featured !== null): ?>
                                <article class="update-featured">
                                    <a class="update-featured__thumb" href="update.php?slug=<?= bdst_e($featured['slug']) ?>">
                                        <img src="<?= bdst_e(bdst_cover_path($featured)) ?>" alt="<?= bdst_e($featured['title']) ?>" loading="lazy" decoding="async">
                                        <span class="update-featured__date"><i class="fa-regular fa-calendar"></i> <?= bdst_e(date('F j, Y', strtotime($featured['published_at']))) ?></span>
                                    </a>
                                    <div class="update-featured__body">
                                        <span class="update-featured__tag">Latest Update</span>
                                        <h2 class="update-featured__title">
                                            <a href="update.php?slug=<?= bdst_e($featured['slug']) ?>"><?= bdst_e($featured['title']) ?></a>
                                        </h2>
                                        <?php if (!empty($featured['excerpt'])): ?>
                                            <p class="update-featured__excerpt"><?= bdst_e(mb_strimwidth($featured['excerpt'], 0, 220, '...')) ?></p>
                                        <?php endif; ?>
                                        <div class="update-featured__foot">
                                            <div class="update-meta">
                                                <span><i class="fa-regular fa-calendar"></i> <?= bdst_e(date('F j, Y', strtotime($featured['published_at']))) ?></span>
                                                <span><i class="fa-regular fa-clock"></i> <?= bdst_reading_time($featured['content'] ?? '') ?> min read</span>
                                            </div>
                                            <a class="update-more" href="update.php?slug=<?= bdst_e($featured['slug']) ?>">
                                                Read Full Update <i class="tji-arrow-right-long"></i>
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            <?php endif; ?>

                            <?php if (!empty($gridPosts)): ?>
                                <div class="row g-4">
                                    <?php foreach ($gridPosts as $post): ?>
                                        <div class="col-md-6">
                                            <article class="update-card">
                                                <a class="update-thumb" href="update.php?slug=<?= bdst_e($post['slug']) ?>">
                                                    <img src="<?= bdst_e(bdst_cover_path($post)) ?>" alt="<?= bdst_e($post['title']) ?>" loading="lazy" decoding="async">
                                                    <span class="update-thumb__date"><i class="fa-regular fa-calendar"></i> <?= bdst_e(date('M j, Y', strtotime($post['published_at']))) ?></span>
                                                </a>
                                                <div class="update-body">
                                                    <div class="update-meta">
                                                        <span><i class="fa-regular fa-clock"></i> <?= bdst_reading_time($post['content'] ?? '') ?> min read</span>
                                                    </div>
                                                    <h3 class="update-title">
                                                        <a href="update.php?slug=<?= bdst_e($post['slug']) ?>"><?= bdst_e($post['title']) ?></a>
                                                    </h3>
                                                    <?php if (!empty($post['excerpt'])): ?>
                                                        <p class="update-excerpt"><?= bdst_e(mb_strimwidth($post['excerpt'], 0, 150, '...')) ?></p>
                                                    <?php endif; ?>
                                                    <a class="update-more" href="update.php?slug=<?= bdst_e($post['slug']) ?>">
                                                        Read More <i class="tji-arrow-right-long"></i>
                                                    </a>
                                                </div>
                                            </article>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($totalPages > 1): ?>
                                <div class="tj-pagination">
                                    <ul>
                                        <li>
                                            <?php if ($page > 1): ?>
                                                <a href="updates.php?<?= $search !== '' ? 's=' . rawurlencode($search) . '&' : '' ?>page=<?= $page - 1 ?>"><i class="fa-regular fa-angle-left"></i></a>
                                            <?php else: ?>
                                                <a class="disabled" href="#" aria-disabled="true"><i class="fa-regular fa-angle-left"></i></a>
                                            <?php endif; ?>
                                        </li>
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <?php if ($i === $page): ?>
                                                <li><span class="current"><?= $i ?></span></li>
                                            <?php else: ?>
                                                <li>
                                                    <a href="updates.php?<?= $search !== '' ? 's=' . rawurlencode($search) . '&' : '' ?>page=<?= $i ?>"><?= $i ?></a>
                                                </li>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        <li>
                                            <?php if ($page < $totalPages): ?>
                                                <a href="updates.php?<?= $search !== '' ? 's=' . rawurlencode($search) . '&' : '' ?>page=<?= $page + 1 ?>"><i class="fa-regular fa-angle-right"></i></a>
                                            <?php else: ?>
                                                <a class="disabled" href="#" aria-disabled="true"><i class="fa-regular fa-angle-right"></i></a>
                                            <?php endif; ?>
                                        </li>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <div class="tj-pagination">
                                    <ul>
                                        <li><a class="disabled" href="#" aria-disabled="true"><i class="fa-regular fa-angle-left"></i></a></li>
                                        <li><span class="current">1</span></li>
                                        <li><a class="disabled" href="#" aria-disabled="true"><i class="fa-regular fa-angle-right"></i></a></li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-lg-4">
                            <div class="updates-sidebar">
                                <div class="side-widget">
                                    <h4 class="side-widget__title">Search Updates</h4>
                                    <form class="side-search" method="get" action="updates.php">
                                        <input type="search" name="s" value="<?= bdst_e($search) ?>" placeholder="Search updates..." aria-label="Search updates">
                                        <button type="submit" aria-label="Search"><i class="tji-search"></i></button>
                                    </form>
                                </div>

                                <div class="side-widget">
                                    <h4 class="side-widget__title">Recent Updates</h4>
                                    <ul class="side-recent">
                                        <?php foreach ($recentPosts as $rp): ?>
                                            <li>
                                                <a href="update.php?slug=<?= bdst_e($rp['slug']) ?>">
                                                    <span class="side-recent__thumb">
                                                        <img src="<?= bdst_e(bdst_cover_path($rp)) ?>" alt="<?= bdst_e($rp['title']) ?>" loading="lazy" decoding="async">
                                                    </span>
                                                    <span class="side-recent__info">
                                                        <span class="side-recent__title"><?= bdst_e($rp['title']) ?></span>
                                                        <span class="side-recent__date"><i class="fa-regular fa-calendar"></i> <?= bdst_e(date('F j, Y', strtotime($rp['published_at']))) ?></span>
                                                    </span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>

                                <div class="side-widget side-widget--cta">
                                    <h4>Become a Member</h4>
                                    <p>Join Tanzania's national professional home for business development service providers and power enterprise growth.</p>
                                    <a class="tj-primary-btn" href="why-join.html">
                                        <span class="btn-text"><span>Why Join Us</span></span>
                                        <span class="btn-icon"><i class="tji-arrow-right-long"></i></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row justify-content-center">
                        <div class="col-lg-8 text-center">
                            <div class="update-empty">
                                <i class="fa-regular fa-inbox"></i>
                                <h3>No Updates Yet</h3>
                                <p>No updates have been published yet. Please check back soon.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

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
</body>

</html>