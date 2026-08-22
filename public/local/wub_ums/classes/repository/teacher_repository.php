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
 * Repository for Teacher profiles, Assignments, and Academic Positions.
 *
 * Encapsulates direct database queries for {teacher}, {wub_teacher_assignment},
 * {wub_academic_position}, and {wub_academic_section}.
 *
 * @package    local_wub_ums
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_repository {

    /**
     * Retrieve teacher record by ID.
     *
     * @param int $teacherId
     * @return stdClass|null
     */
    public function get_teacher_by_id(int $teacherId): ?stdClass {
        global $DB;
        if (!$DB->get_manager()->table_exists('teacher')) {
            return null;
        }
        $record = $DB->get_record('teacher', ['id' => $teacherId]);
        return $record ?: null;
    }

    /**
     * Retrieve teacher record by Moodle User ID.
     *
     * @param int $userId
     * @return stdClass|null
     */
    public function get_teacher_by_userid(int $userId): ?stdClass {
        global $DB;
        if (!$DB->get_manager()->table_exists('teacher')) {
            return null;
        }
        $record = $DB->get_record('teacher', ['userid' => $userId]);
        return $record ?: null;
    }

    /**
     * Retrieve all teacher assignments for a Moodle User ID.
     *
     * @param int $userId
     * @return array
     */
    public function get_assignments_by_userid(int $userId): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('wub_teacher_assignment')) {
            return [];
        }
        return $DB->get_records('wub_teacher_assignment', ['userid' => $userId, 'status' => 1], 'id ASC');
    }

    /**
     * Retrieve all teacher assignments for a specific course.
     *
     * @param int $courseId
     * @return array
     */
    public function get_assignments_by_course(int $courseId): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('wub_teacher_assignment')) {
            return [];
        }
        return $DB->get_records('wub_teacher_assignment', ['course_id' => $courseId, 'status' => 1], 'id ASC');
    }

    /**
     * Retrieve all assignments for a section.
     *
     * @param int $sectionId
     * @return array
     */
    public function get_assignments_by_section(int $sectionId): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('wub_teacher_assignment')) {
            return [];
        }
        return $DB->get_records('wub_teacher_assignment', ['section_id' => $sectionId, 'status' => 1], 'id ASC');
    }

    /**
     * Find an existing assignment by user, course, offering, and section.
     *
     * @param int $userId
     * @param int $courseId
     * @param int $offeringId
     * @param int $sectionId
     * @return stdClass|null
     */
    public function find_assignment(int $userId, int $courseId, int $offeringId = 0, int $sectionId = 0): ?stdClass {
        global $DB;
        if (!$DB->get_manager()->table_exists('wub_teacher_assignment')) {
            return null;
        }
        $record = $DB->get_record('wub_teacher_assignment', [
            'userid' => $userId,
            'course_id' => $courseId,
            'offering_id' => $offeringId,
            'section_id' => $sectionId,
            'status' => 1,
        ]);
        return $record ?: null;
    }

    /**
     * Assign teacher to a course/offering/section.
     *
     * @param int $teacherId
     * @param int $userId
     * @param int $courseId
     * @param int $offeringId
     * @param int $sectionId
     * @param string $responsibility
     * @return int Inserted assignment ID
     */
    public function assign_teacher(
        int $teacherId,
        int $userId,
        int $courseId,
        int $offeringId = 0,
        int $sectionId = 0,
        string $responsibility = 'course_teacher'
    ): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('wub_teacher_assignment')) {
            return 0;
        }

        $assignment = (object)[
            'teacher_id' => $teacherId,
            'userid' => $userId,
            'course_id' => $courseId,
            'offering_id' => $offeringId,
            'section_id' => $sectionId,
            'responsibility' => $responsibility,
            'status' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        return (int)$DB->insert_record('wub_teacher_assignment', $assignment, true);
    }

    /**
     * Retrieve academic leadership positions for a user.
     *
     * @param int $userId
     * @return array
     */
    public function get_academic_positions_by_userid(int $userId): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('wub_academic_position')) {
            return [];
        }
        return $DB->get_records('wub_academic_position', ['userid' => $userId, 'status' => 1]);
    }
}
