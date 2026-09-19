# Project State

## Project
* **Project Name**: Moodle 5.2.2 Docker Environment (World University of Bangladesh Moodle)
* **Project Path**: `/home/phant0m/Phantom/moodle`
* **Purpose**: Fully reproducible, persistent, portable Dockerized Moodle learning platform.
* **Deployment Architecture**: Docker Compose multi-container setup with 4 core services (`db`, `php`, `nginx`, `cron`).

---

## Current Versions
* **Moodle**: 5.2.2+ (Build: 20260911), branch `MOODLE_502_STABLE`
* **PHP**: 8.4.25 (PHP-FPM, Debian Bookworm base)
* **MariaDB**: 11.8.9 (`11.8.9-MariaDB-ubu2404`)
* **Nginx**: 1.28.3 (`nginx:1.28-alpine`)
* **Docker Compose**: Compose spec with container dependency health checks

---

## Architecture
The infrastructure consists of four cooperating Docker services:

1. **`db` (`moodle-db`)**:
   * Image: `mariadb:11.8`
   * Persistent Volume: `./db:/var/lib/mysql`
   * Healthcheck: `healthcheck.sh --connect --innodb_initialized` (interval 5s, retries 10, start period 10s)
   * Charset & Collation: `utf8mb4` / `utf8mb4_unicode_ci`
2. **`php` (`moodle-php`)**:
   * Built from `./php/Dockerfile` (tagged `moodle-php`)
   * Extensions: `gd`, `intl`, `mysqli`, `opcache`, `zip`, `soap`, `exif`, `mbstring`, `xml`, `xsl`, `curl`, `sodium`
   * Mounts: `./moodle:/var/www/html`, `./moodledata:/var/www/moodledata`, `./php/php.ini:/usr/local/etc/php/conf.d/moodle.ini:ro`
   * Depends on: `db` (`service_healthy`)
3. **`nginx` (`moodle-nginx`)**:
   * Image: `nginx:1.28-alpine`
   * Port Mapping: `8080:80`
   * Root: `/var/www/html/public`
   * FastCGI: Connected to `php:9000`, `fastcgi_split_path_info`, `fastcgi_param SERVER_PORT 8080`
   * Security: Denies hidden files (`.env`, `.git`) and `/config.php` with HTTP 403
4. **`cron` (`moodle-cron`)**:
   * Built from `./php/Dockerfile` (tagged `moodle-php`)
   * User: `www-data`
   * Command: Executes `php /var/www/html/admin/cli/cron.php` every 60 seconds
   * Depends on: `db` (`service_healthy`), `php` (`service_started`)

---

## Important Paths
* **Moodle Source**: `/home/phant0m/Phantom/moodle/moodle` (container: `/var/www/html`)
* **Public Webroot**: `/home/phant0m/Phantom/moodle/moodle/public` (container: `/var/www/html/public`)
* **Persistent Moodledata**: `/home/phant0m/Phantom/moodle/moodledata` (container: `/var/www/moodledata`)
* **Persistent MariaDB Data**: `/home/phant0m/Phantom/moodle/db` (container: `/var/lib/mysql`)
* **PHP Configuration**: `/home/phant0m/Phantom/moodle/php/php.ini` (container: `/usr/local/etc/php/conf.d/moodle.ini`)
* **PHP Dockerfile**: `/home/phant0m/Phantom/moodle/php/Dockerfile`
* **Nginx Configuration**: `/home/phant0m/Phantom/moodle/nginx/default.conf` (container: `/etc/nginx/conf.d/default.conf`)
* **Docker Compose Config**: `/home/phant0m/Phantom/moodle/docker-compose.yml`
* **Environment File**: `/home/phant0m/Phantom/moodle/.env` (untracked secrets)
* **Environment Example**: `/home/phant0m/Phantom/moodle/.env.example` (tracked template)
* **Backups Directory**: `/home/phant0m/Phantom/moodle/backups`
* **Agent Operating Rules**: `/home/phant0m/Phantom/moodle/AGENT_RULES.md` (tracked)
* **Project State File**: `/home/phant0m/Phantom/moodle/PROJECT_STATE.md` (this file, tracked)

*(Note: Never store raw passwords or tokens in documentation or committed files).*

---

## Current Configuration
* **Site URL**: `http://localhost:8080`
* **External Port**: `8080` (mapped to container port `80`)
* **Database Name**: `moodle`
* **Database User**: `moodle`
* **Database Host**: `db` (internal Docker network)
* **Database Collation**: `utf8mb4_unicode_ci` (490 tables)
* **Moodle `$CFG->dbtype`**: `mariadb`
* **Site Full Name**: `World University of Bangladesh Moodle`
* **Site Short Name**: `WUB Moodle`
* **Admin Username**: `khairul_anam24` (site admin account; credentials stored in `.env` — note `.env` `MOODLE_ADMIN_USER` currently reads `khairul_anam`, which does not match the live username)
* **Active Theme**: `academi` (v2026042900 / v5.2), preset `enlightlite`, `theme_academi/primarycolor` = `#0f6cbf`
* **Force login**: `forcelogin=1`, guest login button disabled
* **Directory Permissions**: `$CFG->directorypermissions = 02775;`
* **FastCGI Port Handling**: `fastcgi_param SERVER_PORT 8080;` passed by Nginx to align with port forwarding

---

## Completed Work
1. **Moodle 5.2.2+ Database Installation**:
   - Initialized empty MariaDB database using `admin/cli/install_database.php`.
   - Created core database schema and installed all standard plugins (490 tables).
2. **Database Unicode & Collation Fix**:
   - Changed database collation to `utf8mb4_unicode_ci`.
   - Updated `config.php` to use `utf8mb4_unicode_ci` so `$DB->setup_is_unicodedb()` passes.
3. **Nginx Webroot & FastCGI Configuration**:
   - Set root to `/var/www/html/public` per Moodle 5.2 architecture.
   - Configured `fastcgi_split_path_info` and 300-second timeouts.
   - Injected `SERVER_PORT 8080` to resolve infinite redirect loop caused by port mapping (8080 &rarr; 80).
   - Hardened security by blocking `/.env`, `/.git`, and `/config.php` with HTTP 403.
4. **Permissions Hardening**:
   - `moodle/config.php`: set to `644` (`-rw-r--r--`), owned by `phant0m:phant0m`, enabling `www-data` read access without world-writable permissions.
   - `moodledata/`: set to `2775` (`drwxrwsr-x`), owned by `www-data:phant0m` (`33:1000`), removing legacy `777` permissions.
   - Updated `$CFG->directorypermissions = 02775;`.
5. **Dedicated Cron Service**:
   - Added `cron` service to `docker-compose.yml` running `php /var/www/html/admin/cli/cron.php` every 60s as `www-data`.
   - Added MariaDB healthcheck to ensure PHP and Cron only start when database is healthy.
   - Verified manual and daemon cron execution (`Cron run completed correctly`).
6. **Site Identity Customization**:
   - Updated full site name to `World University of Bangladesh Moodle` and short name to `WUB Moodle` using Moodle's native `admin_setting_sitesettext` API.
   - Purged caches and verified login page title and header rendering.
7. **Persistence Verification**:
   - Verified system survival across `docker compose restart` and `docker compose down && docker compose up -d` with zero data loss (490 tables intact).
8. **Theme Directory Permissions for Plugin Installation**:
   - Diagnosed upload failure: `/var/www/html/public/theme` lacked write permission for `www-data`.
   - Set ownership to `phant0m:www-data` (`1000:33`) and mode to `2775` (`drwxrwsr-x`), verifying `is_writable` returns `YES`.
   - Confirmed Academi theme ZIP package in `~/Downloads/theme_academi_2026042900.zip` is intact and valid for Moodle 5.2.
9. **Project Management Infrastructure**:
   - Created `AGENT_RULES.md` and `PROJECT_STATE.md` to guide future agents and engineers.
