<p align="center">
  <img src="assets/images/logos/logo.webp" alt="BDS Tanzania" width="180">
</p>

<h1 align="center">BDS Tanzania</h1>

<p align="center">
  <strong>The Business Development Society of Tanzania</strong><br>
  National professional association uniting business development service providers,
  enterprise advisors and institutional partners.
</p>

<p align="center">
  <a href="https://github.com/atilioobadia-cpu/BDSTanzania/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-green.svg" alt="License: MIT"></a>
  <a href="#"><img src="https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white" alt="HTML5"></a>
  <a href="#"><img src="https://img.shields.io/badge/CSS3-1572B6?logo=css3&logoColor=white" alt="CSS3"></a>
  <a href="#"><img src="https://img.shields.io/badge/JavaScript-F7DF1E?logo=javascript&logoColor=black" alt="JavaScript"></a>
  <a href="#"><img src="https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white" alt="PHP 8.2"></a>
  <a href="#"><img src="https://img.shields.io/badge/Bootstrap-7952B3?logo=bootstrap&logoColor=white" alt="Bootstrap"></a>
  <a href="#"><img src="https://img.shields.io/badge/jQuery-0769AD?logo=jquery&logoColor=white" alt="jQuery"></a>
  <a href="#"><img src="https://img.shields.io/badge/PHPMailer-30B0C7?logo=maildotru&logoColor=white" alt="PHPMailer"></a>
  <a href="#"><img src="https://img.shields.io/badge/Apache-%20.htaccess-D22128?logo=apache&logoColor=white" alt="Apache"></a>
  <a href="#"><img src="https://img.shields.io/badge/cPanel-FF6C2C?logo=cpanel&logoColor=white" alt="cPanel"></a>
