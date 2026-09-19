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
 * Standard Moodle callbacks and public library for local_wub_auth.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Moodle Core Callback: executed after require_login() on every authenticated page.
 *
 * Intercepts restricted students and unaccepted policy states across all Moodle
 * courses, activities, and dashboard pages.
 *
 * @param mixed $courseorid Course object or ID.
 * @param bool $autologinguest Autologin guest flag.
 * @param mixed $cm Course module object or null.
 * @param bool $setwantsurltome Wants URL flag.
 * @param bool $preventredirect Prevent redirect flag.
 */
function local_wub_auth_after_require_login($courseorid, $autologinguest, $cm, $setwantsurltome, $preventredirect) {
    global $USER, $PAGE, $SCRIPT;

    // Do not intercept CLI scripts, web services, or AJAX calls
    if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
        return;
    }
    if (defined('WS_SERVER') && WS_SERVER) {
        return;
    }
    if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
        return;
    }

    // Do not intercept guest users or unauthenticated sessions
    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Administrators are exempt from institutional restrictions
    if (is_siteadmin($USER)) {
        return;
    }

    // Whitelist: Prevent redirect loop on our own access-control pages and logout
    $currentscript = (string)($SCRIPT ?? '');
    $exemptscripts = [
        '/local/wub_auth/restricted.php',
        '/local/wub_auth/policy.php',
        '/local/wub_auth/login.php',
        '/local/wub_auth/postlogin.php',
        '/login/logout.php',
    ];

    foreach ($exemptscripts as $exempt) {
        if (str_ends_with($currentscript, $exempt)) {
            return;
        }
    }

    // 1. Institutional Financial Clearance Check
    $clearanceservice = new \local_wub_auth\service\financial_clearance_service();
    $clearance = $clearanceservice->check_clearance($USER);

    if (!$clearance->is_allowed()) {
        if (!$preventredirect) {
            redirect(new moodle_url('/local/wub_auth/restricted.php'));
        }
        return;
    }

    // 2. Policy Acknowledgement Check
    $policyservice = new \local_wub_auth\service\policy_service();
    if ($policyservice->is_enabled()) {
        $roleresolver = new \local_wub_auth\service\role_resolver();
        $role = $roleresolver->resolve_primary_role($USER);

        if (!$policyservice->is_policy_accepted($role, (int)$USER->id)) {
            if (!$preventredirect) {
                $returnurl = $PAGE->url ? $PAGE->url->out(false) : '';
                redirect(new moodle_url('/local/wub_auth/policy.php', array_filter(['role' => $role, 'returnurl' => $returnurl])));
            }
        }
    }
}

/**
 * Public helper function for external plugins to query student due status.
 *
 * @param int $userid
 * @return array
 */
function wub_auth_check_student_due_status(int $userid): array {
    global $DB;
    $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
    if (!$user) {
        return ['allowed' => false, 'reason' => 'User not found', 'due' => 0.0];
    }

    $clearanceservice = new \local_wub_auth\service\financial_clearance_service();
    $clearance = $clearanceservice->check_clearance($user);

    return [
        'allowed' => $clearance->is_allowed(),
        'state' => $clearance->get_state(),
        'net_due' => $clearance->get_net_due(),
        'raw_due' => $clearance->get_raw_due(),
        'reason' => $clearance->get_reason(),
        'cached' => $clearance->is_cached(),
    ];
}
