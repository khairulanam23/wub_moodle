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
 * Custom WUB Login Page with Remember Me and Policy Verification.
 *
 * Provides a custom login experience styled identically to the WUB Landing Page.
 * Uses Moodle's core authentication APIs and encrypted cookie management.
 *
 * @package    local_wub_login
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/authlib.php');
if (file_exists($CFG->dirroot . '/local/wub_policy/lib.php')) {
    require_once($CFG->dirroot . '/local/wub_policy/lib.php');
}

global $CFG, $USER, $SESSION, $PAGE, $OUTPUT;

// Get role and returnurl parameter if passed.
$role = optional_param('role', '', PARAM_ALPHA);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

if (!empty($returnurl)) {
    $SESSION->wub_return_url = $returnurl;
}
if (empty($role) && !empty($SESSION->wub_intended_role)) {
    $role = $SESSION->wub_intended_role;
}

// Normalize role if provided.
if (!empty($role)) {
    if (function_exists('wub_policy_normalize_role')) {
        $role = wub_policy_normalize_role($role);
    } else if ($role === 'administrator') {
        $role = 'admin';
    }
}

// If user is already logged in (and not guest), redirect appropriately.
if (isloggedin() && !isguestuser()) {
    if (!empty($SESSION->wub_intended_role)) {
        redirect(new moodle_url('/local/wub_landing/postlogin.php'));
    } else {
        redirect(new moodle_url('/my/'));
    }
}

// Anti-Bypass Guard: If a specific role is selected or intended, verify 30-day policy acceptance.
if (!empty($role) && function_exists('wub_policy_is_accepted')) {
    if (!wub_policy_is_accepted($role)) {
        $policyparams = ['role' => $role];
        if (!empty($returnurl)) {
            $policyparams['returnurl'] = $returnurl;
        }
        redirect(new moodle_url('/local/wub_policy/index.php', $policyparams));
    }
}

$error = null;
$username = optional_param('username', '', PARAM_RAW);
$rememberusername = optional_param('rememberusername', -1, PARAM_INT);

// Pre-fill remembered username on GET requests if not explicitly supplied.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (empty($username)) {
        $username = get_moodle_cookie();
        if (!empty($username) && $rememberusername === -1) {
            $rememberusername = 1;
        }
    }
}
if ($rememberusername === -1) {
    $rememberusername = !empty($username) ? 1 : 0;
}

// Handle form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = optional_param('password', '', PARAM_RAW);
    $logintoken = optional_param('logintoken', '', PARAM_RAW);
    $rememberusername = optional_param('rememberusername', 0, PARAM_INT);

    if (!empty($username) && !empty($password)) {
        $errorcode = 0;
        $user = authenticate_user_login($username, $password, false, $errorcode, $logintoken);

        // Fallback: If user entered short username (e.g. 0326735386), try with @student.wub.ac.bd
        if (!$user && strpos($username, '@') === false) {
            $alt_username = trim($username) . '@student.wub.ac.bd';
            $user = authenticate_user_login($alt_username, $password, false, $errorcode, $logintoken);
        }

        // Fallback: If user entered an alternate domain email, try resolving to @student.wub.ac.bd
        if (!$user && strpos($username, '@') !== false && strpos($username, '@student.wub.ac.bd') === false) {
            $short_un = explode('@', $username)[0];
            $alt_username = trim($short_un) . '@student.wub.ac.bd';
            $user = authenticate_user_login($alt_username, $password, false, $errorcode, $logintoken);
        }

        if ($user) {
            // Log in the user.
            complete_user_login($user);

            // Handle Remember Username cookie persistence.
            if (!empty($CFG->nolastloggedin)) {
                // Do not store last logged in user in cookie if administratively disabled.
            } else if (!empty($rememberusername)) {
                set_moodle_cookie($user->username);
            } else {
                set_moodle_cookie('');
            }

            // Bind policy acceptance to authenticated user.
            if (function_exists('wub_policy_bind_user_acceptance')) {
                wub_policy_bind_user_acceptance((int)$user->id, $role);
            }

            // Handle post-login redirection.
            if (!empty($SESSION->wub_intended_role)) {
                redirect(new moodle_url('/local/wub_landing/postlogin.php'));
            } else if (!empty($SESSION->wantsurl)) {
                $urltogo = $SESSION->wantsurl;
                unset($SESSION->wantsurl);
                redirect($urltogo);
            } else {
                redirect(new moodle_url('/my/'));
            }
        } else {
            $error = get_string('invalidlogin', 'local_wub_login');
        }
    } else {
        $error = get_string('invalidlogin', 'local_wub_login');
    }
}

// Page setup.
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/wub_login/index.php', array_filter(['role' => $role])));
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('pluginname', 'local_wub_login'));
$PAGE->set_heading(get_string('pluginname', 'local_wub_login'));

require_once($CFG->dirroot . '/local/header/lib.php');
require_once($CFG->dirroot . '/local/footer/lib.php');

// Prepare renderable.
$validroles = ['student', 'teacher', 'admin'];
$displayrole = in_array($role, $validroles) ? $role : null;
$renderable = new \local_wub_login\output\login_page($displayrole, $error, $username, (bool)$rememberusername);

// Render page.
echo $OUTPUT->header();

// Render custom header.
if (function_exists('local_header_render')) {
    echo local_header_render($OUTPUT);
}

$templatedata = $renderable->export_for_template($OUTPUT);
echo $OUTPUT->render_from_template('local_wub_login/login_page', $templatedata);

// Render custom footer.
if (function_exists('local_footer_render')) {
    echo local_footer_render($OUTPUT);
}

echo $OUTPUT->footer();
