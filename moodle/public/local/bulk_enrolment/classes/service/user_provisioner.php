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

namespace local_bulk_enrolment\service;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use core_text;
use core_user;
use moodle_exception;

global $CFG;
require_once($CFG->dirroot . '/user/lib.php');

/**
 * Dedicated authoritative service for UMS student account provisioning and synchronization.
 *
 * CANONICAL INSTITUTIONAL MAPPINGS:
 * - Program Name        => mdl_user.department
 * - Batch Name/Title    => mdl_user.institution
 * - Student ID / Reg ID => mdl_user.idnumber
 * - Clean Numeric ID    => mdl_user.username
 * - Institutional Email => mdl_user.email ({clean_id}@student.wub.edu.bd)
 * - Initial Password    => {clean_id} (hashed via Moodle core auth plugin; NEVER stored in plaintext)
 *
 * CRITICAL ARCHITECTURAL CONFORMANCE:
 * - Uses Moodle core user_create_user() and user_update_user() exclusively.
 * - Never directly inserts into mdl_user or core tables.
 * - Does not invent fake synthetic IDs like 'ums_1234'.
 * - Verifies collisions (username, email, idnumber) before creating accounts.
 * - Protects against duplicate accounts (idempotent).
 * - Leaves course enrolment as a separate operation.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_provisioner {

    /**
     * Check if automated student account creation is enabled.
     *
     * @return bool
     */
    public function is_provisioning_enabled(): bool {
        return true;
    }

    /**
     * Retrieve status summary of the provisioning subsystem.
     *
     * @return array
     */
    public function get_subsystem_status(): array {
        return [
            'status' => 'ACTIVE',
            'enabled' => true,
            'reason' => 'Authoritative student account provisioning is enabled via native Moodle user API.',
            'canonical_mappings' => [
                'department' => 'UMS Program Title',
                'institution' => 'UMS Batch Name',
                'idnumber' => 'UMS Official Registration ID',
                'username' => 'Institutional Numeric Student ID',
                'email' => '{student_id}@student.wub.edu.bd',
            ],
            'custom_tables_created' => 0,
        ];
    }

    /**
     * Deterministically convert UMS full_name into Moodle firstname and lastname.
     *
     * Handles single names, multi-word names, and honorifics deterministically.
     *
     * @param string $fullName Raw full name from UMS
     * @param string $fallbackUsername Fallback name if full_name is blank
     * @return array ['firstname' => string, 'lastname' => string]
     */
    public static function split_full_name(string $fullName, string $fallbackUsername = ''): array {
        $clean = trim(preg_replace('/\s+/', ' ', $fullName));
        if ($clean === '') {
            $clean = $fallbackUsername !== '' ? $fallbackUsername : 'Student';
        }

        $parts = explode(' ', $clean);
        if (count($parts) === 1) {
            return [
                'firstname' => $parts[0],
                'lastname' => '.', // Moodle standard for single-name accounts.
            ];
        }

        $lastName = array_pop($parts);
        $firstName = implode(' ', $parts);

        return [
            'firstname' => $firstName,
            'lastname' => $lastName,
        ];
    }

    /**
     * Generate canonical institutional student email address.
     *
     * @param string $username Student numeric username
     * @return string Validated institutional email
     */
    public static function get_student_email(string $username): string {
        $domain = get_config('local_bulk_enrolment', 'email_domain');
        if (empty($domain)) {
            $domain = student_identity_service::EMAIL_DOMAIN;
        }
        $domain = ltrim(trim($domain), '@');
        return core_text::strtolower(trim($username)) . '@' . $domain;
    }

    /**
     * Check for identity collisions (username, email, or idnumber) across existing Moodle users.
     *
     * @param string $username
     * @param string $email
     * @param string $regId
     * @param int $excludeUserId Optional user ID to exclude (for updates)
     * @return string|null Null if clean, error explanation if collision detected
     */
    public function check_identity_collision(string $username, string $email, string $regId = '', int $excludeUserId = 0): ?string {
        global $DB, $CFG;

        $mnetHostId = (int)$CFG->mnet_localhost_id;
        $cleanUsername = core_text::strtolower(trim($username));
        $cleanEmail = core_text::strtolower(trim($email));
        $cleanRegId = trim($regId);

        // 1. Check email collision with a different user.
        if ($cleanEmail !== '') {
            $sql = "SELECT id, username FROM {user}
                     WHERE deleted = 0 AND mnethostid = :mnet AND LOWER(email) = :email";
            $params = ['mnet' => $mnetHostId, 'email' => $cleanEmail];
            if ($excludeUserId > 0) {
                $sql .= " AND id != :exid";
                $params['exid'] = $excludeUserId;
            }
            $existing = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
            if ($existing && strtolower($existing->username) !== $cleanUsername) {
                return "Email address '{$cleanEmail}' is already registered to user '{$existing->username}' (ID: {$existing->id}).";
            }
        }

        // 2. Check idnumber collision with a different user.
        if ($cleanRegId !== '') {
            $sql = "SELECT id, username FROM {user}
                     WHERE deleted = 0 AND mnethostid = :mnet AND idnumber = :idnumber";
            $params = ['mnet' => $mnetHostId, 'idnumber' => $cleanRegId];
            if ($excludeUserId > 0) {
                $sql .= " AND id != :exid";
                $params['exid'] = $excludeUserId;
            }
            $existing = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
            if ($existing && strtolower($existing->username) !== $cleanUsername) {
                return "Registration ID '{$cleanRegId}' is already assigned to user '{$existing->username}' (ID: {$existing->id}).";
            }
        }

        return null;
    }

    /**
     * Create a new Moodle student account from authoritative UMS student record.
     *
     * @param array $record UMS student comparison record
     * @return array ['status' => 'CREATED'|'BLOCKED'|'FAILED', 'userid' => int, 'reason' => string]
     */
    public function create_student_account(array $record): array {
        global $DB, $CFG;

        $rawUsername = trim($record['username'] ?? '');
        if ($rawUsername === '') {
            return ['status' => 'FAILED', 'userid' => 0, 'reason' => 'Username cannot be blank.'];
        }

        $cleanUsername = core_text::strtolower($rawUsername);
        if ($cleanUsername !== core_user::clean_field($cleanUsername, 'username')) {
            return ['status' => 'FAILED', 'userid' => 0, 'reason' => "Invalid characters in username '{$rawUsername}'."];
        }

        // Determine email.
        $email = !empty($record['email']) ? trim($record['email']) : self::get_student_email($cleanUsername);
        if (!validate_email($email)) {
            return ['status' => 'FAILED', 'userid' => 0, 'reason' => "Invalid email format '{$email}'."];
        }

        $regId = !empty($record['reg_id']) ? trim($record['reg_id']) : '';

        // If user already exists in Moodle with exact username, delegate to safe update.
        $existing = $DB->get_record('user', ['username' => $cleanUsername, 'deleted' => 0, 'mnethostid' => $CFG->mnet_localhost_id], '*', IGNORE_MULTIPLE);
        if ($existing) {
            $upd = $this->update_student_account((int)$existing->id, $record);
            return [
                'status' => ($upd['status'] === 'UPDATED') ? 'UPDATED' : 'ALREADY_SYNCED',
                'userid' => (int)$existing->id,
                'reason' => $upd['reason'],
            ];
        }

        // Check for collisions with other accounts.
        $collision = $this->check_identity_collision($cleanUsername, $email, $regId);
        if ($collision !== null) {
            return ['status' => 'BLOCKED', 'userid' => 0, 'reason' => $collision];
        }

        // Parse names deterministically.
        $fullName = !empty($record['full_name']) ? trim($record['full_name']) : '';
        $names = self::split_full_name($fullName, $cleanUsername);

        try {
            $user = new stdClass();
            $user->username = $cleanUsername;
            $user->email = $email;
            $user->firstname = $names['firstname'];
            $user->lastname = $names['lastname'];
            $user->idnumber = $regId;
            $user->department = !empty($record['program_name']) ? trim($record['program_name']) : '';
            $user->institution = !empty($record['batch_name']) ? trim($record['batch_name']) : '';
            $user->auth = 'manual';
            $user->mnethostid = (int)$CFG->mnet_localhost_id;
            $user->confirmed = 1;
            $user->suspended = 0;
            $user->deleted = 0;

            // 1. Create Moodle user via supported core API without password check.
            $newUserId = user_create_user($user, false, true);

            // 2. Hash and store the initial password securely via Moodle's native auth plugin.
            // NEVER stored or exposed in plaintext.
            $newuser = $DB->get_record('user', ['id' => $newUserId], '*', MUST_EXIST);
            $authplugin = get_auth_plugin($newuser->auth);
            $authplugin->user_update_password($newuser, $cleanUsername);

            return [
                'status' => 'CREATED',
                'userid' => (int)$newUserId,
                'reason' => 'Account created successfully with initial credentials.',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'FAILED',
                'userid' => 0,
                'reason' => 'User creation error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Safely update institutional metadata for an existing Moodle student account.
     *
     * @param int $moodleUserId
     * @param array $record
     * @return array ['status' => 'UPDATED'|'ALREADY_SYNCED'|'FAILED', 'reason' => string]
     */
    public function update_student_account(int $moodleUserId, array $record): array {
        global $DB;

        if ($moodleUserId <= 0) {
            return ['status' => 'FAILED', 'reason' => 'Invalid Moodle user ID.'];
        }

        try {
            $user = $DB->get_record('user', ['id' => $moodleUserId, 'deleted' => 0], '*', MUST_EXIST);

            $updateObj = new stdClass();
            $updateObj->id = $user->id;
            $hasChange = false;

            if (!empty($record['program_name']) && trim((string)$user->department) !== trim($record['program_name'])) {
                $updateObj->department = trim($record['program_name']);
                $hasChange = true;
            }

            if (!empty($record['batch_name']) && trim((string)$user->institution) !== trim($record['batch_name'])) {
                $updateObj->institution = trim($record['batch_name']);
                $hasChange = true;
            }

            if (!empty($record['reg_id']) && trim((string)$user->idnumber) !== trim($record['reg_id'])) {
                // Ensure no collision before updating idnumber.
                $col = $this->check_identity_collision($user->username, $user->email, $record['reg_id'], (int)$user->id);
                if ($col !== null) {
                    return ['status' => 'BLOCKED', 'reason' => $col];
                }
                $updateObj->idnumber = trim($record['reg_id']);
                $hasChange = true;
            }

            // If user's firstname or lastname is blank in Moodle, populate from UMS.
            if (!empty($record['full_name'])) {
                $names = self::split_full_name($record['full_name'], $user->username);
                if (empty($user->firstname) && !empty($names['firstname'])) {
                    $updateObj->firstname = $names['firstname'];
                    $hasChange = true;
                }
                if (empty($user->lastname) && !empty($names['lastname'])) {
                    $updateObj->lastname = $names['lastname'];
                    $hasChange = true;
                }
            }

            if ($hasChange) {
                user_update_user($updateObj, false);
                return ['status' => 'UPDATED', 'reason' => 'Metadata synchronized successfully.'];
            }

            return ['status' => 'ALREADY_SYNCED', 'reason' => 'Already up to date.'];
        } catch (\Throwable $e) {
            return ['status' => 'FAILED', 'reason' => 'Update error: ' . $e->getMessage()];
        }
    }
}
