# World University of Bangladesh (WUB) Moodle Customization Portal

<p align="center">
  <a href="https://wub.edu.bd" target="_blank" title="World University of Bangladesh">
    <img src="https://raw.githubusercontent.com/moodle/moodle/main/.github/moodlelogo.svg" alt="Moodle Logo" width="250">
  </a>
</p>

This repository contains the complete custom eLearning platform for the **World University of Bangladesh (WUB)**, built upon Moodle 5.1+ and optimized for PHP 8.2+. It encompasses enterprise UMS (University Management System) integration, student payment dues restriction gates, custom login and role authorization workflows, policy compliance tracking, bulk course enrolment tools, and tailored institutional UI themes.

---

## Table of Contents

- [Architectural Overview](#architectural-overview)
- [Custom Plugins & Extension Directory](#custom-plugins--extension-directory)
- [Detailed Custom Plugin Specifications](#detailed-custom-plugin-specifications)
  - [1. Authentication & Penalty Restriction Gate (`auth_wub_auth_penalty`)](#1-authentication--penalty-restriction-gate-auth_wub_auth_penalty)
  - [2. Due Calculation & Penalty Services (`local_wub_auth_penalty`)](#2-due-calculation--penalty-services-local_wub_auth_penalty)
  - [3. Role Authorization & Intent Router (`local_wub_auth`)](#3-role-authorization--intent-router-local_wub_auth)
  - [4. Custom Login & Identity Normalization (`local_wub_login`)](#4-custom-login--identity-normalization-local_wub_login)
  - [5. Institutional Policy & Compliance Manager (`local_wub_policy`)](#5-institutional-policy--compliance-manager-local_wub_policy)
  - [6. Canonical UMS Sync Engine & API Client (`local_wub_ums`)](#6-canonical-ums-sync-engine--api-client-local_wub_ums)
  - [7. Bulk Enrolment & Access Control (`local_mass_enroll`)](#7-bulk-enrolment--access-control-local_mass_enroll)
  - [8. Portal Landing Page & Course Catalog (`local_wub_landing`)](#8-portal-landing-page--course-catalog-local_wub_landing)
  - [9. Special Login Permission Manager (`local_wub_special_permission`)](#9-special-login-permission-manager-local_wub_special_permission)
  - [10. Institutional Header & Footer Renders (`local_header` & `local_footer`)](#10-institutional-header--footer-renders-local_header--local_footer)
  - [11. Customized Academi Institutional Theme (`theme_academi`)](#11-customized-academi-institutional-theme-theme_academi)
- [End-to-End User Authentication & Financial Restriction Flow](#end-to-end-user-authentication--financial-restriction-flow)
- [UMS Data Synchronization & Field Ownership Model](#ums-data-synchronization--field-ownership-model)
- [Developer Modification & Task Reference Matrix](#developer-modification--task-reference-matrix)
- [System Requirements & Installation](#system-requirements--installation)
- [License & Institutional Information](#license--institutional-information)

---

## Architectural Overview

The portal extends Moodle core with modular custom plugins that separate concerns across **Authentication (`auth/`)**, **Local Services (`local/`)**, and **UI Themes (`theme/`)**.

```text
/var/www/moodle/
├── public/
│   ├── auth/
│   │   └── wub_auth_penalty/           → Auth Plugin: Financial due restriction & login gate
│   ├── local/
│   │   ├── wub_auth_penalty/           → Local Plugin: Due calculator engine & UMS Student API client
│   │   ├── wub_auth/                   → Local Plugin: Role selection entry & post-login capability routing
│   │   ├── wub_login/                  → Local Plugin: Custom login UI, credential dispatch & email suffixing
│   │   ├── wub_policy/                 → Local Plugin: University policy agreement view, DB & cookie tracking
│   │   ├── wub_ums/                    → Local Plugin: Canonical UMS REST API client & student account sync
│   │   ├── mass_enroll/                → Local Plugin: Admin bulk enrolment portal, sync UI & payment notice
│   │   ├── wub_landing/                → Local Plugin: Public portal landing page & course catalog
│   │   ├── wub_special_permission/     → Local Plugin: Admin interface for temporary login override permissions
│   │   ├── header/                     → Local Plugin: Custom header bar navigation renderer
│   │   └── footer/                     → Local Plugin: Custom footer bar navigation renderer
│   └── theme/
│       └── academi/                    → Custom Theme: Adapted Academi institutional styling & routing
└── README.md                           → Primary project documentation & architecture guide
```

---

## Custom Plugins & Extension Directory

| Plugin Directory | Frankenstyle Component Name | Type | Key Responsibility |
| :--- | :--- | :--- | :--- |
| `public/auth/wub_auth_penalty` | `auth_wub_auth_penalty` | `auth` | Secondary authentication plugin blocking students with dues > 100 BDT. |
| `public/local/wub_auth_penalty` | `local_wub_auth_penalty` | `local` | Core due calculation engine (-100 buffer, semester start dates) & UMS API integration. |
| `public/local/wub_auth` | `local_wub_auth` | `local` | Role intent router (`$SESSION->wub_intended_role`), policy check & post-login capability routing. |
| `public/local/wub_login` | `local_wub_login` | `local` | Modern custom login page UI, core Moodle auth dispatch, and short student ID auto-suffixing. |
| `public/local/wub_policy` | `local_wub_policy` | `local` | University terms & policy form, 30-day persistence (`{local_wub_policy_accept}` & device cookie). |
| `public/local/wub_ums` | `local_wub_ums` | `local` | Canonical UMS sync service (`sync_service.php`) for student account creation/update & API client. |
| `public/local/mass_enroll` | `local_mass_enroll` | `local` | Admin bulk enrolment portal, UMS sync comparison UI, Excel export & access control hooks. |
| `public/local/wub_landing` | `local_wub_landing` | `local` | Public portal landing page, public course details, paginated catalog & role entry buttons. |
| `public/local/wub_special_permission` | `local_wub_special_permission` | `local` | Admin dashboard for granting/revoking student `wub_permission` temporary login overrides. |
| `public/local/header` | `local_header` | `local` | Institutional transparent top header bar renderer (`local_header_render`). |
| `public/local/footer` | `local_footer` | `local` | Institutional footer renderer (`local_footer_render`) with branding and social links. |
| `public/theme/academi` | `theme_academi` | `theme` | Customized institutional theme with WUB identity, header/footer hooks, and PHP 8.2+ support. |

---

## Detailed Custom Plugin Specifications

### 1. Authentication & Penalty Restriction Gate (`auth_wub_auth_penalty`)
- **Location**: `public/auth/wub_auth_penalty`
- **Purpose**: Acts as an automated security gate during authentication.
- **Key Features**:
  - Evaluates student payment dues before establishing a Moodle user session.
  - Queries `\local_wub_auth_penalty\service\penalty_checker::is_access_allowed()`.
  - Blocks authentication for students owing > 100 BDT unless an active special permission (`wub_permission`) is present.
  - Redirects blocked users back to the login page with tailored error notification messages.

### 2. Due Calculation & Penalty Services (`local_wub_auth_penalty`)
- **Location**: `public/local/wub_auth_penalty`
- **Purpose**: High-security financial penalty calculation engine and network cURL API client.
- **Key Services**:
  - `student_api.php`: Network client supporting HTTP Digest/Basic Auth and `X-API-KEY` injection for UMS endpoints (`students/student_payment_info`, `payments/student_fees_details`).
  - `authentication.php`: Multi-tiered authentication (internal Moodle password check with fallback to WUB Student Portal API and auto-sync).
  - `due_calculator.php`: Preserves institutional due rules (-100 BDT buffer, monthly installment adjustments, program-specific start dates e.g., Sept 10 vs Aug 15).
  - `penalty_checker.php`: Evaluates access status, enforces 10-minute session caching (`wub_due_status_$userid`), and handles siteadmin/teacher exemptions.

### 3. Role Authorization & Intent Router (`local_wub_auth`)
- **Location**: `public/local/wub_auth`
- **Purpose**: Controls role selection entry points and post-login capability verification.
- **Key Files**:
  - `auth.php`: Role selection entry point (`role=student|teacher|admin`), sets `$SESSION->wub_intended_role`, and validates policy compliance.
  - `postlogin.php`: Executes post-authentication verification to ensure logged-in users possess genuine Moodle capabilities matching their intended role.
  - `lib.php`: Core helper functions `wub_auth_user_is_student()` and `wub_auth_user_is_teacher()`.

### 4. Custom Login & Identity Normalization (`local_wub_login`)
- **Location**: `public/local/wub_login`
- **Purpose**: Renders the custom institutional login view and normalizes user credentials.
- **Key Features**:
  - Displays a modern, responsive login interface adhering to WUB identity branding.
  - Auto-normalizes numeric student IDs (e.g., `0326735386` -> `0326735386@student.wub.ac.bd`) via `wub_ums_normalize_username()`.
  - Dispatches credentials to Moodle core `authenticate_user_login()`.
  - Manages encrypted "remember username" device cookies.

### 5. Institutional Policy & Compliance Manager (`local_wub_policy`)
- **Location**: `public/local/wub_policy`
- **Purpose**: Enforces institutional Terms & Conditions agreement prior to system access.
- **Key Features**:
  - Renders 20 structured university policy points categorized into 4 parts (`index.php`).
  - Maintains 30-day policy acceptance persistence in database `{local_wub_policy_accept}` and secure device cookie (`wub_policy_device`).
  - Automatically binds pre-login device acceptances to authenticated user IDs upon successful login (`wub_policy_bind_user_acceptance`).

### 6. Canonical UMS Sync Engine & API Client (`local_wub_ums`)
- **Location**: `public/local/wub_ums`
- **Purpose**: Canonical single source of truth for UMS REST API communication and student synchronization.
- **Key Services**:
  - `sync_service.php`: Authoritative sync service for creating/updating Moodle student accounts from UMS data.
  - `api_client.php`: REST API executor fetching programs, batches, student lists, and payment info.
  - Admin settings for configuring UMS API URLs, credentials, and API keys.

### 7. Bulk Enrolment & Access Control (`local_mass_enroll`)
- **Location**: `public/local/mass_enroll`
- **Purpose**: Administrative tool for bulk student course enrolment, UMS student synchronization comparison, and access control hooks.
- **Key Components**:
  - `enrolled.php`: Bulk enrolment UI allowing course selection and multi-student enrolment.
  - `sync.php`: Interactive UMS student dataset comparison table identifying synced/unsynced accounts.
  - `export.php`: Secure Excel download callback for enrolment lists.
  - `hook_callbacks.php`: Subscribes to Moodle's `before_http_headers` hook to intercept restricted student dashboard/enrolment access before header output is sent.

### 8. Portal Landing Page & Course Catalog (`local_wub_landing`)
- **Location**: `public/local/wub_landing`
- **Purpose**: Public portal entry point, public course showcase, and paginated course catalog.
- **Key Features**:
  - `index.php`: Main landing page with institutional hero banner and role entry buttons.
  - `catalog.php`: Publicly searchable and paginated course directory.
  - `course.php`: Detailed course view respecting Moodle visibility rules.

### 9. Special Login Permission Manager (`local_wub_special_permission`)
- **Location**: `public/local/wub_special_permission`
- **Purpose**: Administrative dashboard for granting temporary login overrides to students with dues.
- **Key Features**:
  - Grants/revokes temporary `wub_permission` user preferences valid until 23:59:59 of a target date.
  - Allows approved students to bypass automated financial due checks during their extension period.
  - Full audit logging via Moodle event `\local_wub_special_permission\event\permission_updated`.

### 10. Institutional Header & Footer Renders (`local_header` & `local_footer`)
- **Location**: `public/local/header` and `public/local/footer`
- **Purpose**: Provides uniform institutional top header and bottom footer navigation bars.
- **Key Functions**: `local_header_render($OUTPUT)` and `local_footer_render($OUTPUT)`.

### 11. Customized Academi Institutional Theme (`theme_academi`)
- **Location**: `public/theme/academi`
- **Purpose**: Custom presentation layer implementing WUB's brand guidelines.
- **Key Modifications**: Integrated header/footer blocks, custom logo branding, revised navigation routing, and PHP 8.2+ compatibility enhancements.

---

## End-to-End User Authentication & Financial Restriction Flow

```text
[ Visitor arrives at WUB Portal Landing Page ]
                     │
                     ▼ (Click Role Button: Student / Teacher / Admin)
     [ local/wub_auth/auth.php?role=student ]
                     │
                     ▼ (Check wub_policy_is_accepted)
    ┌────────────────┴────────────────┐
    │ Not Accepted (Within 30 Days)   │ Accepted
    ▼                                 ▼
[ local/wub_policy/index.php ]  [ local/wub_login/index.php ]
    │ (Accept Terms)                  │ (Enter Credentials)
    └────────────────┬────────────────┘
                     │
                     ▼ (authenticate_user_login & username normalization)
      [ local/wub_auth/postlogin.php ]
                     │
                     ▼ (Capability & Financial Dues Verification)
    ┌────────────────┴────────────────┐
    │ Site Admin / Teacher            │ Student
    ▼                                 ▼
[ Granted Dashboard Access ]    [ Evaluate Financial Dues via wub_ums / wub_auth_penalty ]
                                      │
                         ┌────────────┴────────────┐
                         │ Has Dues > 100 BDT      │ Dues <= 100 BDT OR
                         │ AND No wub_permission   │ Active wub_permission Valid
                         ▼                         ▼
            [ Terminate Session & Redirect ]  [ Access Granted to /my/ ]
            [ To Login with Notice ]
```

---

## UMS Data Synchronization & Field Ownership Model

When synchronizing student data from UMS via `local_wub_ums\sync_service`, strict field ownership rules are enforced to preserve system integrity:

| Field Group | Fields | Ownership Policy |
| :--- | :--- | :--- |
| **UMS-Owned** | `username`, `email`, `firstname`, `lastname`, `department`, `institution`, `idnumber` | Updated automatically during UMS sync. |
| **Moodle-Owned** | `password`, `auth`, `confirmed`, `preferences` | **NEVER** overwritten during UMS sync. Existing user passwords remain intact. |
| **Admin-Controlled** | `wub_permission`, `special_premission_expiry`, assigned roles, course enrolments | **NEVER** modified or reset by sync operations. |
| **New Accounts** | Initial `password` = Student ID (hashed) | Password initialized to Student ID; user can update password later. |

---

## Developer Modification & Task Reference Matrix

| Task / Feature Modification | Plugin to Inspect | Primary Target Files |
| :--- | :--- | :--- |
| **Role authorization, session role intent, postlogin checks** | `wub_auth` | `auth.php`, `postlogin.php`, `lib.php` |
| **Financial due calculations (-100 buffer, semester start dates)** | `wub_auth_penalty` | `classes/service/due_calculator.php` |
| **Access restriction enforcement & 10-min session cache** | `wub_auth_penalty` | `classes/service/penalty_checker.php` |
| **UMS REST API client endpoints & authentication** | `wub_auth_penalty` / `wub_ums` | `classes/service/student_api.php`, `classes/api_client.php` |
| **Student sync field mapping & account creation** | `wub_ums` | `classes/sync_service.php` |
| **Custom login UI, styling, and short ID normalization** | `wub_login` | `templates/login_page.mustache`, `index.php` |
| **University terms content & policy expiry period** | `wub_policy` | `classes/output/policy_page.php`, `lib.php` |
| **Bulk enrolment course selection & UMS sync UI** | `mass_enroll` | `enrolled.php`, `sync.php`, `classes/enrolhelper.php` |
| **Access restriction hook execution on headers** | `mass_enroll` | `classes/hook_callbacks.php`, `db/hooks.php` |
| **Landing page layout, hero text, and course catalog** | `wub_landing` | `templates/landing_page.mustache`, `catalog.php` |
| **Temporary special login permission grant / revoke UI** | `wub_special_permission` | `index.php`, `classes/local/permission_manager.php` |
| **Institutional top navigation header links** | `header` | `lib.php`, `templates/header.mustache` |
| **Institutional footer links and copyright branding** | `footer` | `lib.php`, `templates/footer.mustache` |
| **Global theme layout, CSS styles, and PHP 8.2 compatibility** | `academi` | `config.php`, `style/custom.css`, `layout/` |

---

## System Requirements & Installation

- **Moodle**: 5.1+
- **PHP**: 8.2+ (with `curl`, `json`, `mbstring`, `gd`, `zip`, `xml` extensions)
- **Database**: MySQL 8.0+ / MariaDB 10.6+
- **Web Server**: Nginx or Apache2 with mod_rewrite enabled

### Plugin Installation & Updates

1. Ensure plugin files are located in `public/local/`, `public/auth/`, and `public/theme/`.
2. Set directory ownership and permissions:
   ```bash
   sudo chown -R www-data:www-data /var/www/moodle/public
   sudo chmod -R 775 /var/www/moodle/public
   ```
3. Visit **Site Administration > Notifications** to execute database updates and schema upgrades.

---

## License & Institutional Information

This customization project is developed for the **World University of Bangladesh (WUB)**.  
Moodle core is open-source software licensed under the [GNU General Public License v3.0](http://www.gnu.org/licenses/gpl-3.0.html).

- **University Website**: [https://wub.edu.bd](https://wub.edu.bd)
- **Moodle Documentation**: [https://docs.moodle.org](https://docs.moodle.org) | [https://moodledev.io](https://moodledev.io)
