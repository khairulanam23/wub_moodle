# Central Architecture & Functionality Guide (`public/local/`)

Welcome to the central developer documentation and architectural guide for custom Moodle plugins under `/var/www/moodle/public/local/`.

This document serves as the **definitive reference map** for developers extending, debugging, or maintaining custom features within the WUB eLearning Portal.

---

## 1. Directory Structure & Plugin Map

```text
local/
├── footer/              → Custom Footer Navigation Bar Rendering (local_footer)
├── header/              → Custom Header Navigation Bar Rendering (local_header)
├── mass_enroll/         → Bulk Course Enrolment, UMS Sync UI & Excel Export (local_mass_enroll)
├── wub_auth/            → Role Selection, Session Intent & Post-Login Authorization (local_wub_auth)
├── wub_landing/         → Landing Page, Course Showcase & Catalog (local_wub_landing)
├── wub_login/           → Custom Login UI, Moodle Core Authentication & Cookies (local_wub_login)
├── wub_policy/          → Policy Agreement View, 30-Day DB & Device Cookie Persistence (local_wub_policy)
├── wub_special_permission/ → Admin Interface for Managing Student Special Login Permissions (local_wub_special_permission)
├── wub_ums/             → Canonical UMS API Client, Student Sync Engine & Payment Dues Verifier (local_wub_ums)
└── readme.md            → Central Architecture and Developer Map
```

---

## 2. Executive Summary of Plugin Responsibilities

| Responsibility Area | Handled By Plugin | Frankenstyle Name | Primary Responsibilities |
| :--- | :--- | :--- | :--- |
| **Authentication & Role Authorization Gate** | `local/wub_auth` | `local_wub_auth` | Manages role selection entry (`auth.php`), stores `$SESSION->wub_intended_role`, verifies policy gating, executes post-login role capability checks (`postlogin.php`), and checks student dues via `wub_ums`. |
| **Landing Page & Course Showcase** | `local/wub_landing` | `local_wub_landing` | Public portal landing page (`index.php`), public course view (`course.php`), paginated course catalog (`catalog.php`). Delegates legacy `auth.php` and `postlogin.php` to `wub_auth`. |
| **Custom Login & Cookie Persistence** | `local/wub_login` | `local_wub_login` | Renders custom login page UI, dispatches login credentials to Moodle's `authenticate_user_login()`, auto-suffixes short student IDs (`@student.wub.ac.bd`), manages encrypted "remember username" cookies. |
| **Policy & Terms Acceptance** | `local/wub_policy` | `local_wub_policy` | Renders 20 detailed university terms (`index.php`), manages 30-day policy persistence across database table `{local_wub_policy_accept}` and device cookie (`wub_policy_device`), provides verification API (`wub_policy_is_accepted`). |
| **UMS Integration & Student Sync** | `local/wub_ums` | `local_wub_ums` | **Canonical sync service** (`sync_service.php`) — single source of truth for student account creation/update. REST API Client (`api_client.php`) for programs, batches, student lists, and payment info. Account mapping: username = student ID (pure digits), email = `student_id@student.wub.edu.bd`, initial password = student ID. Preserves existing passwords and `special_premission` during sync. |
| **Bulk Enrolment & UMS Sync UI** | `local/mass_enroll` | `local_mass_enroll` | Admin bulk enrolment portal (`enrolled.php`), UMS sync comparison UI (`sync.php`), course enrolment processing (`request_enrolled.php`, `submit_enrolled.php`), AJAX API handler (`api.php`), Excel export (`export.php`), payment hold notice UI (`payment_notice.php`). All student creation/update delegated to `local_wub_ums\sync_service`. |
| **Header Bar Rendering** | `local/header` | `local_header` | Custom top transparent navigation bar rendering (`local_header_render`). |
| **Footer Bar Rendering** | `local/footer` | `local_footer` | Custom bottom university footer bar rendering (`local_footer_render`). |

---

## 3. Detailed File Index by Plugin

### 3.1 `local/wub_auth` [NEW]
- **`version.php`**: Component metadata and operational documentation for `local_wub_auth`.
- **`auth.php`**: Entry controller when user selects a role (Student / Teacher / Admin). Sets `$SESSION->wub_intended_role` and delegates to `wub_policy` or `wub_login`.
- **`postlogin.php`**: Security authorization gate after Moodle authentication. Validates that the logged-in user actually possesses student or teacher capabilities in Moodle. Evaluates student dues status via `wub_ums`.
- **`lib.php`**: Helper functions:
  - `wub_auth_user_is_student(int $userid)`: Evaluates student-level access.
  - `wub_auth_user_is_teacher(int $userid)`: Evaluates teacher capability across enrolled courses.
