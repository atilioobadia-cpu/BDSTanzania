# BDS Tanzania - Deployment Runbook

How the live site (`https://www.bdst.or.tz`) is updated safely: **backup first,
byte-exact upload, verify against the public URL, clean up, then commit.**
Follow the phases in order; never skip the backup or the read-back.

## Environment

| Item | Value / location |
| ---- | ---------------- |
| Server | cPanel shared hosting - `srv1.wazohost.net:2083`, user `bdstortz` |
| Web root | `/home/bdstortz/public_html` (PHP 8.2, MySQL 5.7) |
| DB config | `mail-config.php` **above** the web root (gitignored copy template: `mail-config.sample.php`) |
| Live DB | `bdstortz_dbms` (tables `bds_registrations`, `bds_news`, geo reference tables) |
| Repo | `C:\Users\atili\Developer\BDSTanzania` (+ `git` remote `atilioobadia-cpu/BDSTanzania`) |
| Local preview | `php -S 127.0.0.1:8123 -t <docroot>` against a synced copy of the repo |
| Playwright verify | `C:\Users\atili\AppData\Local\Temp\opencode\puppet-verify\` (puppeteer-core, Chrome) |
| cPanel API token | Rotated credentials file (kept out of the repo), e.g. `BDST-ROTATED-CREDENTIALS-*.txt` |

> Never commit the cPanel token, DB password or SMTP password. The deploy
> scripts that carry a token live only in the temp work area
> (`C:\Users\atili\AppData\Local\Temp\opencode\bdst-live\`), never in the repo.

## 0. Pre-flight (before touching production)

```powershell
# PHP syntax on every changed PHP file
php -l dashboard.php
php -l includes/dashboard.php
php -l api/dashboard/trends.php

# DB-less regression suites (they snapshot the expected shapes)
php C:\Users\atili\AppData\Local\Temp\opencode\bdst-live\test_dashboard.php
php C:\Users\atili\AppData\Local\Temp\opencode\bdst-live\test_regions.php
php C:\Users\atili\AppData\Local\Temp\opencode\bdst-live\test_trends.php

# Re-sync the preview docroot with the repo (assets/ is a junction to the repo;
# the copied .php files must be refreshed), then drive the preview with
# puppeteer: heading order, skip link, keyboard on pins/polygons, console errors.
pushd C:\Users\atili\AppData\Local\Temp\opencode\bdst-preview
  Copy-Item C:\Users\atili\Developer\BDSTanzania\dashboard.php  .\dashboard.php -Force
  Copy-Item C:\Users\atili\Developer\BDSTanzania\includes\dashboard.php .\includes\dashboard.php -Force
popd
```

A deploy is green only when: preview renders with `0` console errors, the
keyboard flow (Tab -> pin -> Enter -> district view) works, headings are
`h1 -> h2` with no skipped levels, and the regression suites pass.

## 1. Back up the live database (mandatory)

Every production change is preceded by a full dump stored **above** the web
root, then downloaded and SHA-checked locally:

1. Upload the temp `_bk_*.php` backup helper to `public_html/`.
2. Call it from the live host: it dumps `bdstortz_dbms` (mysqldump) to
   `/home/bdstortz/backups/bdst-backup-<phase>-<YYYYMMDD-HHMMSS>.sql`.
3. Download the dump, verify its SHA-256, and confirm the file contains the
   expected table list (`SHOW TABLES` equivalent: `CREATE TABLE `bds_registrations``
   etc.) - an empty/truncated dump is worse than none.
4. Keep a copy in `bdst-live\backup\` for the session; delete the helper.

Record the dump path in the deploy log (see §7).

## 2. Publish via a byte-exact helper (do not use cPanel File Manager)

cPanel's File Manager round-trips content and corrupts byte layout. Instead:

1. Upload a temp `_up_*.php` helper (POST, basic auth, token-gated) to
   `public_html/` using a fresh random name + token each deploy.
2. For each changed file, upload `path -> content` with the helper
   (`save_file_content` style), creating parent directories as needed.
   Always include every file touched by the phase - remember hidden files
   such as `.htaccess` and `robots.txt`.
3. **Read back** each path and compare SHA-256 (helper `sha1_file`/`hash` on
   the server vs. the local file). Any mismatch = stop and diff, do not
   proceed. The helper must also verify writeability, not just report success.
4. Also re-download the **public URL** of each file (except `.htaccess`,
   which Apache refuses to serve) and compare its digest with the upload.

Typical Phase 5/6 file set (adjust per phase):

```
dashboard.php
.htaccess
assets/js/dashboard.js
assets/css/dashboard.css
assets/vendor/leaflet/.htaccess
assets/geo/.htaccess
robots.txt
```

## 3. Clean up helpers (mandatory)

Delete `_up_*.php` / `_chk_*.php` / `_bk_*.php` from the web root via the
**legacy API2 endpoint** - `UAPI` has no file-remove for Fileman:

```
POST https://srv1.wazohost.net:2083/json-api/cpanel
cpanel_jsonapi_user=bdstortz
cpanel_jsonapi_apiversion=2
cpanel_jsonapi_module=Fileman
cpanel_jsonapi_func=fileop
op=unlink
sourcefiles=%2Fhome%2Fbdstortz%2Fpublic_html%2F_up_xxxxxxxx.php
```

then confirm each helper returns HTTP 404 from the public URL. The root
`.htaccess` denies any stray `_up_*`/`_chk_*`/`_bk_*` files as defence in depth.

## 4. Live verification

```powershell
# Core pages
curl.exe -s -o NUL -w "%{http_code}" https://www.bdst.or.tz/                # 200
curl.exe -s -o NUL -w "%{http_code}" https://www.bdst.or.tz/dashboard/      # 301 (canonical -> /dashboard)
curl.exe -s -o NUL -w "%{http_code}" https://www.bdst.or.tz/dashboard       # 200
curl.exe -s -o NUL -w "%{http_code}" https://www.bdst.or.tz/robots.txt      # 200

