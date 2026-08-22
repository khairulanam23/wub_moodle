# World University of Bangladesh (WUB) Moodle E-Learning Platform
## Comprehensive Feature & Architecture Documentation

---

## 1. Executive Overview

The World University of Bangladesh (WUB) Moodle e-learning platform is an enterprise-grade learning management system built on Moodle 5.x. It integrates official university academic structures, REST API synchronization with the University Management System (UMS), automated web staff synchronization from `wub.edu.bd`, student financial clearance enforcement, locked course lifecycles, and a contextual assessment framework.

---

## 2. Core Custom Plugins & Functional Modules

```text
                               WUB MOODLE PLATFORM ARCHITECTURE
                                              │
      ┌──────────────────┬────────────────────┼────────────────────┬──────────────────┐
      ▼                  ▼                    ▼                    ▼                  ▼
Academic Hierarchy    UMS & Auth      Financial Clearance    Course Lifecycle   Custom UI & Theme
local_wub_academic   local_wub_ums    auth_wub_auth_penalty  wub_course_offering   local_header
local_wub_teacher    local_wub_auth   wub_special_permission wub_academic_section  local_footer
local_mass_enroll    local_wub_login                         wub_assessment      theme_academi
```

---

## 3. Detailed Feature Breakdown

### A. Official WUB Academic Hierarchy Engine (`local_wub_academic`)
- **4 Official WUB Faculties**:
  1. *World School of Business (WSB)*
  2. *Faculty of Arts and Humanities (FAH)*
  3. *Faculty of Science (FS)*
  4. *Faculty of Engineering (FE)*
- **16 Academic Departments**: Full coverage for BBA, THM, ENG, LAW, MSJ, PHARM, ARCH, MPH, CE, CSE, EEE, MTE, TE, ME, AE, and EDU.
- **32 Academic Programs**: Preserves UMS `program_id` strings, academic levels, and department relationships.
- **Automated Web Sync & Importer**: Crawls `https://wub.edu.bd/main/wub_faculty` to auto-sync Deans, HODs, and faculty personnel idempotently. Supports CLI execution (`php local/wub_academic/cli/sync_academic.php`).
- **Hierarchy Integrity Validation**: Centralized validation helper (`academic_service::validate_academic_hierarchy_integrity()`) verifying zero unmapped programs or departments.

### B. UMS Student Synchronization (`local_wub_ums`)
- **Student Data Sync**: Synchronizes 7,214+ active student records from UMS REST API into local database `{enrol_ums_user}`.
- **Local Database Source of Truth**: Uses local database for all runtime operations (dashboards, course views, enrollments) to prevent redundant API calls.

### C. Student Financial Clearance & Dues Enforcement (`auth_wub_auth_penalty`)
- **Real-Time Dues Check**: Checks student financial clearance status (`remaining_dues`). Restricts access to exam cards, online quizzes, and course activities if financial dues are pending.
- **Special Permission Override (`local_wub_special_permission`)**: Administrative waiver system allowing accounts officers to grant temporary or permanent dues overrides for exam access.

### D. Teacher Management & Responsibility System (`local_wub_teacher`)
- **Teacher Profiles**: `{teacher}` table (32 active records) as single source of truth for employee IDs, designations, and academic departments.
- **Contextual Responsibilities**: `{wub_teacher_assignment}` assigns contextual roles per course offering/section:
  - *Course Coordinator*: Establishes assessment structure & course framework.
  - *Course Teacher*: Manages learning activities, lectures, and evaluations.
  - *Lab Teacher*: Manages laboratory sessions and practical assessments.
  - *Teaching Assistant (TA)*: Provides section assistance.
- **Position & Scope Separation**: Separates university positions (*VC*, *Dean*, *HOD*) from course teaching responsibilities.

### E. Course Lifecycle, Enrollment Windows & Reactivation Architecture
- **Independent Lifecycle Dates**:
  - **Course Status**: `UPCOMING`, `ACTIVE`, `FINISHED` based on `start_date` and `end_date`.
  - **Enrollment Window**: `ENROLLMENT_NOT_STARTED`, `ENROLLMENT_OPEN`, `ENROLLMENT_CLOSED` based on `enroll_start_date` and `enroll_end_date`.
