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

namespace local_wub_ums\repository;

defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Repository for student account persistence and query operations in Moodle.
 *
 * Encapsulates all direct database queries for {user} and {enrol_ums_user} tables,
 * separating data access from the synchronization business logic.
 *
 * @package    local_wub_ums
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_repository {

    /**
     * Find existing student by username variants, clean ID, or email patterns.
     *
     * @param string $moodleUsername Standardized username (pure digits).
     * @param string $email Primary email address.
     * @param string $cleanId Clean numeric student ID.
     * @return stdClass|null
     */
    public function find_existing_student(string $moodleUsername, string $email, string $cleanId): ?stdClass {
        global $DB;

        $record = $DB->get_record_select(
            'user',
            'deleted = 0 AND (username = :u1 OR username = :u2 OR email = :e1 OR email = :e2)',
            [
                'u1' => $moodleUsername,
                'u2' => $cleanId . '@student.wub.edu.bd',
                'e1' => $email,
                'e2' => $cleanId . '@student.wub.ac.bd',
            ],
            '*',
            IGNORE_MULTIPLE
        );

        return $record ?: null;
    }

    /**
     * Find a user record by ID.
     *
     * @param int $userId
     * @return stdClass|null
     */
    public function find_by_id(int $userId): ?stdClass {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userId, 'deleted' => 0]);
        return $user ?: null;
    }

    /**
     * Find a user record by exact username.
     *
     * @param string $username
     * @return stdClass|null
     */
    public function find_by_username(string $username): ?stdClass {
        global $DB;
        $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
        return $user ?: null;
    }

    /**
     * Update UMS-owned fields on an existing student user record.
     *
     * @param stdClass $updateData Object containing user id and fields to update.
     * @return bool
     */
    public function update_student_user(stdClass $updateData): bool {
        global $DB;
        return $DB->update_record('user', $updateData);
    }

    /**
     * Create a new student user record using Moodle's native user API.
     *
     * @param stdClass $userData User record structure.
     * @param string $initialPassword Raw password string to hash and assign.
     * @return int|null Created user ID on success, null on error.
     */
    public function create_student_user(stdClass $userData, string $initialPassword): ?int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');

        try {
            $userId = user_create_user($userData, false, false);
            $userData->id = $userId;
            update_internal_user_password($userData, $initialPassword);
            return (int)$userId;
        } catch (\Exception $e) {
            // Race condition: if created concurrently, retrieve existing ID
            $existing = $this->find_by_username($userData->username);
            return $existing ? (int)$existing->id : null;
        }
    }

    /**
     * Synchronize local tracking record in {enrol_ums_user} table.
     *
     * @param int $userId Moodle user ID.
     * @param string $programId Program identifier or name.
     * @param string $batchId Batch identifier or name.
     * @param string $departmentId Department identifier.
     * @return bool
     */
    public function sync_ums_tracking(int $userId, string $programId, string $batchId, string $departmentId = '0'): bool {
        global $DB;

        if (!$DB->get_manager()->table_exists('enrol_ums_user')) {
            return false;
        }

        $umsTrack = $DB->get_record('enrol_ums_user', ['user_id' => $userId]);
        if ($umsTrack) {
            $trackUpdate = new stdClass();
            $trackUpdate->id = $umsTrack->id;
            if (!empty($programId)) {
                $trackUpdate->program_id = $programId;
            }
            if (!empty($batchId)) {
                $trackUpdate->batch_id = $batchId;
            }
            return $DB->update_record('enrol_ums_user', $trackUpdate);
        } else {
            $trackNew = new stdClass();
            $trackNew->user_id = $userId;
            $trackNew->program_id = $programId;
            $trackNew->batch_id = $batchId;
            $trackNew->department_id = $departmentId;
            $trackNew->timecreated = time();
            return (bool)$DB->insert_record('enrol_ums_user', $trackNew);
        }
    }

    /**
     * Retrieve UMS tracking record for a user.
     *
     * @param int $userId
     * @return stdClass|null
     */
    public function get_ums_tracking(int $userId): ?stdClass {
        global $DB;
        if (!$DB->get_manager()->table_exists('enrol_ums_user')) {
            return null;
        }
        $record = $DB->get_record('enrol_ums_user', ['user_id' => $userId]);
        return $record ?: null;
    }
}
