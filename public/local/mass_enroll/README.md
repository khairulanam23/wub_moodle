# Moodle Bulk Enrollment & Access Control (`local_mass_enroll`)

The bulk enrollment plugin provides multi-user & course enrolment capabilities, external UMS REST API synchronization, and student financial due access control.

#### Developed By World University of Bangladesh
https://wub.edu.bd/

---

## Hook Lifecycle & Access Interception Architecture

### Overview
`local_mass_enroll` intercepts page access for authenticated students with outstanding financial dues (> 100 BDT) who do not possess active temporary special permissions.

### Hook Implementation (`db/hooks.php`)
- **Hook Subscription**: `\core\hook\output\before_http_headers::class`
- **Callback Target**: `[\local_mass_enroll\hook_callbacks::class, 'before_http_headers']`

### Why `before_http_headers` is Used
- **Execution Phase**: Triggered inside `core_renderer::header()` **before** any HTTP header or HTML output body is sent to the client (`headers_sent()` is `false`).
- **Context Safety**: Guarantees that `global $PAGE, $USER, $CFG, $SESSION;` are bound, `isloggedin()` and `!isguestuser()` are checked, and `$SESSION->loginerrormsg` session assignments execute safely.
- **Architectural Isolation**: Avoids executing session redirects or property assignments inside HTML head rendering hooks (`before_standard_head_html_generation`), which run after output body generation has commenced inside Mustache page templates.

### Student Due Restriction Flow
1. Intercepts dashboard access (`/my/`, `/my/index.php`, `my-index` page type) and enrolment attempts (`/enrol/index.php`).
2. Evaluates student due status via `\enrolhelper::check_student_due_status((int)$USER->id)`.
3. If access is restricted (`allowed === false`):
   - Invokes `require_logout()` to terminate student session.
   - Sets `$SESSION->loginerrormsg = 'Please complete the due payment to log in.'`.
   - Redirects to `/login/index.php` where the error message renders in the login alert box.

---

## Installation & REST API Instructions

### Install from moodle.org
* Go to https://moodle.org/plugins/view.php?plugin=enrol_bulk_enrollment and use the "Install now" button.

### Install with git
* Switch to the moodle local folder: `cd /path/to/moodle/local/`
* `git clone https://github.com/ProFarjan/moodle_bulk_enrollment.git mass_enroll`
* Navigate on your moodle page to Site Administration -> Notifications to complete installation.