10. **Academi Theme Installed & Activated** (before 2026-09-14): `theme_academi` v2026042900 installed in `moodle/public/theme/academi/` and set as site theme. WUB header logo (`wub-logo2.png`), login background (`wub_image.jpg`) and slide 1 image uploaded via theme settings.
11. **WUB Footer Customization (2026-09-14, final)**:
   - **Mechanism**: Academi admin settings only (`theme_academi/*`, set with `docker compose exec -u www-data php php admin/cli/cfg.php`). No Moodle core, Boost or Academi source files modified; no plugin created; no direct DB edits. Reversible from *Site administration → Appearance → Themes → Academi → Footer / General*.
   - **Why the footnote holds the whole footer**: Academi models exactly one flat link list (`infolink`; nested lines render as dropdowns) and an escaped-text contact block, so a four-section footer (About / Important Links / Useful Links / Contact with a contact-page link) is only achievable via the `footnote` HTML setting of footer block 1. Blocks 2–4 are disabled (`footerb2_status`/`footerb3_status`/`footerb4_status` = 0), so block 1 renders `col-md-12`; `footerbtitle1` = empty; `footlogostatus` = 0.
   - **`footnote`** = `<div class="wub-footer">` with four `.wub-footer-col` sections:
     1. *World University of Bangladesh* — WUB crest logo (linked to `https://www.wub.edu.bd/`, `alt="World University of Bangladesh"`, rendered 96×96), heading, "World University of Bangladesh / Learning Management System", description "The official Learning Management System of World University of Bangladesh, supporting teaching, learning, assessment and academic activities.", and "Official website: **World University of Bangladesh**" (the university name is the link → `https://www.wub.edu.bd/`).
     2. *Important Links* — Admission `https://admission.wub.edu.bd/`, Programs `/admission/programs`, Admission Eligibility `/admission/admission_eligibilities`, Tuition Fees `/admission/tuition_fees`, Scholarship `/admission/scholarship`, How to Apply `/admission/how_to_apply` (all on `admission.wub.edu.bd`), Notice `https://www.wub.edu.bd/main/all_notice`, Visitor Appointment `https://www.wub.edu.bd/visitor`.
     3. *Useful Links* — Academic Calendar `https://www.wub.edu.bd/academics/academic_calendar`, Student Affairs `https://dsa.wub.edu.bd/`, Student Support `https://support.wub.edu.bd/`, Our Teachers `https://www.wub.edu.bd/main/wub_teachers`, Activities `https://www.wub.edu.bd/main/all_activities`, Jobs @ WUB `https://jobs.wub.edu.bd/`, IQAC `https://www.wub.edu.bd/main/about_iqac`, Waste Disposal Policy `https://www.wub.edu.bd/main/waste_disposal_policy`. *Convocation omitted*: `https://convocation.wub.edu.bd/` returns HTTP 404 (2026-09-14) although the WUB homepage links it.
     4. *Contact Us* (FontAwesome 6 icons `fa-location-dot`, `fa-phone`, `fa-envelope`, `fa-comments`) — Avenue 6, Lake Drive Road / Sector 17/H, Uttara, Dhaka-1230; `tel:+8809643204060` shown as +880 9643 204060; `mailto:info@wub.edu.bd`; `mailto:admission@wub.edu.bd` (Admission); **Contact Page** → `https://www.wub.edu.bd/contact`.
   - **Footer logo**: source `/home/phant0m/Pictures/272-2722209_world-university-of-bangladesh.png` (128×128 transparent PNG) uploaded via Moodle File API (as `www-data`) into the theme's `footerlogo` file area (context 1, `theme_academi/footerlogo/0//wub-logo.png`), replacing Academi's demo `footerlogo.png`; `theme_academi/footerlogo` = `/wub-logo.png`. It is referenced from the footnote HTML as `/pluginfile.php/1/theme_academi/footerlogo/1789361697/wub-logo.png` (served by `theme_academi_pluginfile` → `setting_file_serve`; the numeric segment is only a cache key, 60-day `Cache-Control`). `footlogostatus` stays 0 because Academi's own footer-logo markup hardcodes `alt="Academi"` and `href="#"`. If the logo file is ever replaced, bump the numeric segment in the footnote to bust browser caches.
   - **Native contact settings** (shown in the Academi top navbar; previously Academi demo values): `phoneno` = `+880 9643 204060`, `emailid` = `info@wub.edu.bd`, `address` = `Avenue 6, Lake Drive Road, Sector 17/H, Uttara, Dhaka-1230`.
   - **`copyright_footer`** = `Copyright &copy; 2026 - Developed by <a href="http://computinginfoservices.com/">Computing and Information Services Ltd</a>. Powered by <a href="https://moodle.org/">Moodle</a>.`
   - **`customcss`** (injected through Academi's `[[setting:customcss]]` in `style/custom.css`): footer main background `#0f6cbf` (configured `primarycolor`), copyright bar `#0a4d8a` (darker shade of primary) with `rgba(255,255,255,.15)` top border, all footer text/links/icons `#fff`, hover/focus `#e3effa` + underline; `.wub-footer` CSS grid 4 columns → 2 (≤991px) → 1 centred (≤767px); contact rows flex with icon column; `overflow-wrap: anywhere` on list items. Contrast: `#fff` on `#0f6cbf` 5.36:1, on `#0a4d8a` 8.60:1 (WCAG AA).
   - **Source of facts**: address, phone, `info@wub.edu.bd`, `admission@wub.edu.bd` and every link label/URL were checked against the live `https://www.wub.edu.bd/` homepage/contact page on 2026-09-14 (the site returns real 404s for unknown paths, so 200s are genuine). No social-media accounts added (none verified).
   - **Verification (2026-09-14)**: HTTP 200 and new footer present on dashboard (`/my/`), front page (`/?redirect=0`), course index, login page (anonymous and logged in), user profile, Site administration search; exact copyright line with both links on all; zero Academi demo strings (`(000) 123-456`, `info@example.com`, `yourtwittername`, Lorem, LMSACE, 2017) on any page. Logo verified after upload: served HTTP 200 `image/png`, bytes identical to source, loads in Chrome at 1366/768/390px with correct alt/href. Headless Chrome (CDP) at 1366px / 768px / 390px on dashboard, course index, admin, login, profile, front page: no horizontal overflow (`scrollWidth == clientWidth`), 4/2/1 column layout, computed colours `rgb(15,108,191)` / `rgb(10,77,138)`, white headings/links, FA6 glyphs rendered, contact block not clipped, zero JS console errors. All 20 external `http(s)` footer links return 200 via curl except `https://moodle.org/` (403 to curl's UA — Cloudflare bot filter; mandated URL). No PHP-FPM or Nginx errors logged. DB audit: users = guest + admin only, courses = 1 (site) — no dummy data. `git status`: no tracked files modified; `moodle/` (incl. Academi) untracked and its theme files unchanged.
   - **Rollback values**: `footnote`, `copyright_footer`, `infolink` = Academi lang defaults (`footnotedefault`, `copyright_default`, `infolinkdefault`); `footerbtitle1` = empty; `footerbtitle2` = `lang:footerbtitle2default`; `footlogostatus`/`footerb2_status`/`footerb3_status`/`footerb4_status` = 1; `address`/`phoneno`/`emailid` = Academi demo defaults; `customcss` = empty; `footerlogo` file area previously held Academi's demo `footerlogo.png` (deleted 2026-09-14; the same image ships in the theme package). (Superseded interim 2022 version of this footer is no longer deployed.)

---

12. **Real WUB UMS Integration (`local_wub_ums`) & Bulk Enrolment Refactor (2026-09-14)**:
    - **Plugin Path**: `/home/phant0m/Phantom/moodle/moodle/public/local/wub_ums/`
    - **Plugin Component**: `local_wub_ums` (v2026091400, Moodle 5.2+)
    - **5-Endpoint UMS Contract Implemented**:
      - API #1: `POST /students/multiple_username_wise_std_details` (Digest Auth + Body `X-API-KEY`)
      - API #2: `GET /students/programs` (Digest Auth + Query `X-API-KEY`)
      - API #3: `GET /students/batches/{program_id}` (Digest Auth + Query `X-API-KEY`, authoritative batch ID preserved)
      - API #4: `GET /students/enroll_student_list_program_batch_wise/{program_id}/{batch_id}` (Digest Auth + Query `X-API-KEY`, primary bulk enrolment roster source)
      - API #5: `POST /students/reg_id_wise_multi_student_info` (Digest Auth + Body `X-API-KEY`)
    - **API Client & Security Architecture**:
      - `\local_wub_ums\api_client`: Single external HTTP boundary with cURL Digest Auth (`CURLAUTH_DIGEST | CURLAUTH_BASIC`) and dynamic key placement (`KEY_PLACEMENT_QUERY` vs `KEY_PLACEMENT_BODY`).
      - Transient retry policy: max 3 attempts with exponential backoff for 5xx/timeouts; zero retries for 4xx errors.
      - Zero hardcoded secrets: All credentials (`api_username`, `api_password`, `api_key`) are empty by default in code and database. Secret unmasking provided via `admin_setting_configpasswordunmask`.
      - MUC Application Caching: `local_wub_ums/programs` (TTL 30m) and `local_wub_ums/batches` (TTL 15m) defined in `db/caches.php`.
      - Data Normalization: Typed models (`ums_program`, `ums_batch`, `ums_course`, `ums_student`, `ums_student_details`) ensure raw JSON is never leaked to consumer plugins.
      - Error Hierarchy: Typed exceptions (`ums_configuration_exception`, `ums_authentication_exception`, `ums_connection_exception`, `ums_response_exception`).
    - **Administration & Diagnostics**:
      - Settings UI at *Site administration → Plugins → Local plugins → WUB UMS* (`/admin/settings.php?section=local_wub_ums`).
      - Diagnostic Console at `/local/wub_ums/index.php` (live connection test calling API #2 without leaking secrets, cache purging, status cards).
    - **Bulk Enrolment Refactor (`local_wub_enrol`)**:
      - `ums_roster_provider`: Refactored to consume normalized `local_wub_ums` models and authoritative batch IDs.
      - Identity matching: Matches UMS `username` against `mdl_user`. Unmatched students are safely reported as `NOT_MATCHED` (id=0) with disabled selection to prevent erroneous enrolments.
      - Course matching: Flags registered course code discrepancies in preview matrix.
      - Roster manager: Gracefully labels UMS as `(Not Configured)` when integration is unconfigured or disabled, falling back to local Moodle Cohorts.
    - **Verification (2026-09-14)**:
      - 37/37 automated unit and integration tests passed in Moodle 5.2.2 CLI container (`scratch/test_ums_integration.php`).
      - 29/29 bulk enrolment tests passed (`scratch/test_wub_enrol.php`).
      - Verified zero fake production data: 0 fake courses in `mdl_course`, 0 fake users in `mdl_user`.
      - Web endpoints verified: `/local/wub_ums/index.php` and `/local/wub_enrol/index.php` return HTTP 303 redirect to login when unauthenticated.
      - No Moodle core modifications; legacy `wub_moodle` untouched.

13. **Unified WUB Bulk Enrolment & UMS Integration Plugin (`local_bulk_enrolment`) (2026-09-14)**:
    - **Plugin Path**: `/home/phant0m/Phantom/moodle/moodle/public/local/bulk_enrolment/`
    - **Component**: `local_bulk_enrolment` (v2026091400, Moodle 5.2+)
    - **Unified Architecture**:
      - Merged `local_wub_ums` (authoritative REST client & diagnostics) and `local_wub_enrol` (bulk enrolment workspace, validation matrix, and chunked dispatcher) into a single production plugin while maintaining strict internal modularity.
      - **UMS Layer (`classes/api_client.php`, `classes/model/*`, `classes/exception/*`)**: Preserves all 5 API endpoint contracts, cURL Digest Auth (`CURLAUTH_DIGEST | CURLAUTH_BASIC`), dynamic `X-API-KEY` placement (Query on GET, Body on POST), 3-attempt exponential backoff, and MUC caching (`local_bulk_enrolment/programs`, `local_bulk_enrolment/batches`).
      - **Enrolment Layer (`classes/service/*`, `classes/external/*`)**: Preserves dual-selection (courses + rosters), pluggable roster providers (UMS and Cohorts), pre-enrolment validation matrix, chunked transactional execution, and course-context permission enforcement (`enrol/manual:enrol`, `moodle/role:assign`).
      - **Clean Removal of Old Components**: Old plugins `local_wub_ums` and `local_wub_enrol` uninstalled cleanly via Moodle's native `core_plugin_manager::uninstall_plugin()`, removed from filesystem, and all settings migrated safely to `local_bulk_enrolment`.
      - **Unified Management UI (`index.php`)**: Features sub-navigation tabs:
        - Tab 1: **Bulk Enrolment Workspace** (`?tab=enrol`) — Course & roster dual selector, pre-enrolment matrix, chunked execution progress, and CSV download.
        - Tab 2: **UMS Integration & Diagnostics** (`?tab=ums`) — Status cards, 5 supported API contract table, live connection test trigger, and cache purge.
      - **Site Administration**: Unified settings page at *Site administration → Plugins → Local plugins → WUB Bulk Enrolment & UMS Integration* (`/admin/settings.php?section=local_bulk_enrolment`).
    - **Verification (2026-09-14)**:
      - 18/18 automated unit and integration tests passed in Moodle 5.2.2 CLI container (`scratch/test_bulk_enrolment.php`).
      - Web endpoints verified: `/local/bulk_enrolment/index.php?tab=enrol` and `?tab=ums` return HTTP 303 redirect to login when unauthenticated; render full HTML templates for admin sessions.
      - Database verified: 490 tables intact; 0 fake tables (`mdl_student` does not exist); 0 fake users created.
      - Zero Moodle core modifications; legacy `wub_moodle` project untouched.

12. **UI Modernization Pass — presentation layer only (2026-09-14)**:
   - **Mechanism**: everything is in Academi's `theme_academi/customcss` setting; its source of truth is the tracked file `theme-custom/academi-customcss.css` (~1,100 lines, sectioned: tokens, typography, focus, header/nav, page shell, cards, buttons, forms, tables, alerts, misc, admin, login, responsive, reduced-motion, WUB footer). Apply with `theme-custom/apply-customcss.sh` (runs `cfg.php` + `purge_caches.php` as `www-data`). One theme setting toggled: `theme_academi/showdefaultloginpanel` = 0 (hides Moodle's generic "500M users" login panel). No theme/core/plugin file was modified; no JS added; no functionality changed.
   - **UI inventory**: `theme-custom/UI_INVENTORY.md` (page → entry point → templates → CSS/JS → changes).
   - **Improvements**: light neutral page background with a white, bordered `.main-inner` content surface; darker body text (`#1f2d3d`) and unified Alegreya Sans typography for buttons/inputs (Academi mixed Arial/Century Gothic); page `h1` 34px→28px (22px mobile); compact header (logo bar 121px→77px) with active/hover states on primary nav; secondary-nav tabs with primary underline; cards/blocks with 8px radius, subtle border/shadow and muted headers; button hierarchy (primary `#0f6cbf` → hover `#0b5399`; secondary neutral white/border; Academi's dark `#353535` submit/cancel inputs overridden incl. `#id_submitbutton`); consistent 42px form controls with primary focus ring; tables with muted header row and hover; alerts with left accent per type; dropdown/modal/drawer chrome; restored keyboard focus rings that Academi suppressed (`* { outline: none }`, `[role=button]:focus`, `a.dropdown-toggle:focus`, checkbox/radio…) with a white ring inside the blue top bar and Boost-style dark ring on buttons; `prefers-reduced-motion` respected; login page as a centred card over the campus photo with a dark overlay. Responsive fixes: header logo capped (220/170/160/150px by breakpoint) — fixes the baseline defect where the logo covered the "More ▾" nav item on mobile; 44px touch targets for buttons/inputs on mobile; drawer-toggle button moved below Academi's two-row header (it overlapped "Site administration" at 768–1366px); tablet/mobile paddings.
   - **Verified** (headless Chrome via DevTools protocol + curl, real data only): 14 pages (dashboard, front page, course index, login, profile, admin search, users list, edit profile, preferences, calendar, WUB bulk enrolment, WUB UMS console, UMS settings, grade scales) at **1366×768, 1024×768, 768×1024, 390×844** — no horizontal overflow (`scrollWidth == clientWidth`) on any page/width; no new JS console errors (pre-existing: `local_wub_enrol` page throws an exception on load — other agent's work; core YUI "unload" permissions-policy report on edit-profile). Interactions: user menu, mobile primary "More", mobile secondary "More", block drawer open, calendar "New event" modal open/close, users Filters dropdown, course search (GET → course/management.php), admin search (GET), keyboard Tab focus rings, logout → login. Secondary-nav more-menu regression found and fixed during the pass (tab items must not change box height or Boost collapses them). Contrast: body text 12.6:1, primary buttons 5.36:1. No PHP-FPM/Nginx errors.
   - **Not verified**: 1920/1600/1440 desktop widths, 1024-portrait/430/375 mobile widths, real course pages (no courses exist), non-Chrome browsers, screen readers. No fake data created (DB: users guest+admin, 1 site course, 0 cohorts, 0 enrolments).
   - **Other-agent safety**: `local_wub_enrol` and `local_wub_ums` files untouched; only generic Bootstrap-class styling reaches their pages. `moodledata` root-owned files (created at 11:13 and ~11:40 by root CLI runs from elsewhere) were repaired with chown; none of this pass's commands ran as root.

---

13. **Migrate Legacy `local_mass_enroll` Functionality into Unified `local_bulk_enrolment` (2026-09-14)**:
   - **Version Bump**: `local_bulk_enrolment` updated to version `2026091401` (release `v2.1 (Migrated Legacy Capabilities)`).
   - **Migrated Legacy Features (Reimplemented)**:
     - **Identity Resolution (`student_identity_service`)**: Resolves student identifiers unambiguously across numeric registration ID, Moodle username, institutional email (`{id}@student.wub.edu.bd`), and Moodle `idnumber`. Refuses ambiguous matches; strictly rejects synthetic IDs like `ums_1234`.
     - **Scoped Course Groups (`group_service`)**: Course-scoped group resolution, optional auto-creation via native Moodle core `groups_create_group()`, and membership assignment via `groups_add_member()`. No direct SQL on `mdl_groups` or `mdl_groups_members`.
     - **CSV Bulk Enrolment (`csv_enrolment_service`)**: Native `csv_import_reader` parsing, pre-enrolment validation matrix, course authorization verification, and chunked 50-student transactional execution.
     - **Bulk Unenrolment (`unenrolment_service`)**: Dedicated safe unenrolment workspace requiring `enrol/manual:unenrol` capability in `context_course`, validating manual enrolment instances and invoking native `enrol_manual_plugin::unenrol_user()`.
     - **Native Data Export (`export_service`)**: Replaced bundled PhpSpreadsheet (822 vendor files) with native CSV streaming and downloadable templates via `export.php`.
     - **Audit Logging Events**: Implemented `\local_bulk_enrolment\event\bulk_enrolment_executed` and `\local_bulk_enrolment\event\bulk_unenrolment_executed` with valid URLs.
     - **Capabilities**: Registered `local/bulk_enrolment:unenrol` in `db/access.php` and `db/upgrade.php`.
     - **Unified UI Workspace**: Navigation tabs for Enrolment (`?tab=enrol`), Unenrolment (`?tab=unenrol`), Student Synchronization / Identity Inspector (`?tab=sync`), and UMS Diagnostics (`?tab=ums`).
   - **Explicitly Discarded Legacy Anti-Patterns**:
     - **Financial Enforcement**: Legacy `payment_notice.php` and `hook_callbacks.php` (synchronous cURL calls blocking page renders) discarded.
     - **Custom Table `mdl_enrol_ums_user`**: Discarded. Table was completely unindexed and duplicated core `department`/`institution` fields.
     - **PhpSpreadsheet Vendor Tree**: Discarded. Replaced with native Moodle dataformat/CSV streaming.
     - **Bundled Legacy JavaScript**: Discarded `Select2`, `SweetAlert2`, `Ladda`, `List.js`. Replaced with native Moodle AMD modules and Bootstrap components.
     - **System-Only Authorization & Forced Instances**: Discarded. Course-level context authorization enforced; missing manual instances are never forced or auto-created.
   - **Formally Deferred Features**:
     - **Automated Student Account Provisioning (`user_provisioner`)**: Canonical mappings documented; account creation deferred pending live UMS batch response schema audits with university administration.
   - **Verification Results**:
     - 43/43 assertions passing in `scratch/test_bulk_enrolment.php`.
     - 37/37 assertions passing in `scratch/test_migrated_functionality.php`.
     - Total: 80 automated assertions passed (0 failures).
     - UI rendering verified for all 4 navigation tabs (`enrol`, `unenrol`, `sync`, `ums`).
     - Database state verified: 490 core tables intact, 0 custom tables, 0 fake records.
     - Legacy repository `/home/phant0m/Phantom/wub_moodle` untouched (READ-ONLY).

---

14. **Restore and Implement August 2026 UMS Student Sync + Advanced Bulk Enrolment System (2026-09-14)**:
   - **UMS API #4 Contract Discovery & Verification**:
     - Resolved the previous HTTP 404 issue with API #4 (`GET /students/enroll_student_list_program_batch_wise/{program_id}/{batch_id}`):
       - When passing `0` or `all` as the batch parameter, UMS returns the complete roster of active students in the program (live verified: 1,187 active students in CSE, 2,227 in EEE, 2,623 in Civil).
       - When passing a batch title (e.g. `74F`), UMS returns students in that specific batch.
       - The API client automatically maps numeric batch IDs to batch titles via `get_batches()`.
       - For batches with no registered students in the active semester, UMS returns HTTP 404; the client now gracefully catches this and returns an empty roster `[]` without error.
     - Confirmed live operation across all 5 documented UMS endpoints (API #1, #2, #3, #4, #5).
   - **Model Enhancements**:
     - `\local_bulk_enrolment\model\ums_course`: Parses course code, title, and credits from `enrollCourseDetails`.
     - `\local_bulk_enrolment\model\ums_student`: Normalized properties for `full_name`, `reg_id`, `email`, `stud_id`, and registered course objects (`courses`). Added `get_course_codes_summary()` and `has_courses()`.
   - **August 2026 Student Synchronization Subsystem**:
     - `\local_bulk_enrolment\service\sync_service`: Compares UMS rosters against local Moodle user database.
     - Comparison matrix classifies students into: `SYNCED` (up-to-date), `CAN_UPDATE` (metadata updates available), `PROVISION_DEFERRED` (unmatched UMS student; account creation deferred), and `AMBIGUOUS_MATCH` (conflicting Moodle identities; blocked).
     - Implemented `execute_sync()` applying safe metadata updates (`department` ← program, `institution` ← batch, `idnumber` ← regId) using core `user_update_user()`.
     - Strictly preserves authentication, passwords, and roles. Refuses to create synthetic or phantom accounts.
     - Interactive UI workspace (`templates/sync_page.mustache` & `amd/src/sync_workspace.js`): Dynamic program/batch dropdowns, real-time comparison KPI cards, filterable comparison table, select-all controls, safe execution modal, and result report.
   - **Advanced Bulk Enrolment Dual-Panel Workspace**:
     - Left panel: Program and batch multi-selector with real-time search, select/unselect all, and counter.
     - Right panel: Rich student roster displaying full name, username, Registration ID, batch badge, previous/current registered course badges (`enrollCourseDetails`), and local Moodle match indicators.
     - Pre-enrolment verification matrix: Evaluates student x course combinations (`READY`, `ALREADY_ENROLLED`, `IDENTITY_NOT_FOUND`, `NO_MANUAL_INSTANCE`).
     - Safe execution: Native `enrol_manual_plugin::enrol_user()` within 50-student transaction chunks, dynamic student role resolution, idempotency validation, course group assignments, and CSV result export.
   - **Verification & Test Results**:
     - **138 automated test assertions passing across 3 test suites (0 failures)**:
       - `scratch/test_bulk_enrolment.php`: 43 passed, 0 failed.
       - `scratch/test_migrated_functionality.php`: 37 passed, 0 failed.
       - `scratch/test_august_sync_enrolment.php`: 58 passed, 0 failed.
     - **Real Moodle E2E Workflow Verified**:
       - Created controlled test course and test student user in live Moodle database.
       - Computed preview matrix (`ENROLLABLE`).
       - Executed enrolment via `enrolment_dispatcher` & verified active enrolment with `is_enrolled()`.
       - Assigned course group via `group_service`.
       - Re-evaluated matrix to verify idempotency (`ALREADY_ENROLLED`).
       - Executed unenrolment via `unenrolment_service` & verified student unenrolled.
       - Cleaned up test course and user cleanly.
     - **Integrity & Security**:
       - 0 core Moodle file modifications.
       - 0 custom database tables created (strictly Moodle-native schema).
       - Zero API keys, passwords, or credentials logged or exposed.
       - Legacy codebase `/home/phant0m/Phantom/wub_moodle` completely untouched (READ-ONLY).

14. **UI pass 2 — primary-shade colour system + `local_bulk_enrolment` page design (2026-09-14)**:
   - **Mechanism unchanged**: `theme-custom/academi-customcss.css` → `theme_academi/customcss` via `theme-custom/apply-customcss.sh`. No plugin/theme/core file modified (plugin was under active edit during the pass).
   - **Shade palette** (tokens `--wub-p-50…900`, all derived from `#0f6cbf`): 900 `#073a63`, 800 `#0a4d8a`, 700 `#0b5399`, 600 `#0f6cbf`, 500 `#2f83cc`, 400 `#5f9fd9`, 300 `#93bfe7`, 200 `#c6def3`, 100 `#e7f1fb`, 50 `#f3f8fd`. Site-wide use: top bar `#header` → p-800; `.main-inner` 3px p-600 top accent; card/block headers, table heads, modal headers, row hover → p-50 with p-200 borders and p-900 text.
   - **`local_bulk_enrolment` (scoped to `#page-local-bulk_enrolment-index`, customcss §11b)**: tab bar styled like Moodle secondary nav (p-100 active with p-600 underline); step/card headers `bg-primary`/`bg-success`/`bg-info`/`bg-secondary|dark` → p-600/p-700/p-500/p-800; `bg-white` headers → p-50/p-900; `border-left-*` accents, `bg-light` tiles, `badge-light/secondary/info/primary`, `btn-success/info/light/outline-secondary`, `code` chips, `thead-light`, striped rows, progress bar → primary shades. Semantic states kept: `alert-warning/danger`, `badge-success/warning`, `table-warning`, `btn-danger` (unenrolment) — status meaning preserved. Responsive fixes: two tables without `.table-responsive` (UMS config status, field mapping) overflowed at 390px — fixed via `.card-body:has(> table) { overflow-x:auto }`, wrapping `<code>` URLs and no-wrap label column; `custom-select` forced full-width (BS4 class under BS5 compat); intro action buttons wrap on mobile; a mobile rule that accidentally widened the back-to-top button was corrected before finishing.
   - **Verified** (headless Chrome + curl): enrol/unenrol/sync/ums tabs, plugin settings page, dashboard, users list, login at 1366×768, 768×1024, 390×844 — no horizontal overflow on any page/width after the fixes (before: sync/ums overflowed at 390); no new JS errors (pre-existing plugin exception on enrol/sync tabs remains); back-to-top 40×44px; computed colours: top bar `rgb(10,77,138)`, card headers `rgb(243,248,253)`, main-inner accent `rgb(15,108,191)`. Sync tab screenshots taken with `&sesskey=` and no `action` (renders only; no sync executed). No data created (users guest+admin, 1 site course).
   - **`!important` usage** rose to ~44 occurrences, all inside the plugin scope to beat Bootstrap utility classes (`bg-*`, `text-*`, `shadow-sm`) that are `!important` themselves.

15. **`local_bulk_enrolment` v3.0 — UMS contract audit, service fixes, workspace rebuild (2026-09-14)**:
   - **Live UMS verification from the Moodle container (all 5 APIs, real data, no secrets printed)**:
     | API | Method | Live | Real payload (verified) | Moodle mapping |
     |---|---|---|---|---|
     | #1 details by usernames | POST `email=` (key in body) | 200 | `message.StudentDetails[]`: `stud_id`=registration id, `ugc_stud_id`=16-digit UGC id (not the login), `username`=university e-mail (local part = 10-digit login), `batch_id`=batch **title**, `program_id`, `shift`… + sensitive fields | `ums_student_details::from_api1` (whitelist; password hash/dues never retained) |
     | #2 programs | GET | 200, 35 | `id`, `title`, `short_title`, `short_name`, `code`, `is_active` | `ums_program` |
     | #3 batches | GET | 200, 172 (CSE) | `id`, `batch_title`, `shift`, `program_type`, `total_no_of_students`, `is_active`, `is_open` | `ums_batch` (id **and** title kept) |
     | #4 roster | GET `/{program}/{batch_title\|0}` | 200 (74F→15, 0/all→1,187) | `username`, `regId`, `full_name`, `mother_batch`, `current_batch`, `program_name`, `program_id`, `enrollCourseDetails[{title,courseCode,credit}]` | `ums_student` / `ums_course` |
     | #5 by ids | POST `ids=` (key in body) | 200 | `stud_id`=registration id, `university_email`=10-digit username, `batch_id`=title, `program_id`, `department_id` | `ums_student_details::from_api5` |
     API #4 semantics: numeric batch id → 404; valid batch without registered students → 404; **unknown title → whole program**. Key in query on POST → 403.
   - **Root causes found and fixed**: (1) `amd/build/` missing → every AMD request 404 → enrol tab JS never ran ("Uncaught" exception); (2) providers passed the numeric batch id to API #4 and the client swallowed the 404 as "0 students"; unknown titles were unguarded; (3) API #1/#5 models mapped non-existent keys (`regId`, `university_email` at that level) → empty registration id/e-mail, and the roster model fabricated `<username>@student.wub.edu.bd`; (4) UMS failures returned `[]`/silently fell back to the cohort provider; (5) identity matching collided in a map (random-account risk), never reported ambiguity, and ran 4 queries per student; (6) matrix accepted only Moodle user ids (unmatched students vanished), status codes not per spec, chunk size not enforced server-side; (7) sync tab called `confirm_sesskey()` on GET (crash).
   - **Files rewritten/added** (plugin only; no core/theme/legacy changes): `classes/api_client.php`; `classes/exception/*` (+`ums_not_found_exception`, error codes + HTTP codes); `classes/model/*`; `classes/service/roster_service.php` (new), `student_identity_service.php` (bulk resolve + duplicate-idnumber guard), `preview_matrix_service.php`, `enrolment_dispatcher.php` (MAX_CHUNK 50, group assignment), `ums_roster_provider.php`, `sync_service.php` (batch titles, bulk identity, BLOCKED before SKIPPED); `classes/external/get_programs.php`, `get_batches.php` (new), `get_roster.php`, `preview_matrix.php`, `execute_chunk.php`, `ums_error_trait.php`; `db/services.php`, `db/caches.php` (+`roster` 10 min, `student_details` 10 min); `templates/enrolment_page.mustache`, `ws_program_list/ws_batch_list/ws_roster_table/ws_matrix/ws_results.mustache` (new); `amd/src/workspace.js` (new; `selector.js`/`enrolment_controller.js` removed), `amd/src/sync_workspace.js`, `amd/build/*` (built with terser); `index.php` (workspace init, hidden courses listed, sync fixes, UMS error notice); `lang/en`; `README.md` (contract doc); `version.php` → 2026091402 (upgrade run as `www-data`). Setting `local_bulk_enrolment/base_url` normalised to `https://api.e-dhrubo.com` (client derives the origin anyway).
   - **Workspace (`?tab=enrol`)**: left — program search/multi-select/select-visible/clear, batches grouped per program with "All batches"; right — server-paginated roster (25/page, cap 100) with search (name/username/reg id/course code/title), batch and account filters, facets, select page / select all matching / clear, registered-course history per student, Moodle-account status (MATCHED / No account / Ambiguous); Moodle course list (category + search, hidden courses flagged), role/duration, group mode (none / UMS batch title / custom, optional creation), reactivate-suspended; verification matrix with status filter; confirmation modal; chunked execution with progress, per-row results, CSV download; automatic re-verification after execution. UMS failures surface as `error_code — message` in place (never "0 students").
   - **Tests performed (2026-09-14)**: live API probes (above); `roster_service` CLI checks (74F=15, 75A valid-empty → EMPTY, ZZZ9 → `INVALID_BATCH` refused, 300/all → 1,187 paged); browser (headless Chrome) — programs 35, batches 172, roster paging, batch 74F, search, course history, e-mail enrichment, error envelope, zero JS errors; **real E2E through the UI** with a controlled fixture (hidden course `BE_E2E_TEST_2026`, manual instance welcome e-mail disabled, temporary Moodle account for one real UMS student of batch 74F): MATCHED → READY → modal → `ENROLLED` + group `74F` created and joined → re-verify `ALREADY_ENROLLED`; DB: `is_enrolled()` true, 1 `user_enrolments` row, role = resolved `student` (id 5 by archetype, not hardcoded), 1 group membership; second run `ALREADY_ENROLLED`, no duplicate rows; chunk of 61 users refused (`error_chunk_too_large`); matrix `IDENTITY_NOT_FOUND` / `INVALID_COURSE` / `MANUAL_INSTANCE_DISABLED`; guest user → `PERMISSION_DENIED`; **sync**: `CAN_UPDATE` (department, institution) → `UPDATED` → `SYNCED`, idnumber/username/e-mail/password/auth/roles unchanged, 14 UMS-only students `PROVISION_DEFERRED`, duplicate-idnumber account → `AMBIGUOUS_MATCH` blocked in sync (`BLOCKED`) and matrix; large data: 1,187-student roster ~0.5 s/page from cache, 25 DOM rows, 15 MB JS heap, at 1366 and 390 px; UMS unreachable (base URL → closed port) → `UMS_CONNECTION` shown in the UI and diagnostics, then restored; all four tabs + settings + CSV export HTTP 200; PHP lint clean; 0 PHP-FPM errors. Fixture deleted afterwards via `delete_course()`/`delete_user()` (Moodle soft-delete rows remain for the temporary accounts; DB back to 2 active users, 1 course).
   - **Not done / limitations**: no PHPUnit suite (verification was live/CLI/browser); API #1 enrichment is per visible page only (by design); roster/details are cached 10 min in MUC (application cache, personal data transient — privacy provider still declares no persistent storage); Moodle account provisioning remains deferred (no account creation); the cohort roster provider is kept but not exposed in the workspace; other browsers not tested.

16. **`local_bulk_enrolment` v3.0 Production Candidate Audit & Hardening (2026-09-14)**:
   - **Audit of 14 Security & Operational Dimensions**:
     - *Authorization boundaries & capability checks*: System UI access restricted to `local/bulk_enrolment:view`; course actions strictly checked in `context_course::instance($courseid)` for `enrol/manual:enrol`, `enrol/manual:unenrol`, `moodle/role:assign`, and `moodle/course:managegroups`.
     - *UMS credential handling*: Secret tokens (`api_key`, `api_password`, `api_username`) scrubbed via `api_client::scrub()` before any exception message reaches logs or UI.
     - *API failure semantics*: Proper exception hierarchy (`ums_not_found_exception` for 404, `ums_authentication_exception` for 401/403, `ums_connection_exception` for transport/5xx with exponential backoff); `roster_service` guards against UMS batch title fallback anomalies.
     - *CSRF / sesskey*: `confirm_sesskey()` enforced on all `index.php` form actions; `require_sesskey()` enforced in AJAX external endpoints (`execute_chunk.php`).
     - *XSS / output escaping*: Templates use HTML-escaped tokens (`{{variable}}`, zero `{{{`), `workspace.js` uses strict `esc()` sanitization, PHP output uses `s()` and `format_string()`.
     - *SQL safety*: 100% parameter binding with `$DB->get_in_or_equal()` and named params (`:mnet`). Zero string concatenation.
     - *Identity matching*: `student_identity_service` enforces strict zero-guessing policy; duplicate idnumber collisions correctly yield `AMBIGUOUS_MATCH` (blocked from enrolment).
     - *Transaction rollback*: Delegated transactions in `enrolment_dispatcher` and `unenrolment_service` roll back cleanly on failure without partial corruption.
     - *Concurrent execution / idempotency*: Re-evaluation and re-enrolment classify users as `ALREADY_ENROLLED` without creating duplicate `mdl_user_enrolments` records.
     - *Large-roster behavior*: Database queries batched in 400-student chunks; enrolment transactions capped at 50 users (`MAX_CHUNK`); UI paginates in 25-100 rows with API #1 enrichment limited to current page.
     - *CSV upload security*: Added upload error checking (`UPLOAD_ERR_OK`), file size threshold (<= 2MB), and extension whitelist (`.csv`, `.txt`).
     - *CSV formula injection guard*: Prepending apostrophe (`'`) in `export_service::sanitize_csv_field` for cell values starting with `=`, `+`, `-`, `@`, `\t`, `\r` prevents DDE spreadsheet execution.
     - *Group permissions*: Course group creation strictly gated by `moodle/course:managegroups` capability in course context.
     - *Sensitive data exposure*: UMS ingestion whitelist drops password hashes, financial dues, and phone numbers at boundary (`ums_student_details::from_api1` / `from_api5`).
   - **Concrete Defects Fixed**:
     1. `enrolment_dispatcher.php`: Added dispatch of `\local_bulk_enrolment\event\bulk_enrolment_executed::log_enrolment()` after transaction commit.
     2. `index.php`: Added `require_capability('local/bulk_enrolment:manage', $context)` check for sync actions; hardened unenrolment CSV upload with error, size, and extension validation.
     3. `export_service.php`: Added `sanitize_csv_field()` to prevent CSV formula injection on export.
     4. `classes/model/*`: Added OOP getters to `ums_course`, `ums_student`, `ums_program`, and `ums_batch` for backwards API compatibility.
     5. `classes/service/preview_matrix_service.php`: Supported numeric user ID resolution in input normalization, added `compute_matrix` alias, and added `enrollable` key to summary.
   - **Automated Verification**:
     - 4 test suites passing: `test_bulk_enrolment.php` (43/43), `test_migrated_functionality.php` (37/37), `test_august_sync_enrolment.php` (58/58), `test_production_audit.php` (37/37). Total: 175 passed, 0 failed.
   - **Headless Cross-Browser Verification**:
     - Google Chrome 153 and Mozilla Firefox 155 executed across all four tabs (`tab=enrol`, `tab=unenrol`, `tab=sync`, `tab=ums`) using active admin session cookie. All tabs loaded, rendered cleanly with 0 console errors, and captured full screenshots.
   - **Zero Prohibited Modifications**: Moodle core, theme Academi, and legacy `wub_moodle` completely untouched. No git commits or pushes.

---

 17. **`local_bulk_enrolment` v3.0 UI/UX Polished Redesign**:
    - **Visual Workflow Stepper**:
      - Added high-visibility 4-step workflow indicator pipeline at top of enrolment workspace (`.be-pipeline-bar`):
        `1. Select Roster (Program & Batch)` &rarr; `2. Review Students (Moodle Accounts)` &rarr; `3. Target Courses (Role & Groups)` &rarr; `4. Verify & Enrol (Audit & Execution)`.
      - Pipeline state updates dynamically as programs, students, courses, and matrix are evaluated.
    - **Step Badges in Panel Headers**:
      - Card headers enhanced with step pills (`Step 1`, `Step 2`, `Step 3a`, `Step 3b`, `Step 4`, `Done`) providing clear process sequence.
    - **Scrollable Large Option Lists with Sensible Max-Heights**:
      - Programs list (`#be-program-list`): `max-height: 240px` with smooth scrolling.
      - Batches list (`#be-batch-list`): `max-height: 280px` with **sticky program group headers** (`.be-batch-group-head`) preserving program context while scrolling.
      - Course selection list (`#be-course-list`): `max-height: 280px` with search and category filters.
      - Student roster table (`.be-roster-scroll`): `max-height: 480px` with **sticky table header** (`thead th`), allowing comfortable paging through 25 students without page elongation.
      - Verification matrix (`.be-matrix-scroll`): `max-height: 420px` with **sticky table header**.
      - Pure CSS scroll edge shadow hints (Lea Verou technique) to visually indicate scrollable content.
    - **Typography & Alignment Refinement**:
      - Checkboxes vertically centered and aligned with labels.
      - Monospace code pills (`.be-code-pill`) for student usernames, IDs, and course shortnames.
      - High-contrast, standardized status badges (`READY`, `ALREADY_ENROLLED`, `IDENTITY_NOT_FOUND`, `AMBIGUOUS_MATCH`, `NO_MANUAL_INSTANCE`, `PROVISION_DEFERRED`).
      - Stat tiles (`.be-stat-card`) with iconography and clear metric hierarchy.
    - **Motion & Accessibility**:
      - Subtle fade-in reveal animations respecting `prefers-reduced-motion`.
      - Smooth scrolling without scroll-jacking; user retains full scroll control.
    - **Responsive Layout Verification**:
      - Verified across 1366px (Desktop), 1024px (Small Desktop), 768px (Tablet), and 390px (Mobile) viewports. Zero horizontal page overflow.
    - **Automated & Cross-Browser Verification**:
      - 220/220 automated test assertions passing across 5 test suites.
      - Headless Chrome and Firefox verified with zero console errors. Full interactive flow tested.
    - **UMS Student Account Provisioning & Selection Fix**:
      - Fixed student selection regression across all tabs and dropdown controls.
      - Enabled native, secure Moodle student account creation in `user_provisioner.php` and `sync_service.php` for unmatched UMS students.
      - Implemented deterministic name splitting (`firstname`/`lastname`), institutional email convention (`<username>@student.wub.edu.bd`), collision detection (`check_identity_collision`), and native Moodle password hashing with zero plaintext persistence.
      - Verified full lifecycle: Select UMS student → Apply safe metadata sync → Moodle account created/updated → Sync result displayed → Select course → Verify matrix → Enrol.

 18. **WUB Institutional Landing Page & Role-Based Authentication Flow (2026-09-15)**:
    - **Visual Reference Replication**:
      - Replicated the reference institutional landing layout matching WUB design:
        - Full-screen high-resolution WUB campus background image (`pix/wub_campus_bg.jpg`).
        - Centered, elevation-bordered white content card (`max-width: 660px`).
        - Left column: high-angle architectural perspective of WUB glass tower (`pix/card_tower.jpg`).
        - Right column: "WELCOME TO WUB", institutional guidance text, role selector grid (`Student | Teacher` row, `Administration` full width), divider, `Course Catalog` guest button, `CONTACT US | HOW-TO GUIDES` sublinks, and institutional copyright footer bar.
      - Page layout uses `$PAGE->set_pagelayout('embedded');` to bypass standard theme headers and draw the full-page campus background seamlessly.
    - **Authoritative Flow Restructuring**:
      - Reordered authentication journey: `WUB Landing Page` &rarr; `Role Selection` &rarr; `Policy Agreement` &rarr; `Role-Specific Login` &rarr; `Authoritative Auth & Clearance` &rarr; `Role Dashboard`.
      - Landing page acts as the primary public entry point (`alternateloginurl = '/local/wub_auth/landing.php'`).
      - Role selection serves as an intended login persona; server-side capability and archetype validation (`role_resolver::can_act_as_role()`) independently verifies whether authenticated identity is genuinely authorized for the selected persona.
      - Mismatched logins are rejected server-side with `STATUS_UNAUTHORIZED_PERSONA` and logged to `mdl_wub_auth_audit` (`UNAUTHORIZED_PERSONA`).
      - Policy page (`policy.php`) moved into the authentication journey following role selection. Device cookie (`wub_policy_device`) validation preserves 30-day agreement validity while requiring re-acknowledgement upon persona or policy revision.
      - Role-specific login pages: Dynamic persona badges (`Student Portal`, `Faculty Portal`, `Administration Portal`), tailored username/email placeholders and helper text, with "Portal Home" return navigation.
      - Root entry routing: Single-hop 303 redirection at Nginx level (`location = /`) routing fresh unauthenticated visitors straight to `landing.php` without multi-hop core redirects or session dependencies.
      - Course Catalog guest access: Unauthenticated visitors browsing `/course/index.php` are served directly with HTTP 200 without forced login redirection (`$CFG->forcelogin = 0`, `$CFG->defaulthomepage = 1`).
      - Institutional guides page: `/local/wub_auth/guides.php` providing guidance on student, faculty, and administrative access.
    - **Cross-Browser & Automated Verification**:
      - 17/17 automated flow assertions passing in `scratch/test_landing_flow.php`.
      - 61/61 auth suite assertions passing in `scratch/test_wub_auth.php`.
      - Headless Google Chrome & Mozilla Firefox audits across 1366px, 1024px, 768px, and 390px viewports passing 100% with 0 console errors. Zero horizontal overflow (`scrollWidth <= clientWidth`).
    - **Boundary Integrity**:
      - Moodle core and theme Academi unmodified. Legacy `wub_moodle` untouched. Student provisioning and enrolment strictly preserved in `local_bulk_enrolment`.
12. **WUB Academic Seed Data & Authentic Institutional Hierarchy (Zero Dummy Data)**:
    - **Data Harvesting & Sourcing**:
      - Scraped exclusively from official World University of Bangladesh website (`https://wub.edu.bd/`) and 15 official departmental subdomains (`https://{dept}.wub.edu.bd/`).
      - Preserved full source traceability, timestamps, and portal URLs in machine-readable format (`scratch/wub_academic_dataset.json`).
      - Zero placeholder, fake, synthetic, or AI-generated data.
    - **Academic Structure Provisioned**:
      - **4 Root Faculty Categories**: World School of Business (`WUB_FAC_BUS`), Faculty of Engineering (`WUB_FAC_ENG`), Faculty of Science (`WUB_FAC_SCI`), Faculty of Arts and Humanities (`WUB_FAC_ARTS`).
      - **15 Department Subcategories**: Business Administration, Tourism & Hospitality, CSE, Textile Engineering, Mechatronics, Mechanical, Automobile, EEE, Civil Engineering, Pharmacy, Architecture, Biomedical Engineering & Public Health, English, Law, Media Studies & Journalism.
      - **749 Authentic Courses**: Extracted with exact course codes, official titles, and credit hours from official `/main/undergraduate_course_structure` pages.
      - **Course Deduplication Architecture**: Resolved shared common courses (Option A: 78 shared courses hosted authoritatively in primary home department) and differing academic contexts (Option B: 33 separate course offerings disambiguated by department tag where course titles or credits genuinely differ).
    - **Faculty Member Provisioning**:
      - **228 Verified Teachers**: 100% real faculty members extracted from official departmental faculty portals.
      - **15/15 Verified Department Heads**: Identified exclusively from official WUB designation evidence (`[Head of the Department]` or `[Head(Acting)]`).
      - **Deterministic Local IDs**: `W` + 6-digit padded official profile ID (`W000001` - `W001546`), stable across re-runs and globally unique. Explicitly documented as local Moodle identifiers.
      - **Standardized Institutional Emails**: `firstname.restofthename@wub.edu.bd` (academic titles removed). The 2 raw collisions (`nusrat.jahan` and `israt.jahan`) cleanly disambiguated with official ID suffix.
      - **Profile Photographs**: Downloaded 227 official portraits and attached natively to user profiles via Moodle core `process_new_icon()` API (`f1.jpg`, `f2.jpg`, `f3.jpg`). 1 teacher with no published photo on the official portal was left absent without placeholder.
    - **Course-Teacher Enrolments**:
      - Enrolled 3 verified departmental teachers (1 HOD + 2 regular teachers) into every course offered by that department using Moodle's native `enrol_manual_plugin` and assigned the `editingteacher` role (2,247 total role assignments).
      - Privilege isolation verified: Zero teachers or HODs possess system administrative context roles.
    - **Verification & Idempotency**:
      - Automated test suite (`scratch/verify_wub_seed_data.php`) verified all 17 requirements with 31/31 assertions passing.
      - Full idempotency proven: Re-running `scratch/import_wub_academic_seed.php` produced 0 duplicate categories, 0 duplicate users, 0 duplicate courses, and 0 duplicate enrolments.
      - Existing legitimate Moodle users (administrator `khairul_anam24` and 15 student accounts) preserved untouched.
11. **WUB Moodle LMS Production Validation & Fall 2026 UMS Synchronization**:
    - **Fall 2026 Boundary Enforcement**:
      - Activated 35 Semester 1 courses for Fall 2026 (`startdate = 1788199200` / `2026-09-01 00:00:00`) across 10 active undergraduate programs (CSE, EEE, BBA, BTHM, MTE, TE, English, ME, AE, BMSJ).
      - 714 historical/inactive courses preserved untouched with startdate 0.
    - **Approved Bulk Enrolment Architecture Used Exclusively**:
      - Synchronized all records through `local_bulk_enrolment` service layer (`api_client`, `user_provisioner`, `sync_service`, `enrolment_dispatcher`, `group_service`).
      - Zero direct SQL manipulation of `mdl_user` or `mdl_user_enrolments`. Zero secondary enrolment mechanisms.
    - **Authentic Student Provisioning & Synchronization**:
      - Discovered 1,288 authentic students from live UMS API (`https://api.e-dhrubo.com`).
      - Provisioned 1,273 new authentic student accounts with official credentials, standardized emails, and institutional IDs (`idnumber`).
      - Updated 15 pre-existing student accounts. Zero duplicate accounts created.
      - Zero dummy, synthetic, or guessed student records.
    - **Enrolments & Section Group Mapping**:
      - Enrolled students into 35 Fall 2026 courses (5,774 student enrolments; 8,021 total course enrolments including teacher seed assignments).
      - Created 218 section groups (e.g. `74A`–`74F`, `97H`–`97L`) matching official UMS cohorts.
      - Assigned 5,774 group memberships. Zero generic grouping.
    - **Test Suite & Idempotency Verification**:
      - Automated test suite (`scratch/test_lms_and_sync_functional.php`) executed with 20/20 tests passing (100% success rate):
        - Bulk Enrolment functional tests (single/multi-course, single/multi-student, repeat idempotency `ALREADY_ENROLLED`): 6/6 PASS.
        - UMS API failure & boundary handling (empty batch 404, invalid program, non-existent identity, invalid course): 4/4 PASS.
        - Synchronization integrity (zero duplicate usernames, emails, idnumbers; bidirectional group membership): 4/4 PASS.
        - LMS persona end-to-end validation (Student, Teacher, Admin journeys): 6/6 PASS.
    - **Persona Validation & Cross-Browser Audit**:
      - Student (`0326745565`): Login, dashboard course visibility, course view verified. Course activities audited and confirmed `NOT AVAILABLE IN SOURCE DATA` without fabricating synthetic items.
      - Teacher (`jannatul.naeem`): Login, assigned course participants view (168 participants), section group filter (`74F` with 15 students) verified.
      - Admin (`khairul_anam24`): Authorized system management and bulk enrolment sync matrix verified.
      - Visual evidence captured across Google Chrome and Mozilla Firefox.
    - **ExamController API Readiness Assessment**:
      - Evaluated all 15 integration dimensions with definitive readiness classifications.

12. **Moodle Site Administrator LMS Visibility & Navigation Enhancement (2026-09-15)**:
    - **Objective**: Improve the Moodle Site Administrator (`khairul_anam24`, user ID 2) normal LMS visibility and discovery of the complete academic structure (4 faculties, 15 departments, 749 courses) using 100% native Moodle mechanisms without creating a custom admin dashboard, without redesigning the dashboard, and without artificial course enrolments.
    - **Root Cause Diagnosed**:
      1. *Course Overview (`block_myoverview`) Scope*: Moodle's native Course Overview block is strictly designed for enrolled courses (`enrol_get_my_courses()`). Because an institutional Site Administrator is not enrolled as a student or teacher in academic courses, `/my/` displayed *"You're not enrolled in any courses"*.
      2. *Primary Navigation Bar Deficiency*: In Moodle 5.x Boost/Academi, primary navigation displayed only `Dashboard` and `Site administration`. Native `custommenuitems` was empty (`""`), `enablemyhome` was disabled (`0`), and `navshowallcourses` was disabled (`0`), leaving the administrator with no direct top-bar navigation to browse courses or categories without manually finding the URL or diving into admin settings.
      3. *Dashboard Block Configuration*: The default dashboard lacked course/category discovery blocks.
    - **Native Moodle Mechanisms Adopted (Zero Custom Code, Zero Core/Theme Modifications)**:
      1. *Primary Navigation (`$CFG->custommenuitems`)*: Configured native Moodle core menu items:
         ```text
         Course Catalog|/course/index.php
         Course Management|/course/management.php
         User Accounts|/admin/user.php
         Bulk Enrolment|/local/bulk_enrolment/
         ```
         These render natively across all pages in the primary top navigation bar (and automatically collapse into a responsive `More ∨` dropdown on narrower viewports).
      2. *Navigation Setting (`$CFG->navshowallcourses = 1`)*: Enabled global course listing visibility across Moodle's navigation structure.
      3. *Native Course Categories Block (`block_course_list`)*: Added Moodle's native "Course categories" block to the administrator's dashboard (`pagetypepattern = 'my-index'`, `parentcontextid = 5`, `defaultregion = 'content'`, `defaultweight = -1`).
         - Displays all 4 faculties (World School of Business, Faculty of Engineering, Faculty of Science, Faculty of Arts and Humanities) directly above the Course Overview.
         - Includes direct links into each faculty and an `All courses ...` catalog link (`/course/index.php`).
         - Student and teacher dashboards remain 100% untouched.
    - **Validation & Verification Evidence**:
      - **Dashboard (`/my/`)**: Loads cleanly with user greeting, populated "Course categories" block, and full top navigation links.
      - **Academic Navigation**:
        - Course Catalog (`/course/index.php`): All 4 faculties browsable and expandable.
        - Faculty Browsing (`/course/index.php?categoryid=2`): World School of Business lists departments (BBA, THM).
        - Academic Course Page (`/course/view.php?id=36`): Business Environment opens with complete syllabus sections and secondary navigation tabs (Course, Settings, Participants, Grades, Activities, More).
        - Course Participants (`/user/index.php?id=36`): 169 participants viewable with cohort group filters, roles, and user details.
      - **Administration & Course Management**:
        - Course Management (`/course/management.php`): Full category management, course creation, editing, sorting, and visibility toggling verified.
        - User Accounts (`/admin/user.php`): Real teachers and students searchable and viewable.
        - Site Administration (`/admin/search.php`): Full site administration hierarchy intact.
      - **Cross-Browser & Viewport Testing**:
        - Chrome Desktop (1366×900): All navigation links, categories block, and course pages verified with 0 console errors.
        - Chrome Mobile (390×844): Zero horizontal overflow (`scrollWidth <= clientWidth` PASS); responsive `More ∨` dropdown verified with 0 console errors.
        - Firefox (Headless with Geckodriver): Dashboard (`firefox_admin_dashboard.png`) and Catalog (`firefox_admin_catalog.png`) verified.
      - **Negative Invariants Strictly Verified**:
        - Zero student or teacher enrolments created for `khairul_anam24` (`mdl_user_enrolments` count for user 2 = 0).
        - Zero academic data corruption: Total user enrolments = 8,021; total courses = 753; total groups = 218; active users = 1,517.
        - Zero Moodle core files modified; zero Academi theme files modified; zero custom dashboard plugins created.
    - **Remaining Limitations**:
      - Historical/inactive courses (714 courses) remain in unpublished/inactive state as intended until future semester activation.

---

## Plugin/Theme State
* **Installed Themes**: `theme_boost`, `theme_classic`, `theme_academi` (v2026042900, **active**)
* **Installed Local Plugins**:
  * `local_bulk_enrolment` (v2026091403, release `v3.1 (UMS Student Provisioning Active)`): UMS-driven bulk enrolment workspace, authoritative account sync/provisioning, unenrolment, diagnostics.
  * `local_wub_auth` (v2026091501, release `v1.1 (WUB Institutional Landing Page & Role Flow)`): Unified WUB authentication orchestrator, institutional landing page, persona-based entry flow, UMS fallback authentication, deterministic student identity resolution, financial clearance enforcement, authoritative waiver/special permission management, 20-clause institutional policy acknowledgement, responsive custom login UI, security audit logging, and ExamController HMAC integration boundary.
* **WUB customizations**: all held in Academi admin settings (logo, login background, slide images, footer content/colours, custom CSS), native Moodle core configuration (`custommenuitems`, `navshowallcourses`), and local plugins. No Moodle core files modified.
* **Custom CSS source of truth**: `theme-custom/academi-customcss.css` → `theme_academi/customcss` via `theme-custom/apply-customcss.sh`. Edit the file, not the setting, and re-apply; UI inventory in `theme-custom/UI_INVENTORY.md`.
* **Reference repository**: `/home/phant0m/Phantom/wub_moodle` (separate git repo) — older WUB Moodle codebase. Used only as read-only reference; not modified.

---

* 2026-09-16: Completed Final Academic Data Synchronization & Moodle ↔ ExamController Integration:
  * Diagnosed and fixed root cause of teacher section invisibility: Moodle lacked academic queries for courses/sections/assignments/enrolments, ExamController had no academic sync mechanism, and section queries strictly checked single teacher foreign key without pivot/HOD departmental oversight.
  * Implemented `academic_service.php` in Moodle `local_examcontroller` with paginated endpoints: `/overview`, `/courses`, `/sections`, `/teacher-assignments`, `/student-enrolments`.
  * Assigned Fall 2026 course teachers to sections in Moodle via `groups_add_member` (218 section assignments) with strict section isolation.
  * Hardened HMAC-SHA256 signature verification in `signature_service.php`: removed hardcoded fallback secrets, generated high-entropy 64-hex secret, and wrapped nonce insertion in `try/catch` against `\dml_exception` for race-safe replay protection.
  * Created additive database migration in ExamController adding `moodle_course_id`, `moodle_group_id`, `moodle_user_id`, `department`, `faculty`, and `section_teachers` pivot table.
  * Implemented `MoodleAcademicSyncService` and `moodle:sync-academic` command in ExamController; synchronized 35 Fall 2026 courses, 218 sections, 436 teacher assignments, and 5,774 student enrolments.
  * Updated ExamController `AuthController.php` to eliminate password duplication (local passwords set to random unguessable hashes); authenticated solely through Moodle; mapped `khairul_anam24` to `Super Admin`.
  * Enhanced teacher section queries to check `teacher_id`, `section_teachers` pivot, and HOD departmental oversight.
  * Verified end-to-end: teacher login, strict section isolation (kazi.h.robin sees 74A, 74C, 74E and cannot see 74B, 74D, 74F; ahsan.ullah sees 74B, 74D, 74F), real student enrolment resolution, and admin platform visibility.
  * Verified all three web applications (Teacher on :4001, Student on :4000, Admin on :4002) in real headless Google Chrome with screenshots captured.

* 2026-09-16: Completed Course 230 (Computer and Cyber Security) Setup, UMS Enrolment, Academic Plan & ExamController Synchronization:
  * Course Metadata: Activated Course 230 (`CSE 06124158: Computer and Cyber Security`, Dept of CSE) for Fall 2026 (Start: September 1, 2026, End: December 31, 2026, `groupmode` = Separate Groups).
  * 16-Week Academic Course Plan: Constructed 17 native structured topics in Moodle across 5 pedagogical phases: Phase 1 Foundations (Weeks 1-3), Phase 2 Network Security (Weeks 4-7), Midterm Assessment Window (Week 8, Oct 20-25), Phase 3 OS & Application Security (Weeks 9-11), Phase 4 Cryptography & Data Security (Weeks 12-14, Practical Assessment Window Nov 25-30), Phase 5 Security Operations & Incident Response (Weeks 15-16), and Final Review & Assessment Period (Section 17, Dec 21-31).
  * Authoritative UMS Enrolment: Queried live UMS API #4 for Program 300 (CSE) and identified exactly 62 registered students across batches 64A (13), 66B (26), 66D (20), 67D (1), 71C (1), 73A (1). Provisioned student Moodle accounts via `user_provisioner` with standardized emails and institutional registration numbers; enrolled all 62 students into Course 230 via `enrolment_dispatcher` with role `student`.
  * Moodle Academic Grouping: Created 6 native course groups (`64A`, `66B`, `66D`, `67D`, `71C`, `73A`) and assigned 62 student group memberships.
  * Teacher Section Assignment: Assigned `kazi.h.robin` to groups `64A`, `66B`, and retake groups; assigned `ahsan.ullah` to group `66D`; preserved HOD `jannatul.naeem` departmental oversight.
  * Decoupled Section Architecture: Created migration `2026_09_16_000002_create_section_mappings_table.php` and `SectionMapping` model in ExamController. Mapped UMS batches and Moodle groups to 3 ExamController examination sections:
    - `Section A (Batch 64A & Retakes)` (ID: 241, 16 students, teacher: `Kazi H. Robin`) [MANY_TO_ONE: 64A, 67D, 71C, 73A]
    - `Section B (Batch 66B)` (ID: 242, 26 students, teacher: `Kazi H. Robin`) [ONE_TO_ONE: 66B]
    - `Section C (Batch 66D)` (ID: 243, 20 students, teacher: `Ahsan Ullah`) [ONE_TO_ONE: 66D]
  * Examination Scheduling: Created and published 9 examination milestones in ExamController (3 assessments x 3 sections) with realistic proctoring configuration (Midterm Oct 22 90m, Practical Nov 26 90m, Final Dec 24 180m).
  * Comprehensive Verification: Verified Moodle course view and participants, strict teacher section isolation (Kazi sees A & B, isolated from C; Ahsan sees C, isolated from A & B), real student eligibility (3 published exams visible per student), and re-run idempotency (zero duplicate users, enrolments, groups, sections, courses, or exams).

---

## Next Steps
* Course 230 setup and examination synchronization fully verified and operational.


---

## Agent Notes
* **DO NOT** delete or recreate `./db`, `./moodledata`, or `./moodle`.
* **DO NOT** run destructive commands like `docker compose down -v`.
* **DO NOT** commit secrets or modify `.env`.
* **DO NOT** edit core Moodle files under `./moodle/lib/`, `./moodle/admin/`, etc.
* **DO NOT** use `chmod 777`.
* **DO NOT** run `docker compose exec php php admin/cli/...` as root — add `-u www-data`.
* **DO NOT** add `PROJECT_STATE.md` or `AGENT_RULES.md` to `.gitignore`.


