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
 * Authentication entry point for WUB Landing.
 *
 * Captures user role selection, checks session-based policy agreement,
 * and routes to the role policy or Moodle login flow.
 *
 * @package    local_wub_landing
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
if (file_exists($CFG->dirroot . '/local/wub_policy/lib.php')) {
    require_once($CFG->dirroot . '/local/wub_policy/lib.php');
}

// Get the intended role and optional return URL from the URL parameter.
$role = required_param('role', PARAM_ALPHA);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

// Validate the role parameter.
$validroles = ['student', 'teacher', 'admin'];
if (!in_array($role, $validroles)) {
    throw new \moodle_exception('invalidrole', 'local_wub_landing');
}

// If the user is already authenticated, go directly to post-login verification or return URL.
if (isloggedin() && !isguestuser()) {
    $SESSION->wub_intended_role = $role;
    if (!empty($returnurl)) {
        $SESSION->wub_return_url = $returnurl;
    }
    redirect(new moodle_url('/local/wub_landing/postlogin.php'));
}

// Store the intended role and return URL in the session.
$SESSION->wub_intended_role = $role;
if (!empty($returnurl)) {
    $SESSION->wub_return_url = $returnurl;
    $SESSION->wantsurl = (new moodle_url('/local/wub_landing/postlogin.php'))->out(false);
} else {
    $SESSION->wantsurl = (new moodle_url('/local/wub_landing/postlogin.php'))->out(false);
}

// Check if policy for this role has been accepted in the current session.
if (function_exists('wub_policy_is_accepted') && !wub_policy_is_accepted($role)) {
    $policyparams = ['role' => $role];
    if (!empty($returnurl)) {
        $policyparams['returnurl'] = $returnurl;
    }
    redirect(new moodle_url('/local/wub_policy/index.php', $policyparams));
}

// Redirect to custom WUB login page.
$loginparams = ['role' => $role];
if (!empty($returnurl)) {
    $loginparams['returnurl'] = $returnurl;
}
redirect(new moodle_url('/local/wub_login/index.php', $loginparams));
