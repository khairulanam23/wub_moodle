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

require_once('../../config.php');
if (file_exists($CFG->dirroot . '/local/wub_policy/lib.php')) {
    require_once($CFG->dirroot . '/local/wub_policy/lib.php');
}

// Get the intended role from the URL parameter.
$role = required_param('role', PARAM_ALPHA);

// Validate the role parameter.
$validroles = ['student', 'teacher', 'admin'];
if (!in_array($role, $validroles)) {
    throw new \moodle_exception('invalidrole', 'local_wub_landing');
}

// If the user is already authenticated, go directly to post-login verification.
if (isloggedin() && !isguestuser()) {
    $SESSION->wub_intended_role = $role;
    redirect(new moodle_url('/local/wub_landing/postlogin.php'));
}

// Store the intended role in the session.
$SESSION->wub_intended_role = $role;

// Set the wantsurl so Moodle redirects to our post-login handler after authentication.
$SESSION->wantsurl = (new moodle_url('/local/wub_landing/postlogin.php'))->out(false);

// Check if policy for this role has been accepted in the current session.
if (function_exists('wub_policy_is_accepted') && !wub_policy_is_accepted($role)) {
    redirect(new moodle_url('/local/wub_policy/index.php', ['role' => $role]));
}

// Redirect to custom WUB login page.
redirect(new moodle_url('/local/wub_login/index.php', ['role' => $role]));