</p>

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Getting Started](#getting-started)
  - [Local Development](#local-development)
  - [Deployment (cPanel)](#deployment-cpanel)
- [Configuration](#configuration)
- [Updates & News Backend](#updates--news-backend)
- [Roadmap](#roadmap)
- [Security Notes](#security-notes)
- [Contact](#contact)

---

## Overview

**BDS Tanzania** is the official website of the *Business Development Society of Tanzania* — a voluntary, non-governmental professional association headquartered in Dodoma that sets professional standards, builds practitioner capacity and coordinates business development services (BDS) across seven national zones.

The site is a performance-focused, SEO-optimized multi-page website built with static HTML/CSS/JS and a light PHP backend for contact handling and (upcoming) news updates. It is hosted on cPanel (Apache / PHP 8.2) and uses clean, extension-less URLs via `.htaccess`.

---

## Features

- **5 responsive pages** — Home, About, Leadership, Gallery, Contact — with mobile offcanvas + sticky header navigation.
- **SEO ready** — per-page meta descriptions/keywords, canonical URLs, Open Graph & Twitter cards, JSON-LD structured data (Organization, WebSite, AboutPage, ContactPage, ProfilePage).
- **Clean URLs** — Apache rewrite rules map `/about` → `about.html` and force HTTPS + `www` on `bdst.or.tz`.
- **AJAX contact forms** — validated on both client and server, delivered via **PHPMailer** over SMTP with HTML + plain-text parts.
- **Media gallery** — image grid with a custom vanilla-JS lightbox (keyboard navigation, prev/next, counter).
- **Motion design** — GSAP ScrollTrigger, WOW.js reveals, Swiper sliders, Odometer counters and custom icon font.
- **No browser caching of HTML/CSS/JS** during rollout — scripts pick up updates immediately (see `.htaccess`).

---

## Tech Stack

| Layer      | Technology                                          |
| ---------- | --------------------------------------------------- |
| Frontend   | HTML5, CSS3, Bootstrap, jQuery, Font Awesome Pro    |
| Interaction| GSAP, Swiper, WOW.js, Odometer, MeanMenu, nice-select|
| Backend    | PHP 8.2, PDO (MySQL), PHPMailer                     |
| Web server | Apache (`.htaccess`, mod_rewrite)                   |
| Hosting    | cPanel shared hosting                               |

---

## Project Structure

```
bdst/
├── index.html              # Homepage
├── about.html              # Who we are, vision/mission, pillars
├── leadership.html         # Governance & leadership
├── gallery.html            # Photo gallery + lightbox
├── contact.html            # Contact info, map & AJAX form
├── updates.html            # News/updates listing (PHP-driven)   [in progress]
├── .htaccess               # Clean URLs, HTTPS, caching rules
├── includes/
│   ├── sendmail.php        # SMTP contact-form handler (PHPMailer)
│   └── PHPMailer/src/      # Bundled PHPMailer library
└── assets/
    ├── css/                # Bootstrap, theme & page styles
    ├── js/                 # Vendors + main.js
    └── images/             # Logos, home, about, gallery, testimonial
```

---

## Getting Started

### Local Development

1. Clone the repository:
   ```bash
   git clone https://github.com/atilioobadia-cpu/BDSTanzania.git
   cd BDSTanzania
   ```
2. Serve the folder with any PHP-enabled web server (XAMPP / Laragon / `php -S`):
   ```bash
   php -S localhost:8000
   ```
3. Open `http://localhost:8000` in your browser.

### Deployment (cPanel)

1. Upload the project contents into `public_html/` (or a subfolder).
2. Create a MySQL database + user in **MySQL® Databases**.
3. Copy `mail-config.sample.php` → `mail-config.php` (above `public_html` if possible) and fill in SMTP + DB credentials.
4. Import the schema (see [Updates & News Backend](#updates--news-backend)).
5. Done — the `.htaccess` handles clean URLs, HTTPS redirect and caching.

---

## Configuration

The site reads environment-specific settings from `mail-config.php` (never committed):

```php
<?php
return [
    'toEmail'      => 'info@bdst.or.tz',
    'fromEmail'    => 'no-reply@bdst.or.tz',
    'siteName'     => 'BDS Tanzania',
    'smtpHost'     => 'mail.bdst.or.tz',
    'smtpUsername' => 'no-reply@bdst.or.tz',
    'smtpPassword' => '***',
    'smtpPort'     => 465,
    'smtpEncryption' => 'ssl',           // 'ssl' (SMTPS) or 'tls' (STARTTLS)
    // DB (used by the updates backend)
    'dbHost'       => 'localhost',
    'dbName'       => 'bdst_updates',
    'dbUser'       => 'bdst_admin',
    'dbPass'       => '***',
];
```

---

## Updates & News Backend

A lightweight **PHP + MySQL** admin is planned/under development to let editors publish news updates (image, title, description, date) without touching code:

- `/admin/` — password-protected dashboard for posting, editing and deleting updates.
- `updates.html` — PHP-driven listing with image, title, date, excerpt and pagination.
- Secure image upload to `assets/images/uploads/` with type/size validation.
- All queries via **PDO prepared statements**.

---

## Roadmap

- [x] Static site shell (Home / About / Leadership / Gallery / Contact)
- [x] SMTP contact forms via PHPMailer
- [x] Clean URLs + HTTPS enforcement
- [ ] PHP + MySQL news backend (admin dashboard + `updates.html`)
- [ ] Post detail page with clean URL (`/updates/{slug}`)
- [ ] Category filtering & search across updates

---

## Security Notes

- `mail-config.php` and `*.log` are **gitignored** — never commit credentials or email logs.
- All user input is sanitized (strip tags, email validation) and output is HTML-escaped.
- The contact handler rejects non-POST requests and validates required fields server-side.
- Keep the admin directory restricted (`/admin/.htaccess`) and use strong, hashed passwords.

---

## Contact

BDS Tanzania — Dodoma, United Republic of Tanzania

- Phone: [+255 655 211 777](tel:+255655211777) · [+255 621 371 328](tel:+255621371328)
- Email: [info@bdst.or.tz](mailto:info@bdst.or.tz)
- Website: [https://www.bdst.or.tz](https://www.bdst.or.tz)

---

<p align="center">© 2026 BDS Tanzania. All rights reserved.</p>
