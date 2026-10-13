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
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-green.svg" alt="License: MIT"></a>
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
- [Membership Registration](#membership-registration)
- [Geographic Reference Data](#geographic-reference-data)
- [Updates & News Backend](#updates--news-backend)
- [Roadmap](#roadmap)
- [Security Notes](#security-notes)
- [Contact](#contact)

---

## Overview

**BDS Tanzania** is the official website of the *Business Development Society of
Tanzania*, a voluntary, non-governmental professional association headquartered
in Dodoma that sets professional standards, builds practitioner capacity and
coordinates business development services (BDS) across seven national zones.

The site is a performance-focused, SEO-optimized multi-page website built with
static HTML/CSS/JS and a light PHP backend for contact handling, news updates,
membership registration and an admin panel (PHP + MySQL). It is hosted on cPanel
(Apache / PHP 8.2) and uses clean, extension-less URLs via `.htaccess`.

---

## Features

- **Public site** - Home, About, Leadership, Gallery, Contact, plus service and
  membership pages (business advisory, capacity building, SME support,
  professional development, how-to-join, why-join, membership benefits) and
  legal pages (privacy policy, terms and conditions).
- **Membership registration** - public, validated application form that stores
  members in MySQL and issues a reference number (`BDS-TZ-<year>-<seq>`).
  New submissions are **auto-approved** and immediately count as members.
- **Member directory** - public listing of approved members (name, firm,
  region, district, professional status).
- **Status lookup** - applicants can check their status by reference number.
- **News / updates** - PHP + MySQL listing and detail pages with search,
  pagination and dynamic SEO/Open Graph meta.
- **Admin panel** (`/nexus/`) - password-protected management of news updates
  and member registrations (review, edit, approve/reject, download CV).
- **SEO ready** - per-page meta, canonical URLs, Open Graph & Twitter cards and
  JSON-LD structured data.
- **Clean URLs** - Apache rewrites map `/about` -> `about.html` and force HTTPS
  + `www` on `bdst.or.tz`.
- **AJAX contact forms** - validated client- and server-side, delivered via
  **PHPMailer** over SMTP with HTML + plain-text parts.
- **Motion design** - GSAP ScrollTrigger, WOW.js reveals, Swiper sliders,
  Odometer counters and a custom icon font.

---

## Tech Stack

| Layer       | Technology                                              |
| ----------- | ------------------------------------------------------- |
| Frontend    | HTML5, CSS3, Bootstrap, jQuery, Font Awesome Pro        |
| Interaction | GSAP, Swiper, WOW.js, Odometer, MeanMenu, nice-select    |
| Backend     | PHP 8.2, PDO (MySQL), PHPMailer                          |
| Web server  | Apache (`.htaccess`, mod_rewrite)                        |
| Hosting     | cPanel shared hosting                                    |

No framework, no Composer and no build step. Front-end libraries are vendored
under `assets/`.

---

## Project Structure

```
BDSTanzania/
|-- index.html                 # Homepage
|-- about.html                 # Who we are, vision/mission, pillars
|-- leadership.html            # Governance & leadership
|-- gallery.html               # Photo gallery + lightbox
|-- contact.html               # Contact info, map & AJAX form
|-- membership-benefits.html   # Membership pages
|-- how-to-join.html
|-- why-join.html
|-- business-advisory-support.html
|-- capacity-building.html
|-- professional-development.html
|-- sme-support.html
|-- privacy-policy.html        # Legal
|-- terms-and-conditions.html
|-- 404.html
|-- updates.php                # News listing (PHP-driven, paginated + search)
|-- update.php                 # Single post detail (?slug=...)
|-- .htaccess                  # Clean URLs, HTTPS, caching, PHP-for-.html
|-- register/                  # Public membership registration
|   |-- index.php              # Application form
|   |-- members.php            # Public member directory
|   |-- check.php              # Status lookup by reference number
|   |-- success.php            # Submission confirmation
|   |-- download.php           # Streams approval documents for approved members
|   |-- uploads/               # Uploaded CVs (blocked from direct web access)
|   `-- register.css
|-- nexus/                     # Staff admin (login required)
|   |-- index.php              #   Login
|   |-- dashboard.php          #   Manage news updates
|   |-- registrations.php      #   List/manage member registrations
|   |-- registration.php       #   View/edit a single registration
|   |-- cv.php                 #   Streams a CV (authenticated)
|   |-- auth.php               #   Session + CSRF helpers
|   `-- ...
|-- includes/                  # Shared PHP (denied from direct web access)
|   |-- db.php                 #   Config lookup + PDO helper (bdst_db, bdst_e)
|   |-- forms.php              #   Registration option lists + helpers
|   |-- geo.php                #   Official 31 regions / 170 districts (generated)
|   |-- reg-mail.php           #   Registration notification emails
|   |-- sendmail.php           #   SMTP contact-form handler
|   |-- approval-docs.php      #   Approval document helpers
|   `-- PHPMailer/src/         #   Bundled PHPMailer library
|-- assets/
|   |-- css/  js/  fonts/  images/
|   `-- geo/                   # Region/district GeoJSON for the map
|-- database/
|   |-- bdst_news.sql          # News schema
|   |-- bdst_registrations.sql # Member registration schema
|   |-- update_2026_08_approval_features.sql
|   `-- update_2026_10_geo_dashboard.sql   # Geo reference tables + index
|-- tools/geo/                 # Generators for geo.php / SQL / GeoJSON
`-- mail-config.sample.php     # Template for mail-config.php (gitignored)
```

---

## Getting Started

### Local Development

> The dynamic pages are executed as PHP through the Apache `.htaccess` rules.
> Use XAMPP or Laragon (Apache + MySQL + PHP) so those rules apply and the
> database is available. `php -S` will not parse `.html` files as PHP.

1. Clone the repository:
   ```bash
   git clone https://github.com/atilioobadia-cpu/BDSTanzania.git
   cd BDSTanzania
   ```
2. Serve the folder from XAMPP/Laragon's web root (or any Apache vhost).
3. Copy `mail-config.sample.php` -> `mail-config.php` and fill in DB + SMTP
   values. The config file is gitignored.
4. Import the schemas in `database/` (`bdst_news.sql`,
   `bdst_registrations.sql`, then any `update_*.sql` files in date order).
5. Open the site in your browser.

### Deployment (cPanel)

1. Back up the live site and database first.
2. Upload the project contents into `public_html/`.
3. Create a MySQL database + user in **MySQL Databases**.
4. Put `mail-config.php` **one level above** `public_html` (the code looks there
   first) and fill in SMTP + DB credentials.
5. Import the schemas in `database/` via **phpMyAdmin**.
6. Generate the admin password hash and add it to `mail-config.php`:
   ```bash
   php -r "echo password_hash('CHANGE_ME', PASSWORD_DEFAULT);"
   ```
7. Confirm `.htaccess` handles clean URLs, HTTPS, caching and parsing `.html`
   files as PHP.

---

## Membership Registration

- The public form lives at `/register/`. It is CSRF-protected, has a honeypot
  field and validates all input server-side.
- Applicants upload a CV (PDF/DOC/DOCX, max 10 MB) and receive a reference
  number (`BDS-TZ-<year>-<seq>`).
- **New submissions are auto-approved** (`status = 'approved'`), so they are
  immediately counted as members and appear in the member directory.
- The registration captures **region** and **district**. These are the source of
  truth for any location-based reporting.
- Staff manage applications under `/nexus/registrations.php` (review, edit,
  change status, download CV).

---

## Geographic Reference Data

The official administrative geography lives in `includes/geo.php` (31 regions,
170 districts) with area centroids, and as simplified boundaries in
`assets/geo/` (`tz-regions.geojson`, `tz-districts.geojson`).

- These are **generated** files. Regenerate with the scripts in `tools/geo/`
  (see `tools/geo/README.md`); do not hand-edit them.
- Sources: geoBoundaries (gbHumanitarian TZA ADM1/ADM2; Tanzania NBS / UN OCHA
  ROSA, **CC BY 3.0 IGO**) and open-admin-data (**CC BY 4.0**). Keep the map
  attribution.
- Reference points are administrative **area centroids**, used to place area
  markers. They are never residential coordinates.

---

## Updates & News Backend

A lightweight PHP + MySQL admin lets editors publish news updates (image, title,
description, date) without touching code:

- `/nexus/` - password-protected dashboard (session + CSRF tokens) for posting,
  editing, publishing/drafting and deleting updates.
- `updates.php` - listing with featured image, title, date, excerpt, search
  (`?s=`) and pagination (`?page=`).
- `update.php` - single post detail (`?slug=...`) with dynamic SEO/Open Graph
  meta tags.
- Secure image upload to `assets/images/uploads/` (JPG/PNG/WEBP/GIF, max 2 MB,
  verified with `getimagesize`).
- All queries use **PDO prepared statements**; output is HTML-escaped via
  `bdst_e()`.

---

## Roadmap

- [x] Static site shell (Home / About / Leadership / Gallery / Contact)
- [x] SMTP contact forms via PHPMailer
- [x] Clean URLs + HTTPS enforcement
- [x] PHP + MySQL news backend (admin dashboard, `updates.php`, `update.php`)
- [x] Membership registration + public member directory (+ auto-approval)
- [x] Official 31-region / 170-district geographic reference data + boundaries
- [ ] Searchable region/district dropdowns on the registration form
- [ ] Public interactive members geographic dashboard (map + statistics)
- [ ] Friendly post URLs (`/updates/{slug}`)
- [ ] Category filtering & search across updates

---

## Security Notes

- `mail-config.php`, `*.log`, `register/uploads/*` and approval documents are
  **gitignored** - never commit credentials, logs or uploaded files.
- `mail-config.php` should live **above** `public_html` so it is never web-served.
- Uploaded CVs in `register/uploads/` are **blocked from direct web access**
  (`.htaccess`); they are served only by the authenticated `nexus/cv.php`.
- `includes/` denies direct access and PHP execution is blocked inside
  `assets/images/uploads/`.
- All user input is sanitized and output is HTML-escaped; every query uses
  prepared statements.
- The admin panel (`/nexus/`) uses PHP sessions with `password_hash` /
  `password_verify`, CSRF tokens on every form, and `session_regenerate_id()`
  on login. Use a strong password.
- The contact handler rejects non-POST requests and validates required fields
  server-side.

---

## Contact

BDS Tanzania - Dodoma, United Republic of Tanzania

- Phone: [+255 655 211 777](tel:+255655211777) - [+255 621 371 328](tel:+255621371328)
- Email: [info@bdst.or.tz](mailto:info@bdst.or.tz)
- Website: [https://www.bdst.or.tz](https://www.bdst.or.tz)

---

<p align="center">(c) 2026 BDS Tanzania. All rights reserved.</p>