# Security headers present on every response
curl.exe -sI https://www.bdst.or.tz/assets/css/dashboard.css |
  Select-String "x-content-type-options|x-frame-options|referrer-policy|permissions-policy"

# Cache policy: vendor assets long-cached (immutable)...
curl.exe -sI https://www.bdst.or.tz/assets/vendor/leaflet/leaflet.js |
  Select-String "cache-control"

# ...GeoJSON long-cached with the right Content-Type...
curl.exe -sI https://www.bdst.or.tz/assets/geo/tz-regions.geojson |
  Select-String "cache-control|content-type"

# ...while everything else stays no-store (JS detects updates)
curl.exe -sI https://www.bdst.or.tz/assets/js/dashboard.js |
  Select-String "cache-control"

# Sensitive files blocked (expect 403)
curl.exe -s -o NUL -w "%{http_code}" https://www.bdst.or.tz/README.md       # 403
curl.exe -s -o NUL -w "%{http_code}" https://www.bdst.or.tz/database/database.sql # 403

# Dashboard APIs still noindex + no-store
curl.exe -sI https://www.bdst.or.tz/api/dashboard/summary.php |
  Select-String "x-robots-tag|cache-control"
```

Then a **browser smoke on the live URL** (puppeteer): page loads, exactly 1
pin or the expected pin count renders, region polygons are focusable, satellite
toggle still switches layers, and the console is free of errors.

Also confirm `.htaccess` was applied at all: the `nosniff` / `X-Frame-Options`
headers on the dashboard page are the proof (Apache returns 403 for the file
itself, so headers, not a fetch of `.htaccess`, are the read-back).

## 5. Cache-busting conventions

- `assets/vendor/leaflet/` and `assets/geo/` are pinned & immutable
  (`max-age=31536000, immutable`) - browsers keep them for a year.
- The GeoJSON URLs are **content-addressed by a version query** in
  `dashboard.php` (`assets/geo/tz-regions.geojson?v=2`). Bump `?v=` whenever a
  `.geojson` changes; the old copy may stay cached for a year.
- Everything else (own CSS/JS/HTML) is `no-store` on purpose so changes are
  detected immediately via `Last-Modified`. Long-caching the whole site is a
  deliberate future optimisation, not a small change - see §8.

## 6. Rollback

1. Restore the previous dump: upload it (or re-dump from the running DB if
   only files changed) - `mysql` the `.sql` back into `bdstortz_dbms`.
2. Re-upload the previous versions of the changed files from git
   (`git show <sha>:<path>`), byte-exact, via the same helper flow.
3. Re-verify with §4. There is no partial state: file changes are atomic per
   file and each helper upload is verified before the next begins.

## 7. Commit + session log

- Commit the repo changes with a phase-scoped message
  (`Phase 5: a11y, performance, hardening` + `Phase 6: docs + runbook`).
  Push to `origin/main` only after live verification passes.
- The working report
  (`BDSTanzania_Geographic_Dashboard_Audit_and_Recommendations.md`) stays
  **untracked** by design - it is a deliverable, not part of the codebase.
- Session log (kept in the temp work area): backups taken/restored, helper
  names + tokens used, SHA read-backs, live verification results, deploy
  start/end times.

## 8. Known limitations / future work

- **CSP is intentionally not set.** The page ships inline JSON config
  (`bdst-dashboard-config`), Leaflet applies inline style attributes, and the
  shared template still injects inline scripts. A strict CSP needs a nonce
  pipeline across the whole template before it can be added safely.
- **Site-wide caching** (template CSS/JS is currently `no-store`, so every
  page loads ~500 KB of CSS) is left as-is because the site relies on
  `Last-Modified` change detection. Optimising this means versioning the
  template assets too - do it as a separate, site-wide change.
- **GeoJSON downloads are not rate-limited**; payloads are small and cached
  client-side, so this is acceptable at current traffic.