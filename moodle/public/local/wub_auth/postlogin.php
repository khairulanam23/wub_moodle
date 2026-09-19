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
 * Post-Login Authorization and Policy Gate.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

global $USER, $SESSION;

$clearanceservice = new \local_wub_auth\service\financial_clearance_service();
$policyservice = new \local_wub_auth\service\policy_service();
$roleresolver = new \local_wub_auth\service\role_resolver();
$sessionservice = new \local_wub_auth\service\session_service();

$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
if (empty($returnurl) && !empty($SESSION->wub_return_url)) {
    $returnurl = $SESSION->wub_return_url;
    unset($SESSION->wub_return_url);
}

$targeturl = $sessionservice->get_safe_redirect_url($returnurl, '/my/');

// 1. Financial Clearance Check
$clearance = $clearanceservice->check_clearance($USER);
if (!$clearance->is_allowed()) {
    $SESSION->wub_restricted_clearance = $clearance->to_array();
    $SESSION->wub_restricted_user = (object)[
        'id' => $USER->id,
        'fullname' => fullname($USER),
        'username' => $USER->username,
        'idnumber' => $USER->idnumber,
    ];
    redirect(new moodle_url('/local/wub_auth/restricted.php'));
}

// 2. Policy Acceptance Check
if ($policyservice->is_enabled()) {
    $role = $roleresolver->resolve_primary_role($USER);
    if (!$policyservice->is_policy_accepted($role, (int)$USER->id)) {
        redirect(new moodle_url('/local/wub_auth/policy.php', [
            'role' => $role,
            'returnurl' => $targeturl->out(false),
        ]));
    }
}

redirect($targeturl);
