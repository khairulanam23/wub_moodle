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
 * Post-login role verification for WUB Landing.
 *
 * After Moodle authenticates the user, this page checks whether the
 * user's actual Moodle roles/capabilities match their declared intent
 * and verifies that the policy for the intended role has been accepted.
 *
 * CRITICAL SECURITY NOTE: The role selection button is an entry point
 * only. The actual authorization decision is made here using Moodle's
 * role/capability system. A user clicking "Teacher" does NOT become
 * a teacher — this page verifies they actually ARE one.
 *
 * @package    local_wub_landing
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
if (file_exists($CFG->dirroot . '/local/wub_policy/lib.php')) {
    require_once($CFG->dirroot . '/local/wub_policy/lib.php');
}

// This page requires authentication.
require_login();

global $USER, $SESSION, $PAGE, $OUTPUT;

// Retrieve the intended role and return URL from the session.
$intendedrole = isset($SESSION->wub_intended_role) ? $SESSION->wub_intended_role : '';
$returnurl = isset($SESSION->wub_return_url) ? $SESSION->wub_return_url : '';

// Determine the default target URL if authorized.
$targeturl = !empty($returnurl) ? new moodle_url($returnurl) : new moodle_url('/my/');

// If no intended role was set, redirect to dashboard or target URL.
if (empty($intendedrole)) {
    unset($SESSION->wub_intended_role);
    unset($SESSION->wub_return_url);
    redirect($targeturl);
}

// Validate the intended role value.
$validroles = ['student', 'teacher', 'admin'];
if (!in_array($intendedrole, $validroles)) {
    unset($SESSION->wub_intended_role);
    unset($SESSION->wub_return_url);
    redirect($targeturl);
}

// Bind device acceptance to this authenticated user.
if (function_exists('wub_policy_bind_user_acceptance')) {
    wub_policy_bind_user_acceptance((int)$USER->id, $intendedrole);
}

// Post-Login Policy Gate: Verify that this authenticated user has accepted the policy
// for this specific role within the previous 30 days and for the active policy version.
if (function_exists('wub_policy_is_accepted')) {
    if (!wub_policy_is_accepted($intendedrole, (int)$USER->id)) {
        // Keep session intent active for after agreement.
        $SESSION->wub_intended_role = $intendedrole;
        if (!empty($returnurl)) {
            $SESSION->wub_return_url = $returnurl;
        }
        $policyparams = [
            'role' => $intendedrole,
            'returnurl' => (new moodle_url('/local/wub_landing/postlogin.php'))->out(false),
        ];
        redirect(new moodle_url('/local/wub_policy/index.php', $policyparams));
    }
}

// Clean up the session variables immediately after policy check.
unset($SESSION->wub_intended_role);
unset($SESSION->wub_return_url);

// Perform authorization check based on intended role.
switch ($intendedrole) {
    case 'student':
        if (wub_landing_user_is_student($USER->id)) {
            if (file_exists($CFG->dirroot . '/local/mass_enroll/classes/enrolhelper.php')) {
                require_once($CFG->dirroot . '/local/mass_enroll/classes/enrolhelper.php');
                $helper = new \enrolhelper();
                $check = $helper->check_student_due_status((int)$USER->id);
                if (!empty($check) && isset($check['allowed']) && $check['allowed'] === false) {
                    redirect(new moodle_url('/local/mass_enroll/payment_notice.php'));
                }
            }
            redirect($targeturl);
        }
        $errormessage = get_string('notauthorisedstudent', 'local_wub_landing');
        break;

    case 'teacher':
        if (wub_landing_user_is_teacher($USER->id)) {
            redirect($targeturl);
        }
        $errormessage = get_string('notauthorisedteacher', 'local_wub_landing');
        break;

    case 'admin':
        if (is_siteadmin($USER)) {
            $admintarget = !empty($returnurl) ? new moodle_url($returnurl) : new moodle_url('/admin/');
            redirect($admintarget);
        }
        $errormessage = get_string('notauthorisedadmin', 'local_wub_landing');
        break;

    default:
        redirect($targeturl);
}

// If we reach here, the user is not authorized for their selected role.
// Display an error page.
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/wub_landing/postlogin.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('authorisationfailed', 'local_wub_landing'));
$PAGE->set_heading(get_string('authorisationfailed', 'local_wub_landing'));

echo $OUTPUT->header();
echo $OUTPUT->notification($errormessage, \core\output\notification::NOTIFY_WARNING);

$landingurl = new moodle_url('/local/wub_landing/index.php');
$dashboardurl = new moodle_url('/my/');

echo \html_writer::start_div('wub-postlogin-actions');
echo \html_writer::link(
    $landingurl,
    get_string('returnlanding', 'local_wub_landing'),
    ['class' => 'btn btn-primary mr-2']
);
echo \html_writer::link(
    $dashboardurl,
    get_string('gotodashboard', 'local_wub_landing'),
    ['class' => 'btn btn-secondary']
);
echo \html_writer::end_div();

echo $OUTPUT->footer();

// --- Helper functions ---

/**
 * Check whether a user has student-level access.
 *
 * @param int $userid The user ID to check.
 * @return bool True if the user is authorized as a student.
 */
function wub_landing_user_is_student(int $userid): bool {
    // Site admins are not students.
    if (is_siteadmin($userid)) {
        return false;
    }
    // Users with teaching capability across courses are teachers, not students.
    if (wub_landing_user_is_teacher($userid)) {
        return false;
    }
    return true;
}

/**
 * Check whether a user has teacher-level access in any course.
 *
 * @param int $userid The user ID to check.
 * @return bool True if the user has teaching capabilities in any course.
 */
function wub_landing_user_is_teacher(int $userid): bool {
    global $DB;

    // Get all courses the user is enrolled in.
    $courses = enrol_get_users_courses($userid, true, ['id']);
    if (empty($courses)) {
        return false;
    }

    // Check if the user has teacher capabilities in any enrolled course.
    foreach ($courses as $course) {
        $coursecontext = context_course::instance($course->id);
        if (has_capability('moodle/course:manageactivities', $coursecontext, $userid, false) ||
            has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid, false)) {
            return true;
        }
    }

    return false;
}
