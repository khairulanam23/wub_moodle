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

$role = optional_param('role', 'student', PARAM_ALPHA);
$role = $policyservice->normalize_role($role);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

// If already authenticated (and not guest), redirect to destination or postlogin
if (isloggedin() && !isguestuser()) {
    $targeturl = $sessionservice->get_safe_redirect_url($returnurl, '/my/');
    redirect($targeturl);
}

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
        $SESSION->wub_restricted_clearance = $auth->get_clearance()->to_array();
        $SESSION->wub_restricted_user = (object)[
            'id' => $auth->get_user()->id,
            'fullname' => fullname($auth->get_user()),
            'username' => $auth->get_user()->username,
            'idnumber' => $auth->get_user()->idnumber,
        ];
        redirect(new moodle_url('/local/wub_auth/restricted.php'));
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
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('login_title', 'local_wub_auth'));
$PAGE->set_heading(get_string('login_title', 'local_wub_auth'));

$PAGE->add_body_class('wub-admin-login-layout');

if ($role === 'student' || $role === 'teacher') {
    $PAGE->add_body_class('wub-auth-hide-nav-items');
    $PAGE->add_body_class('wub-auth-role-' . $role);
}

// Logo URL (reuse Academi or core theme logo)
$logourl = new moodle_url('/local/wub_auth/pix/wub-logo.png');
$footerlogourl = '/pluginfile.php/1/theme_academi/footerlogo/1789361697/wub-logo.png';

$templatedata = [
    'logo_url' => $footerlogourl,
    'landing_url' => (new moodle_url('/local/wub_auth/landing.php'))->out(false),
    'login_url' => (new moodle_url('/local/wub_auth/login.php'))->out(false),
    'form_action' => (new moodle_url('/local/wub_auth/login.php'))->out(false),
    'sesskey' => sesskey(),
    'logintoken' => \core\session\manager::get_login_token(),
    'role' => $role,
    'role_badge_label' => ($role === 'admin') ? 'Administration Portal' : (($role === 'teacher') ? 'Faculty Portal' : 'Student Portal'),
    'returnurl' => $returnurl,
    'username' => s($username),
    'username_label' => ($role === 'student') ? 'Student ID or Institutional Email' : (($role === 'teacher') ? 'Faculty ID, Email, or Username' : 'Administrator Username or Email'),
    'username_placeholder' => ($role === 'student') ? '0326745530 or student email' : (($role === 'teacher') ? 'faculty@wub.edu.bd or username' : 'admin or institutional email'),
    'remember_username' => (bool)$rememberusername,
    'is_student_tab' => ($role === 'student'),
    'is_teacher_tab' => ($role === 'teacher'),
    'is_admin_tab' => ($role === 'admin'),
    'admin_hero_url' => (new moodle_url('/local/wub_auth/pix/admin_login_hero.png'))->out(false),
    'admin_logo_url' => (new moodle_url('/local/wub_auth/pix/admin_cis_logo.png'))->out(false),
    'has_error' => !empty($error),
    'error_message' => $error,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_wub_auth/login_page', $templatedata);
echo $OUTPUT->footer();
