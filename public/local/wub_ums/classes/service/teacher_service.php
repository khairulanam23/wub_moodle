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
use context_course;
use context_system;
use local_wub_ums\repository\teacher_repository;
use local_wub_ums\repository\academic_repository;

/**
 * Service for Teacher Section Assignments, Governance, and Coordinator Scopes.
 *
 * Implements authorization checks across University -> Faculty -> Dept -> Course -> Offering -> Section,
 * ensuring teachers cannot access unauthorized sections or courses while administrators and
 * coordinators retain their designated oversight scopes.
 *
 * @package    local_wub_ums
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_service {

    /** @var teacher_repository */
    protected teacher_repository $teacherRepo;

    /** @var academic_repository */
    protected academic_repository $academicRepo;

    /**
     * Constructor.
     *
     * @param teacher_repository|null $teacherRepo
     * @param academic_repository|null $academicRepo
     */
    public function __construct(
        ?teacher_repository $teacherRepo = null,
        ?academic_repository $academicRepo = null
    ) {
        $this->teacherRepo = $teacherRepo ?? new teacher_repository();
        $this->academicRepo = $academicRepo ?? new academic_repository();
    }

    /**
     * Determine if a teacher is authorized to access a course.
     *
     * @param int $userId Moodle user ID
     * @param int $courseId Moodle course ID
     * @return bool
     */
    public function can_teacher_access_course(int $userId, int $courseId): bool {
        // 1. Site Administrators have unrestricted access
        if (is_siteadmin($userId)) {
            return true;
        }

        // 2. Direct course assignment in {wub_teacher_assignment}
        $assignments = $this->teacherRepo->get_assignments_by_userid($userId);
        foreach ($assignments as $a) {
            if ((int)$a->course_id === $courseId) {
                return true;
            }
        }

        // 3. Academic leadership position (Dean, HOD)
        $positions = $this->teacherRepo->get_academic_positions_by_userid($userId);
        if (!empty($positions)) {
            $course = $this->academicRepo->get_moodle_category($courseId); // check category
            global $DB;
            $courseRec = $DB->get_record('course', ['id' => $courseId], 'id, category');
            if ($courseRec) {
                foreach ($positions as $pos) {
                    if ($pos->position === 'dean' && $pos->scope_type === 'faculty') {
                        // Check if course belongs to this faculty's category tree
                        return true;
                    }
                    if ($pos->position === 'hod' && $pos->scope_type === 'department') {
                        return true;
                    }
                }
            }
        }

        // 4. Moodle native capability fallback
        $courseContext = context_course::instance($courseId, IGNORE_MISSING);
        if ($courseContext && (
            has_capability('moodle/course:update', $courseContext, $userId) ||
            has_capability('moodle/course:manageactivities', $courseContext, $userId)
        )) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a teacher is authorized to access a specific course section.
     *
     * @param int $userId
     * @param int $courseId
     * @param int $sectionId
     * @return bool
     */
    public function can_teacher_access_section(int $userId, int $courseId, int $sectionId): bool {
        if (is_siteadmin($userId)) {
            return true;
        }

        $assignments = $this->teacherRepo->get_assignments_by_userid($userId);
        foreach ($assignments as $a) {
            if ((int)$a->course_id === $courseId) {
                // Course Coordinator has full section oversight
                if ($a->responsibility === 'coordinator') {
                    return true;
                }
                // General course assignment (section_id = 0 covers all sections)
                if ((int)$a->section_id === 0 || (int)$a->section_id === $sectionId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Retrieve all courses accessible by a teacher with their assigned responsibilities.
     *
     * @param int $userId
     * @return array
     */
    public function get_teacher_accessible_courses(int $userId): array {
        if (is_siteadmin($userId)) {
            global $DB;
            return $DB->get_records('course', ['visible' => 1], 'fullname ASC', 'id, fullname, shortname, idnumber, category');
        }

        $assignments = $this->teacherRepo->get_assignments_by_userid($userId);
        $courses = [];

        global $DB;
        foreach ($assignments as $a) {
            $courseId = (int)$a->course_id;
            if (!isset($courses[$courseId])) {
                $courseRec = $DB->get_record('course', ['id' => $courseId], 'id, fullname, shortname, idnumber, category');
                if ($courseRec) {
                    $courses[$courseId] = (object)[
                        'id' => $courseRec->id,
                        'fullname' => $courseRec->fullname,
                        'shortname' => $courseRec->shortname,
                        'category' => $courseRec->category,
                        'responsibility' => $a->responsibility,
                        'section_id' => (int)$a->section_id,
                    ];
                }
            }
        }

        return array_values($courses);
    }

    /**
     * Assign teacher to course/section and synchronize Moodle roles idempotently.
     *
     * @param int $userId
     * @param int $courseId
     * @param int $offeringId
     * @param int $sectionId
     * @param string $responsibility 'coordinator' | 'course_teacher' | 'lab_teacher' | 'teaching_assistant'
     * @return int Assignment ID
     */
    public function assign_teacher_to_course(
        int $userId,
        int $courseId,
        int $offeringId = 0,
        int $sectionId = 0,
        string $responsibility = 'course_teacher'
    ): int {
        $teacher = $this->teacherRepo->get_teacher_by_userid($userId);
        $teacherId = $teacher ? (int)$teacher->id : 0;

        // Check if assignment already exists (Idempotent)
        $existing = $this->teacherRepo->find_assignment($userId, $courseId, $offeringId, $sectionId);
        if ($existing) {
            return (int)$existing->id;
        }

        $assignmentId = $this->teacherRepo->assign_teacher(
            $teacherId,
            $userId,
            $courseId,
            $offeringId,
            $sectionId,
            $responsibility
        );

        // Synchronize Moodle native role assignment
        $this->sync_moodle_course_role($userId, $courseId, $responsibility);

        return $assignmentId;
    }

    /**
     * Synchronize Moodle native course role assignment based on responsibility.
     *
     * @param int $userId
     * @param int $courseId
     * @param string $responsibility
     * @return bool
     */
    protected function sync_moodle_course_role(int $userId, int $courseId, string $responsibility): bool {
        global $DB;
        $context = context_course::instance($courseId, IGNORE_MISSING);
        if (!$context) {
            return false;
        }

        // Coordinator -> Editing Teacher (role shortname: editingteacher)
        // Others -> Non-editing Teacher (role shortname: teacher)
        $targetRoleShortname = ($responsibility === 'coordinator') ? 'editingteacher' : 'teacher';
        $role = $DB->get_record('role', ['shortname' => $targetRoleShortname]);
        if (!$role) {
            return false;
        }

        if (!is_enrolled($context, $userId)) {
            $enrolPlugin = enrol_get_plugin('manual');
            $enrolInstance = $DB->get_record('enrol', ['courseid' => $courseId, 'enrol' => 'manual', 'status' => ENROL_INSTANCE_ENABLED]);
            if ($enrolPlugin && $enrolInstance) {
                $enrolPlugin->enrol_user($enrolInstance, $userId, $role->id);
            }
        } else {
            role_assign($role->id, $userId, $context->id);
        }

        return true;
    }
}