- **`lang/en/local_wub_auth.php`**: Language strings for role authorization errors.

### 3.2 `local/wub_landing`
- **`index.php`**: Public landing page controller for guest & authenticated users.
- **`catalog.php`**: Publicly accessible, paginated course catalog.
- **`course.php`**: Public course information page respecting Moodle visibility rules.
- **`auth.php` & `postlogin.php`**: Backward-compatibility wrappers delegating execution to `local_wub_auth`.
- **`lib.php`**: Plugin library containing navigation tree hook and alias functions for `wub_landing_user_is_student()` and `wub_landing_user_is_teacher()`.
- **`classes/output/`**: Renderable classes (`landing_page.php`, `course_catalog.php`, `course_details.php`).
- **`templates/`**: Mustache templates (`landing_page.mustache`, `landing_page_auth.mustache`, `course_catalog.mustache`, `course_details.mustache`, `course_card.mustache`).

### 3.3 `local/wub_login`
- **`index.php`**: Controller & view for WUB custom login page. Dispatches credentials to Moodle core auth (`authenticate_user_login()`). Resolves short student IDs into full email addresses (`0326735386@student.wub.ac.bd`) via `wub_ums_normalize_username()`.
- **`classes/output/login_page.php`**: Renderable class for custom login page.
- **`templates/login_page.mustache`**: Mustache template for login UI.
- **`pix/`**: Branding logos (`wub-logo.png`, `wubImage.jpg`).

### 3.4 `local/wub_policy`
- **`index.php`**: Controller & view for university policy agreement form with sesskey CSRF protection.
- **`lib.php`**: Core policy persistence library:
  - `wub_policy_is_accepted(string $role, int $userid)`: Checks 30-day validity in session cache, database (`{local_wub_policy_accept}`), or device cookie (`wub_policy_device`).
  - `wub_policy_record_acceptance(string $role, int $userid)`: Records policy agreement in database, sets 60-day secure cookie, and updates session cache.
  - `wub_policy_bind_user_acceptance(int $userid, string $role)`: Binds pre-login device acceptances to authenticated user ID upon login.
  - `wub_policy_normalize_role(string $role)`: Normalizes role strings (`student`, `teacher`, `admin`).
  - `wub_policy_get_version()`: Returns active policy version string.
- **`classes/output/policy_page.php`**: Renderable class structuring 20 policies into 4 category parts.
- **`db/install.xml`**: Database schema for `{local_wub_policy_accept}` table.

### 3.5 `local/wub_ums`
- **`version.php`**: Component metadata for `local_wub_ums`.
- **`settings.php`**: Admin configuration for UMS REST API endpoints (`api_url`, `api_url_programs`, `api_url_batch`, `api_ums_courses`, `api_student_payment_info`, `api_username`, `api_password`, `api_x_api_key`).
- **`lib.php`**: Public library exposing `wub_ums_get_api_client()`, `wub_ums_check_student_due_status()`, and `wub_ums_normalize_username()`.
- **`classes/api_client.php`**: Low-level cURL executor with Basic/Digest auth and X-API-KEY header injection. Provides `get_programs()`, `get_batches()`, `get_students_by_program_batch()`, and `get_student_payment_info()`.
- **`classes/sync_service.php`**: **Canonical single source of truth** for student account creation/update. Field ownership model:
  - **UMS-owned** (updated during sync): username, email, firstname, lastname, department, institution, idnumber
  - **Moodle-owned** (NEVER overwritten): password, auth, confirmed, preferences
  - **Admin-controlled** (NEVER overwritten): special_premission, special_premission_expiry, roles, enrolments
  - New student password: student ID (hashed via `update_internal_user_password()`)
  - Existing student password: preserved unchanged

### 3.6 `local/mass_enroll`
- **`enrolled.php`**: Admin Bulk Enrolment portal — select courses, programs, batches, and students for course enrolment.
- **`sync.php`**: UMS Student Synchronization — compare UMS students with Moodle users, identify synced/unsynced, create new accounts.
- **`api.php`**: AJAX endpoint handling program/batch/student queries, sync operations, and enrolment actions. Includes CSRF sesskey validation.
- **`massenrol.php`**: Legacy admin UI for bulk enrolments.
- **`massunenrol.php`**: Admin UI for bulk unenrolments.
- **`request_enrolled.php` & `submit_enrolled.php`**: AJAX handlers for enrolment verification and submission.
- **`payment_notice.php`**: Restricted dashboard page displayed to students with dues > 100 BDT.
- **`export.php`**: Excel export downloader callback.
- **`classes/enrolhelper.php`**: Business logic helper. **All student creation/update delegated to `local_wub_ums\sync_service`** via `sync_or_create_student_user()`. Also handles course enrolment logic, UMS API data fetching for UI, and due status checking.
- **`classes/hook_callbacks.php`**: Moodle hook intercepting dashboard and enrolment page access for due-restricted students.
- **`templates/enrolled.mustache`**: Mustache template for Bulk Enrolment page with course/student selection UI.
- **`templates/sync.mustache`**: Mustache template for UMS Sync page with student comparison table.

