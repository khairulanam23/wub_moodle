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
 * Institutional Policy Acceptance Controller.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $CFG, $USER, $PAGE, $OUTPUT;

$policyservice = new \local_wub_auth\service\policy_service();
$sessionservice = new \local_wub_auth\service\session_service();

$role = optional_param('role', 'student', PARAM_ALPHA);
$role = $policyservice->normalize_role($role);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

$userid = (isloggedin() && !isguestuser()) ? (int)$USER->id : 0;

// If already accepted for this role and current version, advance cleanly
if ($policyservice->is_policy_accepted($role, $userid)) {
    if ($userid > 0) {
        $target = $sessionservice->get_safe_redirect_url($returnurl, '/my/');
        redirect($target);
    } else {
        $loginparams = ['role' => $role];
        if (!empty($returnurl)) {
            $loginparams['returnurl'] = $returnurl;
        }
        redirect(new moodle_url('/local/wub_auth/login.php', $loginparams));
    }
}

$error = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Session key required
    require_sesskey();
    $agree = optional_param('agree', 0, PARAM_INT);

    if ($agree === 1) {
        $policyservice->record_acceptance($role, $userid);
        if ($userid > 0) {
            $target = $sessionservice->get_safe_redirect_url($returnurl, '/my/');
            redirect($target);
        } else {
            $loginparams = ['role' => $role];
            if (!empty($returnurl)) {
                $loginparams['returnurl'] = $returnurl;
            }
            redirect(new moodle_url('/local/wub_auth/login.php', $loginparams));
        }
    } else {
        $error = 'You must review the policies and check the confirmation box before proceeding.';
    }
}

// Page setup
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/wub_auth/policy.php', array_filter(['role' => $role, 'returnurl' => $returnurl])));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('policy_header', 'local_wub_auth'));
$PAGE->set_heading(get_string('policy_header', 'local_wub_auth'));

$badgekey = 'policy_role_badge_' . $role;
$rolebadgelabel = get_string($badgekey, 'local_wub_auth');

$templatedata = [
    'role' => $role,
    'returnurl' => $returnurl,
    'sesskey' => sesskey(),
    'role_badge_label' => $rolebadgelabel,
    'policy_validity_text' => get_string('policy_validity_notice', 'local_wub_auth', [
        'version' => $policyservice->get_version(),
        'days' => (int)get_config('local_wub_auth', 'policy_expiry_days') ?: 30,
    ]),
    'form_action' => (new moodle_url('/local/wub_auth/policy.php'))->out(false),
    'cancel_url' => $userid > 0 ? (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false) : (new moodle_url('/local/wub_auth/landing.php'))->out(false),
    'has_error' => !empty($error),
    'error_message' => $error,
    'categories' => $policyservice->get_categories_and_policies(),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_wub_auth/policy_page', $templatedata);
echo $OUTPUT->footer();
