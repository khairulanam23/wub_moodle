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
 * Repository for WUB Academic Hierarchy tables and Moodle Course Categories.
 *
 * Encapsulates direct database queries for {wub_academic_faculty}, {wub_academic_department},
 * {wub_academic_program}, {wub_academic_period}, {wub_course_offering},
 * {wub_academic_section}, and {course_categories}.
 *
 * @package    local_wub_ums
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class academic_repository {

    /**
     * Retrieve all academic faculties.
     *
     * @return array
     */
    public function get_faculties(): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('wub_academic_faculty')) {
            return [];
        }
        return $DB->get_records('wub_academic_faculty', null, 'id ASC');
    }

    /**
     * Retrieve academic faculty by ID.
     *
     * @param int $facultyId
     * @return stdClass|null
     */
    public function get_faculty_by_id(int $facultyId): ?stdClass {
        global $DB;
        if (!$DB->get_manager()->table_exists('wub_academic_faculty')) {
            return null;
        }
        $record = $DB->get_record('wub_academic_faculty', ['id' => $facultyId]);
        return $record ?: null;
    }

    /**
     * Update category_id mapping for an academic faculty.
     *
     * @param int $facultyId
     * @param int $categoryId
     * @return bool
     */
    public function update_faculty_category(int $facultyId, int $categoryId): bool {
        global $DB;
        if (!$DB->get_manager()->table_exists('wub_academic_faculty')) {
            return false;
        }
        return $DB->set_field('wub_academic_faculty', 'category_id', $categoryId, ['id' => $facultyId]);
    }

    /**
     * Retrieve all academic departments or filter by faculty ID.
     *
     * @param int|null $facultyId
     * @return array
     */
    public function get_departments(?int $facultyId = null): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('wub_academic_department')) {
            return [];
        }
        if ($facultyId !== null) {
            return $DB->get_records('wub_academic_department', ['faculty_id' => $facultyId], 'id ASC');
        }
        return $DB->get_records('wub_academic_department', null, 'id ASC');
    }

    /**
     * Retrieve academic department by ID.
     *
     * @param int $departmentId
     * @return stdClass|null
     */
    public function get_department_by_id(int $departmentId): ?stdClass {
        global $DB;
        if (!$DB->get_manager()->table_exists('wub_academic_department')) {
            return null;
        }
        $record = $DB->get_record('wub_academic_department', ['id' => $departmentId]);
        return $record ?: null;
    }

    /**
     * Update category_id mapping for an academic department.
     *
     * @param int $departmentId
     * @param int $categoryId
     * @return bool
     */
    public function update_department_category(int $departmentId, int $categoryId): bool {
        global $DB;
        if (!$DB->get_manager()->table_exists('wub_academic_department')) {
            return false;
        }
        return $DB->set_field('wub_academic_department', 'category_id', $categoryId, ['id' => $departmentId]);
    }

    /**
     * Retrieve all academic programs or filter by department ID.
     *
     * @param int|null $departmentId
     * @return array
     */
    public function get_programs(?int $departmentId = null): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('wub_academic_program')) {
            return [];
        }
        if ($departmentId !== null) {
            return $DB->get_records('wub_academic_program', ['department_id' => $departmentId], 'id ASC');
        }
        return $DB->get_records('wub_academic_program', null, 'id ASC');
    }

    /**
     * Retrieve all academic periods / semesters.
     *
     * @return array
     */
    public function get_academic_periods(): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('wub_academic_period')) {
            return [];
        }
        return $DB->get_records('wub_academic_period', null, 'id ASC');
    }

    /**
     * Retrieve course offerings or filter by course/period.
     *
     * @param int|null $courseId
     * @param int|null $periodId
     * @return array
     */
    public function get_course_offerings(?int $courseId = null, ?int $periodId = null): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('wub_course_offering')) {
            return [];
        }
        $conditions = [];
        if ($courseId !== null) {
            $conditions['course_id'] = $courseId;
        }
        if ($periodId !== null) {
            $conditions['period_id'] = $periodId;
        }
        return $DB->get_records('wub_course_offering', $conditions, 'id ASC');
    }

    /**
     * Retrieve sections associated with a course offering.
     *
     * @param int $offeringId
     * @return array
     */
    public function get_sections_by_offering(int $offeringId): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('wub_academic_section')) {
            return [];
        }
        return $DB->get_records('wub_academic_section', ['offering_id' => $offeringId], 'id ASC');
    }

    /**
     * Retrieve a Moodle course category by ID.
     *
     * @param int $categoryId
     * @return stdClass|null
     */
    public function get_moodle_category(int $categoryId): ?stdClass {
        global $DB;
        $record = $DB->get_record('course_categories', ['id' => $categoryId]);
        return $record ?: null;
    }

    /**
     * Find an existing Moodle course category by exact name and parent ID.
     *
     * @param string $name
     * @param int $parent
     * @return stdClass|null
     */
    public function find_moodle_category_by_name(string $name, int $parent = 0): ?stdClass {
        global $DB;
        $record = $DB->get_record('course_categories', ['name' => $name, 'parent' => $parent]);
        if (!$record && $parent === 0) {
            // Also check top-level regardless of slight whitespace variations
            $record = $DB->get_record('course_categories', ['name' => trim($name)]);
        }
        return $record ?: null;
    }

    /**
     * Retrieve courses in a Moodle category.
     *
     * @param int $categoryId
     * @return array
     */
    public function get_courses_by_category(int $categoryId): array {
        global $DB;
        return $DB->get_records('course', ['category' => $categoryId, 'visible' => 1], 'fullname ASC');
    }
}
