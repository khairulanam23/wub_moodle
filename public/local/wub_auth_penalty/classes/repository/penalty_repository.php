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

namespace local_wub_auth_penalty\repository;

defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Repository for financial penalty checks and access authorization queries.
 *
 * Encapsulates all direct database queries for {user}, {enrol_ums_user},
 * course capabilities, and special permission status.
 *
 * @package    local_wub_auth_penalty
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class penalty_repository {

    /**
     * Retrieve active user record by ID.
     *
     * @param int $userId
     * @return stdClass|null
     */
    public function get_user(int $userId): ?stdClass {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userId, 'deleted' => 0]);
        return $user ?: null;
    }

    /**
     * Retrieve program ID mapped to student in {enrol_ums_user}.
     *
     * @param int $userId
     * @return string|null
     */
    public function get_student_program_id(int $userId): ?string {
        global $DB;
        if (!$DB->get_manager()->table_exists('enrol_ums_user')) {
            return null;
        }
        $record = $DB->get_record('enrol_ums_user', ['user_id' => $userId], 'program_id');
        return ($record && !empty($record->program_id)) ? (string)$record->program_id : null;
    }

    /**
     * Retrieve special permission columns for a user.
     *
     * @param int $userId
     * @return stdClass|null
     */
    public function get_user_special_permission(int $userId): ?stdClass {
        global $DB;

        if ($DB->get_manager()->table_exists('wub_special_permission')) {
            $perm = $DB->get_record('wub_special_permission', ['userid' => $userId]);
            if ($perm) {
                return (object)[
                    'id' => $userId,
                    'special_premission' => (int)$perm->status,
                    'special_premission_expiry' => (int)$perm->timeend,
                ];
            }
        }

        $record = $DB->get_record('user', ['id' => $userId], 'id, special_premission, special_premission_expiry');
        return $record ?: null;
    }

    /**
     * Update special permission status on user record.
     *
     * @param int $userId
     * @param int $enabled 1 for active, 0 for disabled
     * @param int $expiryTimestamp Epoch timestamp
     * @return bool
     */
    public function update_user_special_permission(int $userId, int $enabled, int $expiryTimestamp = 0): bool {
        global $DB;

        if ($DB->get_manager()->table_exists('wub_special_permission')) {
            $existing = $DB->get_record('wub_special_permission', ['userid' => $userId]);
            $now = time();
            if ($existing) {
                $DB->update_record('wub_special_permission', (object)[
                    'id' => $existing->id,
                    'status' => $enabled,
                    'timeend' => $expiryTimestamp,
                    'timemodified' => $now,
                ]);
            } else if ($enabled === 1) {
                $DB->insert_record('wub_special_permission', (object)[
                    'userid' => $userId,
                    'status' => 1,
                    'timestart' => $now,
                    'timeend' => $expiryTimestamp,
                    'grantedby' => 0,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            }
        }

        if ($enabled === 1) {
            return $DB->execute(
                "UPDATE {user} SET special_premission = 1, special_premission_expiry = ? WHERE id = ?",
                [$expiryTimestamp, $userId]
            );
        } else {
            return $DB->execute(
                "UPDATE {user} SET special_premission = 0 WHERE id = ?",
                [$userId]
            );
        }
    }

    /**
     * Check if user possesses teacher/grader capabilities in any enrolled course.
     *
     * @param int $userId
     * @return bool
     */
    public function is_user_teacher_in_any_course(int $userId): bool {
        $courses = enrol_get_users_courses($userId, true, ['id']);
        if (!empty($courses)) {
            foreach ($courses as $c) {
                $ccontext = \context_course::instance($c->id);
                if (
                    has_capability('moodle/course:manageactivities', $ccontext, $userId, false) ||
                    has_capability('moodle/course:viewhiddenactivities', $ccontext, $userId, false)
                ) {
                    return true;
                }
            }
        }
        return false;
    }
}
