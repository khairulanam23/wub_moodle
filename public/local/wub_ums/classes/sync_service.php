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

namespace local_wub_ums;

defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Canonical service for synchronizing UMS student records into local Moodle database.
 *
 * This is the single source of truth for student account creation and update.
 * All other code paths (enrolhelper, sync_cli, etc.) MUST delegate to this service.
 *
 * Field ownership model:
 * ─────────────────────────────────────────────────────────────────────
 * UMS-owned (updated during sync):
 *   - username, email, firstname, lastname, department, institution, idnumber
 *
 * Moodle-owned (NEVER overwritten by sync):
 *   - password, auth, confirmed, lang, mnet host, preferences
 *
 * Admin-controlled (NEVER overwritten by sync):
 *   - special_premission, special_premission_expiry, roles, enrolments
 * ─────────────────────────────────────────────────────────────────────
 *
 * Account mapping:
 *   Student ID:  <student_id>
 *   Username:    <student_id>               (pure digits)
 *   Email:       <student_id>@student.wub.edu.bd
 *   Password:    <student_id>               (initial, hashed — only set on creation)
 *
 * @package    local_wub_ums
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_service {

    /** @var api_client UMS API Client. */
    protected api_client $apiClient;

    /**
     * Constructor.
     *
     * @param api_client|null $apiClient
     */
    public function __construct(?api_client $apiClient = null) {
        $this->apiClient = $apiClient ?? new api_client();
    }

    /**
     * Create or update a student user account in Moodle from UMS data.
     *
     * For NEW students:
     *   - Creates Moodle user via user_create_user() (proper Moodle API)
     *   - Sets initial password to the student ID (hashed)
     *   - Sets special_premission = 0, special_premission_expiry = 0
     *
     * For EXISTING students:
     *   - Updates ONLY UMS-owned fields (name, email, department, institution)
     *   - NEVER overwrites password, special_premission, or special_premission_expiry
     *
     * @param object|array $studentObj Raw UMS student payload object.
     * @param string|null $programId Optional program identifier.
     * @param string|null $batchId Optional batch identifier.
     * @return stdClass|null Saved Moodle user record on success, null on invalid input.
     */
    public function sync_student($studentObj, ?string $programId = null, ?string $batchId = null): ?stdClass {
        global $DB, $CFG;

        $stdObj = (object)$studentObj;

        // Extract and normalize student identifier to pure digits.
        $stud_id = $stdObj->stud_id ?? $stdObj->student_id ?? $stdObj->regId ?? $stdObj->registration_no ?? '';
        $rawUsername = strtolower(trim(
            $stdObj->username ?? ($stud_id ? str_replace(['/', ' ', '-'], ['', '', ''], $stud_id) : '')
        ));
        if (empty($rawUsername)) {
            return null;
        }

        // Strip any @domain suffix and extract only digits for clean student ID.
        $baseUsername = explode('@', $rawUsername)[0];
        $digits = preg_replace('/[^0-9]/', '', $baseUsername);
        $cleanId = !empty($digits) ? $digits : $baseUsername;

        // Account mapping: username = pure digits, email = digits@student.wub.edu.bd
        $moodleUsername = $cleanId;
        $email = $cleanId . '@student.wub.edu.bd';

        // Parse full name into first/last components with UMS API lookup fallback.
        $fullName = trim($stdObj->full_name ?? $stdObj->name ?? $stdObj->student_name ?? '');
        $firstName = '';
        $lastName = '';

        if (!empty($stdObj->firstname) && !empty($stdObj->lastname) && strtolower(trim($stdObj->firstname)) !== 'student') {
            $firstName = trim($stdObj->firstname);
            $lastName = trim($stdObj->lastname);
            if (empty($fullName)) {
                $fullName = trim($firstName . ' ' . $lastName);
            }
        }

        // If full_name is still missing or generic, fetch full details directly from UMS API using student ID
        if (empty($fullName) || strtolower($firstName) === 'student') {
            $umsDetails = $this->apiClient->get('https://api.e-dhrubo.com/students/email_number_wise_student_details/' . urlencode($cleanId));
            if (!empty($umsDetails)) {
                $detailObj = is_array($umsDetails) ? ((object)($umsDetails[0] ?? [])) : (object)$umsDetails;
                if (!empty($detailObj->full_name)) {
                    $fullName = trim($detailObj->full_name);
                }
            }
        }

        if (!empty($fullName)) {
            $parts = array_values(array_filter(explode(' ', $fullName)));
            if (count($parts) >= 2) {
                $lastName = array_pop($parts);
                $firstName = implode(' ', $parts);
            } else if (count($parts) === 1) {
                $firstName = $parts[0];
                $lastName = $cleanId;
            }
        }

        if (empty($firstName)) {
            $firstName = 'Student';
        }
        if (empty($lastName)) {
            $lastName = $cleanId;
        }

        // UMS-owned metadata fields.
        $userProgram = trim($stdObj->program_name ?? $stdObj->program_id ?? $programId ?? '');
        $userBatch = trim($stdObj->mother_batch ?? $stdObj->batch_id ?? $batchId ?? '');

        // Search for existing user to prevent duplicate accounts.
        // Check by: exact username match, old email-as-username format, current email, or legacy email formats.
        $existing = $DB->get_record_select(
            'user',
            'deleted = 0 AND (username = :u1 OR username = :u2 OR email = :e1 OR email = :e2)',
            [
                'u1' => $moodleUsername,
                'u2' => $cleanId . '@student.wub.edu.bd',
                'e1' => $email,
                'e2' => $cleanId . '@student.wub.ac.bd',
            ]
        );

        if ($existing) {
            // ═══════════════════════════════════════════════════════════
            // EXISTING USER — Update ONLY UMS-owned fields.
            // Password, special_premission, special_premission_expiry
            // are NEVER touched here.
            // ═══════════════════════════════════════════════════════════
            $updateUser = new stdClass();
            $updateUser->id = $existing->id;
            $updateUser->username = $moodleUsername;
            $updateUser->email = $email;
            $updateUser->firstname = $firstName;
            $updateUser->lastname = $lastName;

            if (!empty($userProgram)) {
                $updateUser->department = $userProgram;
            }
            if (!empty($userBatch)) {
                $updateUser->institution = $userBatch;
            }
            if (!empty($stud_id)) {
                $updateUser->idnumber = $stud_id;
            }

            $DB->update_record('user', $updateUser);
            $userId = $existing->id;

        } else {
            // ═══════════════════════════════════════════════════════════
            // NEW USER — Create via Moodle's user API with initial
            // password set to the student ID (hashed).
            // ═══════════════════════════════════════════════════════════
            require_once($CFG->dirroot . '/user/lib.php');

            $newUser = new stdClass();
            $newUser->username = $moodleUsername;
            $newUser->email = $email;
            $newUser->firstname = $firstName;
            $newUser->lastname = $lastName;
            $newUser->auth = 'manual';
            $newUser->confirmed = 1;
            $newUser->mnethostid = $CFG->mnet_localhost_id;
            $newUser->department = $userProgram;
            $newUser->institution = $userBatch;
            $newUser->idnumber = $stud_id ?: $cleanId;
            $newUser->special_premission = 0;
            $newUser->special_premission_expiry = 0;
            $newUser->lang = 'en';
            $newUser->timecreated = time();
            $newUser->timemodified = time();

            try {
                $userId = user_create_user($newUser, false, false);
                $newUser->id = $userId;
                // Set initial password to the student ID using Moodle's password API.
                update_internal_user_password($newUser, $moodleUsername);
            } catch (\Exception $e) {
                // Race condition: if user was created by a parallel process, retrieve it.
                $target = $DB->get_record('user', ['username' => $moodleUsername, 'deleted' => 0]);
                if (!$target) {
                    return null;
                }
                $userId = $target->id;
            }
        }

        // Maintain local tracking record in {enrol_ums_user} table.
        if ($DB->get_manager()->table_exists('enrol_ums_user')) {
            $umsTrack = $DB->get_record('enrol_ums_user', ['user_id' => $userId]);
            if ($umsTrack) {
                $trackUpdate = new stdClass();
                $trackUpdate->id = $umsTrack->id;
                if (!empty($userProgram)) {
                    $trackUpdate->program_id = $userProgram;
                }
                if (!empty($userBatch)) {
                    $trackUpdate->batch_id = $userBatch;
                }
                $DB->update_record('enrol_ums_user', $trackUpdate);
            } else {
                $trackNew = new stdClass();
                $trackNew->user_id = $userId;
                $trackNew->program_id = $userProgram;
                $trackNew->batch_id = $userBatch;
                $trackNew->department_id = '0';
                $trackNew->timecreated = time();
                $DB->insert_record('enrol_ums_user', $trackNew);
            }
        }

        return $DB->get_record('user', ['id' => $userId]);
    }

    /**
     * Backward compatibility alias for sync_student.
     *
     * @param object|array $studentObj
     * @param string|null $programId
     * @param string|null $batchId
     * @return stdClass|null
     */
    public function sync_or_create_student_user($studentObj, ?string $programId = null, ?string $batchId = null): ?stdClass {
        return $this->sync_student($studentObj, $programId, $batchId);
    }
}
