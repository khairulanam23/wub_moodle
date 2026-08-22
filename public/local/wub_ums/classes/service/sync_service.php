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

namespace local_wub_ums\service;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use local_wub_ums\api_client;
use local_wub_ums\repository\student_repository;

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

    /** @var student_repository Student data repository. */
    protected student_repository $studentRepo;

    /**
     * Constructor.
     *
     * @param api_client|null $apiClient
     * @param student_repository|null $studentRepo
     */
    public function __construct(?api_client $apiClient = null, ?student_repository $studentRepo = null) {
        $this->apiClient = $apiClient ?? new api_client();
        $this->studentRepo = $studentRepo ?? new student_repository();
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
        global $CFG;

        $stdObj = (object)$studentObj;

        // Extract and normalize student identifier to pure digits.
        $stud_id = $stdObj->stud_id ?? $stdObj->student_id ?? $stdObj->regId ?? $stdObj->registration_no ?? '';
        $rawUsername = strtolower(trim(
            $stdObj->username ?? ($stud_id ? str_replace(['/', ' ', '-'], ['', '', ''], $stud_id) : '')
        ));

        // Strip any @domain suffix and extract only digits for clean student ID.
        $baseUsername = explode('@', $rawUsername)[0];
        $digits = preg_replace('/[^0-9]/', '', $baseUsername);
        $cleanId = !empty($digits) ? $digits : $baseUsername;

        if (empty($cleanId)) {
            return null;
        }

        // Standardized Moodle username: pure numeric digits
        $moodleUsername = $cleanId;
        $email = $cleanId . '@student.wub.edu.bd';

        // Extract and sanitize name fields.
        $fullName = trim($stdObj->full_name ?? $stdObj->name ?? $stdObj->student_name ?? '');

        // If name is absent, attempt detail API fallback using cleanId.
        if (empty($fullName)) {
            $details = $this->apiClient->get_student_details($cleanId);
            if ($details) {
                $detailObj = (object)$details;
                $fullName = trim($detailObj->full_name ?? $detailObj->name ?? $detailObj->student_name ?? '');
                if (empty($stud_id)) {
                    $stud_id = $detailObj->stud_id ?? $detailObj->student_id ?? '';
                }
            }
        }

        if (!empty($fullName)) {
            $nameParts = preg_split('/\s+/', $fullName, 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? '.';
        } else {
            $firstName = trim($stdObj->firstname ?? $stdObj->first_name ?? 'Student');
            $lastName = trim($stdObj->lastname ?? $stdObj->last_name ?? $cleanId);
            if (empty($firstName)) {
                $firstName = 'Student';
            }
            if (empty($lastName)) {
                $lastName = $cleanId;
            }
        }

        // UMS-owned metadata fields.
        $userProgram = trim($stdObj->program_name ?? $stdObj->program_id ?? $programId ?? '');
        $userBatch = trim($stdObj->mother_batch ?? $stdObj->batch_id ?? $batchId ?? '');

        // Search for existing user to prevent duplicate accounts via student repository.
        $existing = $this->studentRepo->find_existing_student($moodleUsername, $email, $cleanId);

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

            $this->studentRepo->update_student_user($updateUser);
            $userId = $existing->id;

        } else {
            // ═══════════════════════════════════════════════════════════
            // NEW USER — Create via Moodle's user API with initial
            // password set to the student ID (hashed).
            // ═══════════════════════════════════════════════════════════
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

            $userId = $this->studentRepo->create_student_user($newUser, $moodleUsername);
            if (!$userId) {
                return null;
            }
        }

        // Maintain local tracking record in {enrol_ums_user} table via repository.
        $this->studentRepo->sync_ums_tracking($userId, $userProgram, $userBatch);

        return $this->studentRepo->find_by_id($userId);
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