- **Controlled Enrollment**: Disables public self-enrollment; enrollment is managed by administrators and authorized academic staff.
- **Course Reactivation & History Preservation**: Reactivating a course creates a new distinct `{wub_course_offering}` record per academic term, preserving all historical student enrollments, activity submissions, and gradebook records.

### F. Assessment Structure & Gradebook Strategy
- **Configurable Assessment Components**: Defined per course offering (`attendance`, `quiz`, `assignment`, `midterm`, `final`, `lab`) with configurable weight, max marks, minimum required activities, and required status.
- **Automated Weight Validation**: `validate_assessment_structure()` mathematically verifies that the sum of component weights for an offering equals **`100.00%`**.
- **Native Gradebook Integration**: Maps custom activity components into Moodle Gradebook without replacing native grading tables.

### G. Bulk Student Enrolment (`local_mass_enroll`)
- Batch enrolment and unenrolment engine allowing bulk CSV/Excel upload or student ID list pasting to enroll students into Moodle courses in batches.

### H. Role-Based Dashboards & Custom UI
- **Executive Administrator Dashboard**: Real-time metrics for Faculties, Departments, Programs, Courses, Offerings, Sections, Teachers, and Students.
- **Teacher Dashboard**: Active teacher assignments, course responsibilities, employee IDs, and assigned sections.
- **Student Dashboard**: Active program courses, enrolled sections, and academic status.
- **Custom UI Components**: High-contrast typography, custom WUB header (`local_header`), custom WUB footer (`local_footer`), public landing page (`local_wub_landing`), branded login portal (`local_wub_login`), and customized `academi` theme.

### I. Custom E-Learning Activity Foundations
- **`local_wub_quiz`**: Class activity layer for online quizzes and tests.
- **`local_wub_assignment`**: Class assignment submission and grading foundation.
- **`local_wub_attendance`**: Class attendance tracking and percentage calculation per section.
- **`local_wub_discussion`**: Interactive class discussion forums and Q&A boards.
- **`local_wub_material`**: Centralized repository for lecture slides, PDFs, and syllabi.
- **`local_wub_policy`**: University regulations, academic calendars, and policy portal.

---

## 4. Technical File Inventory

| Plugin / Directory | Primary Purpose | Key Files |
| :--- | :--- | :--- |
| **`local/wub_academic`** | Academic Hierarchy, Offerings, Sections & Dashboards | [`academic_service.php`](file:///var/www/moodle/public/local/wub_academic/classes/academic_service.php), [`dashboard_service.php`](file:///var/www/moodle/public/local/wub_academic/classes/dashboard_service.php), [`offerings.php`](file:///var/www/moodle/public/local/wub_academic/admin/offerings.php), [`assignments.php`](file:///var/www/moodle/public/local/wub_academic/admin/assignments.php) |
| **`local/wub_teacher`** | Teacher Profiles & Staff Records | [`teacher_service.php`](file:///var/www/moodle/public/local/wub_teacher/classes/teacher_service.php), [`admin.php`](file:///var/www/moodle/public/local/wub_teacher/admin.php) |
| **`local/wub_ums`** | UMS REST API Synchronization | [`sync_service.php`](file:///var/www/moodle/public/local/wub_ums/classes/sync_service.php) |
| **`local/wub_auth_penalty`** | Financial Clearance & Dues Control | [`authentication.php`](file:///var/www/moodle/public/local/wub_auth_penalty/classes/service/authentication.php) |
| **`local/wub_special_permission`** | Financial Dues Waivers & Overrides | [`index.php`](file:///var/www/moodle/public/local/wub_special_permission/index.php) |
| **`local/mass_enroll`** | Bulk Student CSV/Excel Enrolment | [`index.php`](file:///var/www/moodle/public/local/mass_enroll/index.php) |
| **`local/wub_landing`** | Public WUB Homepage | [`index.php`](file:///var/www/moodle/public/local/wub_landing/index.php) |
| **`local/wub_login`** | Branded Login Portal | [`index.php`](file:///var/www/moodle/public/local/wub_login/index.php) |
| **`local/header` & `local/footer`** | Custom Navigation Header & Footer | [`header.php`](file:///var/www/moodle/public/local/header/header.php), [`footer.php`](file:///var/www/moodle/public/local/footer/footer.php) |