### 3.7 `local/header` & `local/footer`
- **`local/header/lib.php`**: Renders top navigation bar via `local_header_render($OUTPUT)`.
- **`local/footer/lib.php`**: Renders bottom footer bar via `local_footer_render($OUTPUT)`.

---

## 4. End-to-End User Authentication & Authorization Flow

```text
Step 1: Visitor arrives at Portal Landing Page
        → local/wub_landing/index.php

Step 2: Visitor selects Intended Role (Student / Teacher / Admin)
        → local/wub_auth/auth.php?role=student (or legacy wrapper local/wub_landing/auth.php)
        → Sets $SESSION->wub_intended_role = 'student'

Step 3: Policy Gate Verification (local/wub_policy/lib.php)
        → Calls wub_policy_is_accepted('student')
        → If NOT accepted within 30 days: Redirect to local/wub_policy/index.php
        → User accepts policy -> recorded in {local_wub_policy_accept} + device cookie

Step 4: Authentication (local/wub_login/index.php)
        → Renders custom login form
        → User submits credentials -> authenticate_user_login()
        → Normalizes short ID to username@student.wub.edu.bd via wub_ums_normalize_username()
        → Binds policy acceptance to user ID via wub_policy_bind_user_acceptance()
        → Redirects to local/wub_auth/postlogin.php

Step 5: Capability Authorization (local/wub_auth/postlogin.php)
        → Checks wub_auth_user_is_student($USER->id) or wub_auth_user_is_teacher()
        → For Students: Checks dues status via wub_ums_check_student_due_status()

Step 6: Access Granted / Restricted
        → If Dues > 100 BDT: Logout, set $SESSION->loginerrormsg, and redirect to /login/index.php
        → If Dues <= 100 BDT (or Special Permission active): Redirect to Moodle Dashboard (/my/)
```

---

## 5. Developer Feature Modification Matrix

| Feature / Task | Plugin to Inspect | Primary File(s) |
| :--- | :--- | :--- |
| **Role selection, postlogin authorization, or role capability logic** | `wub_auth` | `auth.php`, `postlogin.php`, `lib.php` |
| **Change landing page hero text, banners, or layout** | `wub_landing` | `templates/landing_page.mustache`, `classes/output/landing_page.php` |
| **Add or update terms in university policies** | `wub_policy` | `classes/output/policy_page.php`, `lang/en/local_wub_policy.php` |
| **Change policy validity period (e.g. from 30 days to 60 days)** | `wub_policy` | `lib.php` (`WUB_POLICY_DEFAULT_EXPIRY_DAYS`) or Admin Settings |
| **Modify custom login page styling or fields** | `wub_login` | `templates/login_page.mustache`, `styles.css` |
| **Change login fallback domain (`@student.wub.edu.bd`)** | `wub_ums` / `wub_login` | `local/wub_ums/lib.php` (`wub_ums_normalize_username`) |
| **Update UMS API endpoints, timeouts, or headers** | `wub_ums` | `classes/api_client.php`, `settings.php` |
| **Modify student sync (account mapping, password rules)** | `wub_ums` | `classes/sync_service.php` |
| **Modify student payment dues threshold (e.g. > 100 BDT)** | `wub_auth_penalty` | `classes/service/due_calculator.php`, `penalty_checker.php` |
| **Update Payment Hold Notice UI text or links** | `mass_enroll` | `payment_notice.php` |
| **Add fields to Mass Enrolment Excel Export** | `mass_enroll` | `classes/enrolhelper.php` (`generateXl`) |
| **Modify UMS Sync comparison/display** | `mass_enroll` | `classes/enrolhelper.php` (`get_ums_students_comparison`), `templates/sync.mustache` |
| **Modify Bulk Enrolment course/student selection** | `mass_enroll` | `classes/enrolhelper.php`, `templates/enrolled.mustache` |
| **Manage special permission for students** | `wub_special_permission` | `index.php` |
| **Modify special permission expiration logic** | `wub_auth_penalty` | `classes/service/penalty_checker.php` (`has_valid_special_permission`) |
| **Modify header navigation logo or links** | `header` | `templates/header.mustache`, `classes/output/main.php` |
| **Modify footer copyright or social links** | `footer` | `templates/footer.mustache`, `classes/output/main.php` |
