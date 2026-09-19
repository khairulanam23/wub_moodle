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

namespace local_examcontroller\service;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Authoritative Academic Service for ExamController Integration.
 *
 * Exports authoritative university courses, sections/groups, teacher assignments,
 * and student enrolments with strict pagination, filtering, and deterministic IDs.
 *
 * @package    local_examcontroller
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class academic_service {

    /**
     * Map of department category ID to Department Name and Faculty Name.
     * Cached in-process for fast lookups.
     *
     * @var array|null
     */
    protected ?array $categoryCache = null;

    /**
     * Map of user idnumber to isHod boolean.
     *
     * @var array|null
     */
    protected ?array $hodCache = null;

    /**
     * Get high-level summary overview of authoritative academic structure.
     *
     * @return array
     */
    public function get_overview(): array {
        global $DB;

        $totalCourses = $DB->count_records('course') - 1; // Exclude frontpage course id 1
        $activeCourses = $DB->count_records_select('course', 'startdate > 0');
        $totalSections = $DB->count_records('groups');
        $totalTeachers = $DB->count_records_select('user', "deleted = 0 AND idnumber LIKE 'W00%'");
        $totalStudents = $DB->count_records_select('user', "deleted = 0 AND username REGEXP '^[0-9]{9,10}$'");
        $totalStudentEnrolments = $DB->count_records_sql("
            SELECT COUNT(ue.id)
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            JOIN {role_assignments} ra ON ra.userid = ue.userid
            JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.instanceid = e.courseid
            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
            WHERE e.courseid > 1
        ");

        return [
            'success' => true,
            'data' => [
                'institution' => 'World University of Bangladesh',
                'activeSemester' => 'Fall 2026',
                'totalCourses' => max(0, $totalCourses),
                'activeCourses' => $activeCourses,
                'totalSections' => $totalSections,
                'totalTeachers' => $totalTeachers,
                'totalStudents' => $totalStudents,
                'totalStudentEnrolments' => $totalStudentEnrolments,
            ],
            'http_status' => 200,
        ];
    }

    /**
     * Get authoritative courses.
     *
     * @param string|null $semester Optional 'Fall 2026' or 'active' or 'all'
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function get_courses(?string $semester = null, int $page = 1, int $perPage = 50): array {
        global $DB;

        $params = [];
        $where = "id > 1";

        if ($semester === 'Fall 2026' || $semester === 'active') {
            $where .= " AND startdate > 0";
        }

        $total = $DB->count_records_select('course', $where, $params);
        $offset = max(0, ($page - 1) * $perPage);
        $records = $DB->get_records_select('course', $where, $params, 'id ASC', 'id, shortname, fullname, idnumber, category, startdate, visible', $offset, $perPage);

        $catMap = $this->get_category_map();
        $items = [];

        foreach ($records as $c) {
            $catInfo = $catMap[$c->category] ?? ['dept' => '', 'faculty' => ''];
            $items[] = [
                'moodleCourseId' => (int)$c->id,
                'courseCode' => (string)$c->shortname,
                'courseName' => (string)$c->fullname,
                'idNumber' => (string)$c->idnumber,
                'department' => $catInfo['dept'],
                'faculty' => $catInfo['faculty'],
                'startDate' => (int)$c->startdate,
                'semester' => (int)$c->startdate > 0 ? 'Fall 2026' : null,
                'status' => (int)$c->startdate > 0 && $c->visible ? 'active' : 'inactive',
            ];
        }

        return [
            'success' => true,
            'data' => $items,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
            ],
            'http_status' => 200,
        ];
    }

    /**
     * Get authoritative course sections / groups.
     *
     * @param string|null $semester
     * @param int|null $courseId
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function get_sections(?string $semester = null, ?int $courseId = null, int $page = 1, int $perPage = 50): array {
        global $DB;

        $params = [];
        $where = "c.id > 1";

        if ($semester === 'Fall 2026' || $semester === 'active') {
            $where .= " AND c.startdate > 0";
        }
        if (!empty($courseId)) {
            $where .= " AND g.courseid = :cid";
            $params['cid'] = $courseId;
        }

        $countSql = "
            SELECT COUNT(g.id)
            FROM {groups} g
            JOIN {course} c ON c.id = g.courseid
            WHERE {$where}
        ";
        $total = $DB->count_records_sql($countSql, $params);

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "
            SELECT g.id, g.courseid, g.name as section_name, c.shortname as course_code, c.fullname as course_name, c.startdate
            FROM {groups} g
            JOIN {course} c ON c.id = g.courseid
            WHERE {$where}
            ORDER BY c.id ASC, g.name ASC
        ";
        $records = $DB->get_records_sql($sql, $params, $offset, $perPage);

        $items = [];
        foreach ($records as $r) {
            // Count students in this section group
            $studentCount = $DB->count_records_sql("
                SELECT COUNT(gm.id)
                FROM {groups_members} gm
                JOIN {role_assignments} ra ON ra.userid = gm.userid
                JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.instanceid = :cid
                JOIN {role} role ON role.id = ra.roleid AND role.shortname = 'student'
                WHERE gm.groupid = :gid
            ", ['cid' => $r->courseid, 'gid' => $r->id]);

            // Query assigned course teacher for this section group
            $teacher = $DB->get_record_sql("
                SELECT u.id, u.username, u.idnumber, u.firstname, u.lastname, u.email
                FROM {groups_members} gm
                JOIN {user} u ON u.id = gm.userid
                JOIN {role_assignments} ra ON ra.userid = u.id
                JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.instanceid = :cid
                JOIN {role} role ON role.id = ra.roleid AND role.shortname IN ('editingteacher', 'teacher')
                WHERE gm.groupid = :gid AND u.deleted = 0
                LIMIT 1
            ", ['cid' => $r->courseid, 'gid' => $r->id]);

            $items[] = [
                'moodleGroupId' => (int)$r->id,
                'moodleCourseId' => (int)$r->courseid,
                'courseCode' => (string)$r->course_code,
                'courseName' => (string)$r->course_name,
                'sectionName' => (string)$r->section_name,
                'semester' => (int)$r->startdate > 0 ? 'Fall 2026' : 'General',
                'studentCount' => $studentCount,
                'assignedTeacher' => $teacher ? [
                    'moodleUserId' => (int)$teacher->id,
                    'username' => (string)$teacher->username,
                    'idNumber' => (string)$teacher->idnumber,
                    'fullName' => fullname($teacher),
                    'email' => (string)$teacher->email,
                ] : null,
            ];
        }

        return [
            'success' => true,
            'data' => $items,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
            ],
            'http_status' => 200,
        ];
    }

    /**
     * Get authoritative teacher-to-course and teacher-to-section assignments.
     *
     * @param string|null $semester
     * @param int|null $teacherId
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function get_teacher_assignments(?string $semester = null, ?int $teacherId = null, int $page = 1, int $perPage = 100): array {
        global $DB;

        $params = [];
        $where = "c.id > 1 AND u.deleted = 0 AND r.shortname IN ('editingteacher', 'teacher')";

        if ($semester === 'Fall 2026' || $semester === 'active') {
            $where .= " AND c.startdate > 0";
        }
        if (!empty($teacherId)) {
            $where .= " AND u.id = :tid";
            $params['tid'] = $teacherId;
        }

        $hodMap = $this->get_hod_map();
        $catMap = $this->get_category_map();

        // 1. First fetch all course-level teacher enrolments
        $sql = "
            SELECT ra.id as raid, u.id as userid, u.username, u.idnumber, u.firstname, u.lastname, u.email, u.department,
                   c.id as courseid, c.shortname as course_code, c.fullname as course_name, c.category, c.startdate
            FROM {role_assignments} ra
            JOIN {user} u ON u.id = ra.userid
            JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
            JOIN {course} c ON c.id = ctx.instanceid
            JOIN {role} r ON r.id = ra.roleid
            WHERE {$where}
            ORDER BY c.id ASC, u.id ASC
        ";

        $totalRecords = $DB->get_records_sql($sql, $params);
        $total = count($totalRecords);

        $offset = max(0, ($page - 1) * $perPage);
        $records = array_slice($totalRecords, $offset, $perPage, true);

        $items = [];
        foreach ($records as $r) {
            $isHod = !empty($hodMap[$r->idnumber]);
            $teacherRole = $isHod ? 'HOD' : 'COURSE_TEACHER';

            // Find sections/groups where this teacher is explicitly assigned
            $assignedGroups = $DB->get_records_sql("
                SELECT g.id, g.name
                FROM {groups_members} gm
                JOIN {groups} g ON g.id = gm.groupid
                WHERE gm.userid = :uid AND g.courseid = :cid
                ORDER BY g.name ASC
            ", ['uid' => $r->userid, 'cid' => $r->courseid]);

            if ($isHod) {
                // HOD has course-wide oversight (and therefore all sections of this course)
                $allCourseGroups = $DB->get_records('groups', ['courseid' => $r->courseid], 'name ASC', 'id, name');
                foreach ($allCourseGroups as $g) {
                    $items[] = [
                        'moodleUserId' => (int)$r->userid,
                        'teacherUsername' => (string)$r->username,
                        'teacherIdNumber' => (string)$r->idnumber,
                        'fullName' => fullname($r),
                        'email' => (string)$r->email,
                        'department' => (string)$r->department,
                        'moodleCourseId' => (int)$r->courseid,
                        'courseCode' => (string)$r->course_code,
                        'courseName' => (string)$r->course_name,
                        'moodleGroupId' => (int)$g->id,
                        'sectionName' => (string)$g->name,
                        'semester' => (int)$r->startdate > 0 ? 'Fall 2026' : 'General',
                        'role' => 'HOD',
                        'isCourseWide' => true,
                    ];
                }
            } else {
                // Regular course teacher: assigned to specific sections
                if (!empty($assignedGroups)) {
                    foreach ($assignedGroups as $g) {
                        $items[] = [
                            'moodleUserId' => (int)$r->userid,
                            'teacherUsername' => (string)$r->username,
                            'teacherIdNumber' => (string)$r->idnumber,
                            'fullName' => fullname($r),
                            'email' => (string)$r->email,
                            'department' => (string)$r->department,
                            'moodleCourseId' => (int)$r->courseid,
                            'courseCode' => (string)$r->course_code,
                            'courseName' => (string)$r->course_name,
                            'moodleGroupId' => (int)$g->id,
                            'sectionName' => (string)$g->name,
                            'semester' => (int)$r->startdate > 0 ? 'Fall 2026' : 'General',
                            'role' => 'COURSE_TEACHER',
                            'isCourseWide' => false,
                        ];
                    }
                } else {
                    // Enrolled in course but no group assigned yet
                    $items[] = [
                        'moodleUserId' => (int)$r->userid,
                        'teacherUsername' => (string)$r->username,
                        'teacherIdNumber' => (string)$r->idnumber,
                        'fullName' => fullname($r),
                        'email' => (string)$r->email,
                        'department' => (string)$r->department,
                        'moodleCourseId' => (int)$r->courseid,
                        'courseCode' => (string)$r->course_code,
                        'courseName' => (string)$r->course_name,
                        'moodleGroupId' => null,
                        'sectionName' => null,
                        'semester' => (int)$r->startdate > 0 ? 'Fall 2026' : 'General',
                        'role' => 'COURSE_TEACHER',
                        'isCourseWide' => true,
                    ];
                }
            }
        }

        return [
            'success' => true,
            'data' => $items,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
            ],
            'http_status' => 200,
        ];
    }

    /**
     * Get authoritative student-to-section enrolments.
     *
     * @param string|null $semester
     * @param int|null $courseId
     * @param int|null $groupId
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function get_student_enrolments(?string $semester = null, ?int $courseId = null, ?int $groupId = null, int $page = 1, int $perPage = 200): array {
        global $DB;

        $params = [];
        $where = "c.id > 1 AND u.deleted = 0 AND r.shortname = 'student'";

        if ($semester === 'Fall 2026' || $semester === 'active') {
            $where .= " AND c.startdate > 0";
        }
        if (!empty($courseId)) {
            $where .= " AND c.id = :cid";
            $params['cid'] = $courseId;
        }
        if (!empty($groupId)) {
            $where .= " AND g.id = :gid";
            $params['gid'] = $groupId;
        }

        $countSql = "
            SELECT COUNT(gm.id)
            FROM {groups_members} gm
            JOIN {groups} g ON g.id = gm.groupid
            JOIN {course} c ON c.id = g.courseid
            JOIN {user} u ON u.id = gm.userid
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.instanceid = c.id
            JOIN {role} r ON r.id = ra.roleid
            WHERE {$where}
        ";
        $total = $DB->count_records_sql($countSql, $params);

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "
            SELECT gm.id as gmid, u.id as userid, u.username, u.idnumber, u.firstname, u.lastname, u.email, u.department,
                   c.id as courseid, c.shortname as course_code, c.fullname as course_name, c.startdate,
                   g.id as groupid, g.name as section_name
            FROM {groups_members} gm
            JOIN {groups} g ON g.id = gm.groupid
            JOIN {course} c ON c.id = g.courseid
            JOIN {user} u ON u.id = gm.userid
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.instanceid = c.id
            JOIN {role} r ON r.id = ra.roleid
            WHERE {$where}
            ORDER BY c.id ASC, g.id ASC, u.username ASC
        ";

        $records = $DB->get_records_sql($sql, $params, $offset, $perPage);
        $items = [];

        foreach ($records as $r) {
            $items[] = [
                'moodleUserId' => (int)$r->userid,
                'universityId' => (string)$r->username,
                'registrationNo' => (string)$r->idnumber,
                'fullName' => fullname($r),
                'email' => (string)$r->email,
                'department' => (string)$r->department,
                'moodleCourseId' => (int)$r->courseid,
                'courseCode' => (string)$r->course_code,
                'courseName' => (string)$r->course_name,
                'moodleGroupId' => (int)$r->groupid,
                'sectionName' => (string)$r->section_name,
                'semester' => (int)$r->startdate > 0 ? 'Fall 2026' : 'General',
            ];
        }

        return [
            'success' => true,
            'data' => $items,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
            ],
            'http_status' => 200,
        ];
    }

    /**
     * Build category map of category id -> department and faculty names.
     */
    protected function get_category_map(): array {
        if ($this->categoryCache !== null) {
            return $this->categoryCache;
        }

        global $DB;
        $categories = $DB->get_records('course_categories', null, 'id ASC', 'id, name, parent');
        $map = [];

        foreach ($categories as $cat) {
            if ($cat->parent == 0) {
                $map[$cat->id] = [
                    'dept' => '',
                    'faculty' => $cat->name,
                ];
            } else {
                $parentName = isset($categories[$cat->parent]) ? $categories[$cat->parent]->name : '';
                $map[$cat->id] = [
                    'dept' => $cat->name,
                    'faculty' => $parentName,
                ];
            }
        }

        $this->categoryCache = $map;
        return $map;
    }

    /**
     * Build map of HOD localIds from authoritative category role assignments.
     *
     * Identifies teachers assigned 'manager', 'coursecreator', or 'hod' role
     * at the Course Category context (contextlevel 40).
     */
    protected function get_hod_map(): array {
        if ($this->hodCache !== null) {
            return $this->hodCache;
        }

        global $DB;
        $map = [];

        // Authoritative query: users assigned manager/coursecreator/hod role in a category context
        $hods = $DB->get_records_sql("
            SELECT DISTINCT u.idnumber
            FROM {role_assignments} ra
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {user} u ON u.id = ra.userid
            WHERE ctx.contextlevel = 40
              AND r.shortname IN ('manager', 'coursecreator', 'hod')
              AND u.deleted = 0
              AND u.idnumber IS NOT NULL
              AND u.idnumber != ''
        ");

        foreach ($hods as $h) {
            $map[$h->idnumber] = true;
        }

        $this->hodCache = $map;
        return $map;
    }
}
