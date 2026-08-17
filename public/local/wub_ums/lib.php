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
 * Public API Library for local_wub_ums plugin.
 *
 * @package    local_wub_ums
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_wub_ums\api_client;

/**
 * Get instance of UMS API Client.
 *
 * @return api_client
 */
function wub_ums_get_api_client(): api_client {
    return new api_client();
}

/**
 * Normalize student username into standard Moodle format (pure digits e.g. 0525641925).
 * Excuses guest, admin_khairul, root, and non-student administrative usernames.
 *
 * @param string $username
 * @return string
 */
function wub_ums_normalize_username(string $username): string {
    $clean = trim($username);
    if (empty($clean)) {
        return '';
    }

    $base = explode('@', $clean)[0];
    $digits = preg_replace('/[^0-9]/', '', $base);

    if (empty($digits)) {
        return $clean;
    }

    return $digits;
}

/**
 * Extract clean numeric student ID from username or email.
 *
 * @param string $username
 * @return string
 */
function wub_ums_extract_student_id(string $username): string {
    $clean = trim($username);
    if (empty($clean)) {
        return '';
    }

    $base = explode('@', $clean)[0];
    $digits = preg_replace('/[^0-9]/', '', $base);

    return !empty($digits) ? $digits : $base;
}

/**
 * Verify student payment dues status with local_wub_auth_penalty or fallback.
 *
 * @param int $userid Moodle user ID.
 * @return array ['allowed' => bool, 'reason' => string, 'status' => string, 'due' => float]
 */
function wub_ums_check_student_due_status(int $userid): array {
    global $CFG;
    if (file_exists($CFG->dirroot . '/local/wub_auth_penalty/lib.php')) {
        require_once($CFG->dirroot . '/local/wub_auth_penalty/lib.php');
    }
    if (function_exists('wub_auth_penalty_check_student_due_status')) {
        return wub_auth_penalty_check_student_due_status($userid);
    }
    return ['allowed' => true, 'reason' => 'Auth penalty service unavailable', 'status' => 'Active', 'due' => 0.0];
}
