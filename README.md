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
- [Updates & News Backend](#updates--news-backend)
- [Roadmap](#roadmap)
- [Security Notes](#security-notes)
- [Contact](#contact)

---

## Overview

**BDS Tanzania** is the official website of the *Business Development Society of Tanzania* — a voluntary, non-governmental professional association headquartered in Dodoma that sets professional standards, builds practitioner capacity and coordinates business development services (BDS) across seven national zones.

The site is a performance-focused, SEO-optimized multi-page website built with static HTML/CSS/JS and a light PHP backend for contact handling and news updates (PHP + MySQL). It is hosted on cPanel (Apache / PHP 8.2) and uses clean, extension-less URLs via `.htaccess`.

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
├── updates.html            # News/updates listing (PHP-driven, paginated + search)
├── update.html             # Single post detail page (?slug=...)
├── database.sql            # MySQL schema for the updates table
├── mail-config.sample.php  # Template for mail-config.php (gitignored)
├── .htaccess               # Clean URLs, HTTPS, caching + PHP-for-.html rules
├── nexus/                   # Password-protected news backend (renamed from admin/)
│   ├── index.php           # Login
│   ├── dashboard.php       # Manage updates
│   ├── post.php            # Create/edit update + image upload
│   ├── delete.php          # Delete update
│   ├── logout.php
│   ├── auth.php            # Session + CSRF helpers
│   ├── admin.css
│   └── .htaccess
└── includes/
    ├── sendmail.php        # SMTP contact-form handler (PHPMailer)
    ├── db.php              # Config lookup + PDO helper (bdst_db, bdst_e)
    ├── .htaccess           # Denies direct access to includes/
    └── PHPMailer/src/      # Bundled PHPMailer library
```

---

## Getting Started

### Local Development

> Note: the dynamic pages (`updates.html`, `update.html`) are `.html` files executed as PHP by the Apache `.htaccess` rule. Use XAMPP or Laragon (Apache + MySQL + PHP) so that rule applies and the database is available. `php -S` will not parse `.html` files as PHP.

1. Clone the repository:
   ```bash
   git clone https://github.com/atilioobadia-cpu/BDSTanzania.git
   cd BDSTanzania
   ```
2. Serve the folder from XAMPP/Laragon's web root (or any Apache vhost).
3. Create a local MySQL database and a `mail-config.php` (copy from `mail-config.sample.php`).
4. Import `database.sql`, then open the site in your browser.

### Deployment (cPanel)

1. Upload the project contents into `public_html/` (or a subfolder).
2. Create a MySQL database + user in **MySQL® Databases**.
3. Copy `mail-config.sample.php` → `mail-config.php` (one level **above** `public_html` if possible) and fill in SMTP + DB credentials.
4. Import `database.sql` in **phpMyAdmin** to create the `updates` table.
5. Generate the admin password hash and add it to `mail-config.php`:
   ```bash
   php -r "echo password_hash('CHANGE_ME', PASSWORD_DEFAULT);"
   ```
6. Done — the `.htaccess` handles clean URLs, HTTPS redirect, caching and parsing `.html` files as PHP.

---

## Updates & News Backend

A lightweight **PHP + MySQL** admin lets editors publish news updates (image, title, description, date) without touching code:

- `/nexus/` - password-protected dashboard (session + CSRF tokens) for posting, editing, publishing/drafting and deleting updates.
- `updates.html` — PHP-driven listing with featured image, title, date, excerpt, search (`?s=`) and pagination (`?page=`).
- `update.html` — single post detail page (`?slug=...`), with dynamic SEO/OpenGraph meta tags.
- Secure image upload to `assets/images/uploads/` (JPG/PNG/WEBP/GIF, max 2 MB, verified with `getimagesize`).
- All queries use **PDO prepared statements**; output is HTML-escaped via `bdst_e()`.
- `.html` files are executed as PHP by the `.htaccess` `AddHandler` rule (outside the cPanel-managed block).

---

## Roadmap

- [x] Static site shell (Home / About / Leadership / Gallery / Contact)
- [x] SMTP contact forms via PHPMailer
- [x] Clean URLs + HTTPS enforcement
- [x] PHP + MySQL news backend (admin dashboard, `updates.html`, `update.html`)
- [ ] Friendly post URLs (`/updates/{slug}`)
- [ ] Category filtering & search across updates

---

## Security Notes

- `mail-config.php`, `*.log` and `assets/images/uploads/` are **gitignored** — never commit credentials, email logs or user-uploaded images.
- All user input is sanitized (strip tags, email validation) and output is HTML-escaped.
- The contact handler rejects non-POST requests and validates required fields server-side.
- The admin panel uses **PHP sessions** with `password_hash`/`password_verify` authentication, **CSRF tokens** on every form, and `session_regenerate_id()` on login.
- `includes/` denies direct access and PHP execution is blocked inside `assets/images/uploads/` (`.htaccess`).
- Keep the admin directory restricted (`/nexus/.htaccess`) and use a strong password.

---

## Contact

BDS Tanzania — Dodoma, United Republic of Tanzania

- Phone: [+255 655 211 777](tel:+255655211777) · [+255 621 371 328](tel:+255621371328)
- Email: [info@bdst.or.tz](mailto:info@bdst.or.tz)
- Website: [https://www.bdst.or.tz](https://www.bdst.or.tz)

---

<p align="center">© 2026 BDS Tanzania. All rights reserved.</p>
