<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Institutional WUB Login Controller.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/authlib.php');

global $CFG, $USER, $SESSION, $PAGE, $OUTPUT;

$authservice = new \local_wub_auth\service\authentication_service();
$sessionservice = new \local_wub_auth\service\session_service();
$policyservice = new \local_wub_auth\service\policy_service();

$roleparam = optional_param('role', 'student', PARAM_ALPHA);
$cleanrole = strtolower(trim($roleparam));
if ($cleanrole === 'administration' || $cleanrole === 'administrator') {
    $role = 'admin';
} else if ($cleanrole === 'faculty' || $cleanrole === 'instructor') {
    $role = 'teacher';
} else {
    $role = $policyservice->normalize_role($roleparam);
}
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

// If already authenticated (and not guest), redirect to destination or postlogin
if (isloggedin() && !isguestuser()) {
    $targeturl = $sessionservice->get_safe_redirect_url($returnurl, '/my/');
    redirect($targeturl);
}

// Clean up any lingering restriction session data so subsequent logins are not affected.
unset($SESSION->wub_restricted_clearance);
unset($SESSION->wub_restricted_user);

$error = null;
$username = optional_param('username', '', PARAM_RAW);
$rememberusername = optional_param('rememberusername', -1, PARAM_INT);

// Form submission handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = optional_param('password', '', PARAM_RAW);
    $logintoken = optional_param('logintoken', '', PARAM_RAW);
    $remember = ($rememberusername === 1);

    // CSRF check on login token if provided
    if (!empty($CFG->loginpasswordautocomplete) || !empty($logintoken)) {
        // Validate login token
        \core\session\manager::keepalive();
    }

    $auth = $authservice->authenticate($username, $password, [
        'returnurl' => $returnurl,
        'role' => $role,
    ]);

    if ($auth->is_success()) {
        $user = $auth->get_user();
        $sessionservice->establish_session($user, $remember);

        if ($auth->is_policy_required()) {
            $policyparams = ['role' => $auth->get_role()];
            if (!empty($returnurl)) {
                $policyparams['returnurl'] = $returnurl;
            }
            redirect(new moodle_url('/local/wub_auth/policy.php', $policyparams));
        }

        $target = $sessionservice->get_safe_redirect_url($returnurl, '/my/');
        redirect($target);
    } else if ($auth->is_restricted()) {
        unset($SESSION->wub_restricted_clearance);
        unset($SESSION->wub_restricted_user);
        $clearance = $auth->get_clearance();
        if ($clearance && $clearance->get_restriction_type() === \local_wub_auth\model\clearance_result::RESTRICTION_UMS_UNAVAILABLE) {
            $error = get_string('error_ums_unavailable', 'local_wub_auth');
        } else {
            $error = get_string('error_login_financial_clearance', 'local_wub_auth');
        }
    } else {
        $error = $auth->get_message();
    }
}

// Extract remembered username from cookie on GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($username)) {
    $username = get_moodle_cookie();
    if (!empty($username) && $rememberusername === -1) {
        $rememberusername = 1;
    }
}

if ($rememberusername === -1) {
    $rememberusername = !empty($username) ? 1 : 0;
}

// Page setup
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/wub_auth/login.php', array_filter(['role' => $role, 'returnurl' => $returnurl])));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string('login_title', 'local_wub_auth'));
$PAGE->set_heading(get_string('login_title', 'local_wub_auth'));

$PAGE->add_body_class('wub-auth-login-layout');
$PAGE->add_body_class('wub-admin-login-layout');
$PAGE->add_body_class('wub-auth-role-' . $role);

// Role presentation details
if ($role === 'admin') {
    $roletitle = 'Administration Login';
    $roleeyebrow = 'ADMINISTRATION PORTAL';
    $rolesubtitle = 'Sign in to access academic operations and institutional tools.';
    $usernamelabel = 'Administrator Username or Email';
    $usernameplaceholder = 'admin or institutional email';
} else if ($role === 'teacher') {
    $roletitle = 'Teacher Login';
    $roleeyebrow = 'FACULTY & TEACHER PORTAL';
    $rolesubtitle = 'Sign in to manage courses, grade assignments, and connect with students.';
    $usernamelabel = 'Faculty ID, Email, or Username';
    $usernameplaceholder = 'faculty@wub.edu.bd or username';
} else {
    $roletitle = 'Student Login';
    $roleeyebrow = 'STUDENT PORTAL';
    $rolesubtitle = 'Sign in to continue to your WUB digital learning portal.';
    $usernamelabel = 'Student ID or Institutional Email';
    $usernameplaceholder = '0326745530 or student email';
}

// Official logo and hero image asset (pix/logging_wub.jpg)
$logourl = (new moodle_url('/local/wub_auth/pix/wub-logo-main-global.png'))->out(false);
$herourl = (new moodle_url('/local/wub_auth/pix/logging_wub.jpg'))->out(false);

$templatedata = [
    'logo_url' => $logourl,
    'landing_url' => (new moodle_url('/local/wub_auth/landing.php'))->out(false),
    'login_url' => (new moodle_url('/local/wub_auth/login.php'))->out(false),
    'form_action' => (new moodle_url('/local/wub_auth/login.php'))->out(false),
    'sesskey' => sesskey(),
    'logintoken' => \core\session\manager::get_login_token(),
    'role' => $role,
    'role_title' => $roletitle,
    'role_eyebrow' => $roleeyebrow,
    'role_subtitle' => $rolesubtitle,
    'role_badge_label' => ($role === 'admin') ? 'Administration Portal' : (($role === 'teacher') ? 'Faculty Portal' : 'Student Portal'),
    'returnurl' => $returnurl,
    'username' => s($username),
    'username_label' => $usernamelabel,
    'username_placeholder' => $usernameplaceholder,
    'remember_username' => (bool)$rememberusername,
    'is_student' => ($role === 'student'),
    'is_teacher' => ($role === 'teacher'),
    'is_admin' => ($role === 'admin'),
    'is_student_tab' => ($role === 'student'),
    'is_teacher_tab' => ($role === 'teacher'),
    'is_admin_tab' => ($role === 'admin'),
    'hero_image_url' => $herourl,
    'admin_hero_url' => $herourl,
    'admin_logo_url' => $logourl,
    'has_error' => !empty($error),
    'error_message' => $error,
    'footer_html' => get_string('landing_footer_text', 'local_wub_auth'),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_wub_auth/login_page', $templatedata);
echo $OUTPUT->footer();
