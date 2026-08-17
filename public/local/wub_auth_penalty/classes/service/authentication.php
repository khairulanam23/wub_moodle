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

namespace local_wub_auth_penalty\service;

defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Student Authentication Service.
 *
 * Implements the multi-tiered authentication workflow:
 * 1. Moodle user lookup & password verification (`validate_internal_user_password`).
 * 2. Fallback verification against WUB Student Portal API (`checkStudentPortalPassword`).
 * 3. Automatic Moodle password synchronization upon successful Student Portal authentication.
 *
 * @package    local_wub_auth_penalty
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class authentication {

    /**
     * Instance of Student API Client.
     * @var student_api
     */
    protected student_api $apiClient;

    /**
     * Constructor.
     *
     * @param student_api|null $apiClient
     */
    public function __construct(?student_api $apiClient = null) {
        $this->apiClient = $apiClient ?? new student_api();
    }

    /**
     * Execute user authentication according to institutional rules.
     *
     * Flow:
     * 1. Retrieve Moodle user record by clean digit username or full email.
     * 2. Verify internal Moodle password.
     * 3. If internal password fails, fallback to Student Portal API authentication.
     * 4. Update internal Moodle password on successful Student Portal API authentication.
     *
     * @param string $username Moodle or Student Portal username.
     * @param string $password User password.
     * @return stdClass|false Moodle user object on success, or false on failure.
     */
    public function user_login(string $username, string $password) {
        global $DB;

        $rawUsername = strtolower(trim($username));
        if (empty($rawUsername) || empty($password)) {
            return false;
        }

        $shortUsername = explode('@', $rawUsername)[0];
        $digits = preg_replace('/[^0-9]/', '', $shortUsername);
        $moodleUsername = !empty($digits) ? $digits : $shortUsername;

        // Find existing Moodle user record by normalized digit username or email
        $user = $DB->get_record_select('user', 'deleted = 0 AND (username = :u1 OR username = :u2 OR email = :e1 OR email = :e2)', [
            'u1' => $moodleUsername,
            'u2' => $moodleUsername . '@student.wub.edu.bd',
            'e1' => $moodleUsername . '@student.wub.edu.bd',
            'e2' => $rawUsername
        ]);

        if (!$user) {
            return false;
        }

        // 1. Check Moodle internal password first
        require_once(__DIR__ . '/../../../../user/lib.php');
        if (!empty($user->password) && $user->password !== 'not cached' && validate_internal_user_password($user, $password)) {
            return $user;
        }

        // 2. Fallback to Student Portal API password check
        if ($this->checkStudentPortalPassword($moodleUsername, $password)) {
            // Synchronize Moodle internal password so future logins can authenticate locally
            update_internal_user_password($user, $password);
            return $user;
        }

        return false;
    }

    /**
     * Verify student password directly against the Student Portal API.
     *
     * @param string $studentUsername Base student registration number / username.
     * @param string $password Student portal password.
     * @return bool True if Student Portal API confirms credentials.
     */
    public function checkStudentPortalPassword(string $studentUsername, string $password): bool {
        if (empty($studentUsername) || empty($password)) {
            return false;
        }

        $res = $this->apiClient->student_login($studentUsername, $password);
        return !empty($res['success']) && $res['success'] === true;
    }
}
