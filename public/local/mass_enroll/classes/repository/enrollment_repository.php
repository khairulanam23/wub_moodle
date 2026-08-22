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

namespace local_mass_enroll\repository;

defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Repository for course and enrollment database access.
 *
 * Encapsulates direct database queries for {course}, {enrol}, {user_enrolments},
 * and course categories.
 *
 * @package    local_mass_enroll
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrollment_repository {

    /**
     * Get courses by category ID.
     *
     * @param int $catId Category ID.
     * @return array
     */
    public function get_courses_by_category(int $catId = 0): array {
        global $DB;
        if ($catId > 0) {
            return $DB->get_records('course', ['category' => $catId, 'visible' => 1], 'fullname ASC', 'id, fullname, shortname, idnumber, category');
        }
        return $DB->get_records('course', ['visible' => 1], 'fullname ASC', 'id, fullname, shortname, idnumber, category');
    }

    /**
     * Get course record by ID.
     *
     * @param int $courseId
     * @return stdClass|null
     */
    public function get_course(int $courseId): ?stdClass {
        global $DB;
        $record = $DB->get_record('course', ['id' => $courseId]);
        return $record ?: null;
    }

    /**
     * Get active manual enrolment instance for a course.
     *
     * @param int $courseId
     * @return stdClass|null
     */
    public function get_manual_enrol_instance(int $courseId): ?stdClass {
        global $DB;
        $instance = $DB->get_record('enrol', ['courseid' => $courseId, 'enrol' => 'manual', 'status' => ENROL_INSTANCE_ENABLED]);
        return $instance ?: null;
    }

    /**
     * Check if user is currently enrolled in a course.
     *
     * @param int $courseId
     * @param int $userId
     * @return bool
     */
    public function is_user_enrolled(int $courseId, int $userId): bool {
        global $DB;
        $sql = "SELECT ue.id
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                WHERE e.courseid = :courseid AND ue.userid = :userid AND ue.status = :status";
        return $DB->record_exists_sql($sql, ['courseid' => $courseId, 'userid' => $userId, 'status' => ENROL_USER_ACTIVE]);
    }

    /**
     * Retrieve all enrolled users in a course.
     *
     * @param int $courseId
     * @return array
     */
    public function get_enrolled_users(int $courseId): array {
        global $DB;
        $sql = "SELECT u.id, u.username, u.firstname, u.lastname, u.email, u.department, u.institution,
                       ue.id as enrolment_id, ue.status as enrolment_status, ue.timestart, ue.timeend
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {user} u ON u.id = ue.userid
                WHERE e.courseid = :courseid AND u.deleted = 0";
        return $DB->get_records_sql($sql, ['courseid' => $courseId]);
    }

    /**
     * Find users by an array of clean usernames or emails.
     *
     * @param array $identifiers
     * @return array Array of user objects keyed by ID.
     */
    public function find_users_by_identifiers(array $identifiers): array {
        global $DB;
        if (empty($identifiers)) {
            return [];
        }
        list($insql1, $inparams1) = $DB->get_in_or_equal($identifiers, SQL_PARAMS_NAMED, 'u');
        list($insql2, $inparams2) = $DB->get_in_or_equal($identifiers, SQL_PARAMS_NAMED, 'e');
        $params = array_merge($inparams1, $inparams2);
        $sql = "SELECT id, username, email, firstname, lastname, department, institution
                FROM {user}
                WHERE deleted = 0 AND (username $insql1 OR email $insql2)";
        return $DB->get_records_sql($sql, $params);
    }
}
