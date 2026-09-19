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
use csv_import_reader;
use context_course;
use moodle_exception;

global $CFG;
require_once($CFG->libdir . '/csvlib.class.php');

/**
 * Service for CSV-based bulk enrolment processing, validation, and chunked execution.
 *
 * Supported CSV columns:
 * - Student: 'username' | 'email' | 'idnumber' | 'studentid'
 * - Course: 'course' | 'courseid' | 'shortname'
 * - Role (optional): 'role' (shortname, defaults to student)
 * - Group (optional): 'group' (course group name)
 * - Enrolment dates (optional): 'timestart', 'timeend'
 *
 * CRITICAL ARCHITECTURAL CONFORMANCE:
 * - Uses native csv_import_reader.
 * - Resolves student identities unambiguously via student_identity_service.
 * - Enforces course-context authorization for every row.
 * - Validates manual enrolment instances non-destructively.
 * - Dispatches transactional execution in 50-student chunks.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_enrolment_service {

    protected student_identity_service $identityservice;
    protected permission_service $permissionservice;
    protected instance_resolver $instanceresolver;
    protected role_resolver $roleresolver;
    protected group_service $groupservice;
    protected enrolment_dispatcher $dispatcher;

    public function __construct(
        ?student_identity_service $identityservice = null,
        ?permission_service $permissionservice = null,
        ?instance_resolver $instanceresolver = null,
        ?role_resolver $roleresolver = null,
        ?group_service $groupservice = null,
        ?enrolment_dispatcher $dispatcher = null
    ) {
        $this->identityservice = $identityservice ?: new student_identity_service();
        $this->permissionservice = $permissionservice ?: new permission_service();
        $this->instanceresolver = $instanceresolver ?: new instance_resolver();
        $this->roleresolver = $roleresolver ?: new role_resolver();
        $this->groupservice = $groupservice ?: new group_service();
        $this->dispatcher = $dispatcher ?: new enrolment_dispatcher();
    }

    /**
     * Parse and preview a CSV file content or path.
     *
     * @param string $csvcontent Raw CSV string or file path
     * @param string $delimiter Comma, semicolon, or tab
     * @param string $encoding Character encoding
     * @param bool $allowcreategroups Whether group auto-creation is authorized
     * @return array [
     *     'total_rows' => int,
     *     'valid_rows' => int,
     *     'invalid_rows' => int,
     *     'rows' => array of validated row objects
     * ]
     */
    public function preview_csv(
        string $csvcontent,
        string $delimiter = 'comma',
        string $encoding = 'UTF-8',
        bool $allowcreategroups = false
    ): array {
        global $DB;

        $iid = csv_import_reader::get_new_iid('local_bulk_enrolment');
        $reader = new csv_import_reader($iid, 'local_bulk_enrolment');
        $readcount = $reader->load_csv_content($csvcontent, $encoding, $delimiter);

        if ($readcount === false || $reader->get_error() !== null) {
            $reader->cleanup();
            throw new moodle_exception('error_invalid_csv', 'local_bulk_enrolment');
        }

        if (!$reader->init()) {
            $reader->cleanup();
            throw new moodle_exception('error_invalid_csv', 'local_bulk_enrolment');
        }

        $headers = array_map('strtolower', array_map('trim', $reader->get_columns()));

        // Identify column mappings.
        $colUser = null;
        foreach (['username', 'email', 'idnumber', 'studentid', 'student_id'] as $candidate) {
            $idx = array_search($candidate, $headers);
            if ($idx !== false) {
                $colUser = $idx;
                break;
            }
        }

        $colCourse = null;
        foreach (['course', 'courseid', 'course_id', 'shortname', 'idnumber_course'] as $candidate) {
            $idx = array_search($candidate, $headers);
            if ($idx !== false) {
                $colCourse = $idx;
                break;
            }
        }

        if ($colUser === null || $colCourse === null) {
            $reader->close();
            $reader->cleanup();
            throw new moodle_exception('error_csv_missing_columns', 'local_bulk_enrolment');
        }

        $colRole = array_search('role', $headers);
        $colGroup = array_search('group', $headers);
        $colStart = array_search('timestart', $headers);
        $colEnd = array_search('timeend', $headers);

        $defaultRoleId = $this->roleresolver->get_default_student_role_id();

        $rows = [];
        $validCount = 0;
        $invalidCount = 0;
        $line = 1;

        // Cache course records in memory during loop to avoid repeated DB hits.
        $courseCache = [];

        while ($record = $reader->next()) {
            $line++;
            if (empty(array_filter($record))) {
                continue;
            }

            $userIdent = trim($record[$colUser] ?? '');
            $courseIdent = trim($record[$colCourse] ?? '');
            $roleStr = ($colRole !== false) ? trim($record[$colRole] ?? '') : '';
            $groupStr = ($colGroup !== false) ? trim($record[$colGroup] ?? '') : '';
            $startStr = ($colStart !== false) ? trim($record[$colStart] ?? '') : '';
            $endStr = ($colEnd !== false) ? trim($record[$colEnd] ?? '') : '';

            $rowObj = [
                'line' => $line,
                'user_identifier' => $userIdent,
                'course_identifier' => $courseIdent,
                'user' => null,
                'course' => null,
                'role_id' => $defaultRoleId,
                'role_name' => 'Student',
                'group_name' => $groupStr,
                'timestart' => !empty($startStr) ? strtotime($startStr) : 0,
                'timeend' => !empty($endStr) ? strtotime($endStr) : 0,
                'status' => 'PENDING',
                'allowed' => false,
                'message' => '',
            ];

            // 1. Resolve User.
            $userRes = $this->identityservice->resolve_identifier($userIdent);
            if ($userRes['status'] !== 'RESOLVED') {
                $rowObj['status'] = $userRes['status'];
                $rowObj['message'] = "User '{$userIdent}' could not be resolved ({$userRes['status']}).";
                $rows[] = $rowObj;
                $invalidCount++;
                continue;
            }
            $rowObj['user'] = $userRes['user'];

            // 2. Resolve Course.
            $course = null;
            if (isset($courseCache[$courseIdent])) {
                $course = $courseCache[$courseIdent];
            } else {
                if (is_numeric($courseIdent)) {
                    $course = $DB->get_record('course', ['id' => (int)$courseIdent]);
                }
                if (!$course) {
                    $course = $DB->get_record('course', ['shortname' => $courseIdent]);
                }
                if (!$course) {
                    $course = $DB->get_record('course', ['idnumber' => $courseIdent]);
                }
                $courseCache[$courseIdent] = $course;
            }

            if (!$course) {
                $rowObj['status'] = 'COURSE_NOT_FOUND';
                $rowObj['message'] = "Course '{$courseIdent}' does not exist.";
                $rows[] = $rowObj;
                $invalidCount++;
                continue;
            }
            $rowObj['course'] = $course;

            // 3. Resolve Role.
            if (!empty($roleStr)) {
                $role = $DB->get_record('role', ['shortname' => strtolower($roleStr)]);
                if ($role) {
                    $rowObj['role_id'] = (int)$role->id;
                    $rowObj['role_name'] = $role->name ?: $role->shortname;
                } else {
                    $rowObj['status'] = 'INVALID_ROLE';
                    $rowObj['message'] = "Role '{$roleStr}' is not defined.";
                    $rows[] = $rowObj;
                    $invalidCount++;
                    continue;
                }
            }

            // 4. Validate Course Permissions.
            $perm = $this->permissionservice->validate_enrolment_permission($course->id, $rowObj['role_id']);
            if (!$perm['allowed']) {
                $rowObj['status'] = 'PERMISSION_DENIED';
                $rowObj['message'] = "User lacks permission to assign roles in course '{$course->shortname}'.";
                $rows[] = $rowObj;
                $invalidCount++;
                continue;
            }

            // 5. Validate Manual Enrolment Instance.
            $instance = $this->instanceresolver->get_manual_instance($course->id);
            if (!$instance) {
                $rowObj['status'] = 'MANUAL_INSTANCE_MISSING';
                $rowObj['message'] = "Course '{$course->shortname}' does not have a manual enrolment instance.";
                $rows[] = $rowObj;
                $invalidCount++;
                continue;
            }

            if (!$this->instanceresolver->is_instance_active($instance)) {
                $rowObj['status'] = 'MANUAL_INSTANCE_DISABLED';
                $rowObj['message'] = "Manual enrolment is disabled in course '{$course->shortname}'.";
                $rows[] = $rowObj;
                $invalidCount++;
                continue;
            }

            // 6. Check Existing Enrolment.
            $context = context_course::instance($course->id);
            $alreadyEnrolled = is_enrolled($context, $rowObj['user']->id);
            if ($alreadyEnrolled) {
                $rowObj['status'] = 'ALREADY_ENROLLED';
                $rowObj['message'] = "Student is already enrolled in course '{$course->shortname}'.";
                $rowObj['allowed'] = false;
                $rows[] = $rowObj;
                continue;
            }

            // Valid row.
            $rowObj['status'] = 'ENROLLABLE';
            $rowObj['allowed'] = true;
            $rowObj['message'] = 'Ready for bulk enrolment.';
            $validCount++;
            $rows[] = $rowObj;
        }

        $reader->close();
        $reader->cleanup();

        return [
            'total_rows' => count($rows),
            'valid_rows' => $validCount,
            'invalid_rows' => $invalidCount,
            'rows' => $rows,
        ];
    }

    /**
     * Execute bulk enrolment from validated CSV rows.
     *
     * Processes course-by-course in 50-student chunks.
     *
     * @param array $validatedrows Validated row objects from preview_csv()
     * @param bool $allowcreategroups Whether to create missing groups
     * @return array Execution report
     */
    public function execute_csv_enrolment(array $validatedrows, bool $allowcreategroups = false): array {
        // Group rows by target course ID.
        $byCourse = [];
        foreach ($validatedrows as $row) {
            if (!$row['allowed'] || empty($row['user']) || empty($row['course'])) {
                continue;
            }
            $cid = (int)$row['course']->id;
            $byCourse[$cid][] = $row;
        }

        $totalEnrolled = 0;
        $totalAlready = 0;
        $totalFailed = 0;
        $courseReports = [];

        foreach ($byCourse as $courseid => $rows) {
            $userids = array_map(fn($r) => (int)$r['user']->id, $rows);
            $roleid = $rows[0]['role_id'] ?? 0;
            $timestart = $rows[0]['timestart'] ?? 0;
            $timeend = $rows[0]['timeend'] ?? 0;

            // Chunk execution in batches of 50.
            $chunks = array_chunk($userids, 50);
            $cEnrolled = 0;
            $cAlready = 0;
            $cFailed = 0;

            foreach ($chunks as $chunk) {
                $res = $this->dispatcher->enrol_chunk($courseid, $chunk, $roleid, $timestart, $timeend);
                $cEnrolled += $res['enrolled'];
                $cAlready += $res['already_enrolled'];
                $cFailed += $res['failed'];
            }

            // Group assignments.
            $groupAssigned = 0;
            foreach ($rows as $r) {
                if (!empty($r['group_name'])) {
                    $gres = $this->groupservice->resolve_or_create_group($courseid, $r['group_name'], $allowcreategroups);
                    if ($gres['success']) {
                        $this->groupservice->add_member($gres['groupid'], (int)$r['user']->id);
                        $groupAssigned++;
                    }
                }
            }

            // Log Moodle event.
            \local_bulk_enrolment\event\bulk_enrolment_executed::log_enrolment(
                $courseid,
                $cEnrolled,
                $roleid,
                ['source' => 'csv', 'groups_assigned' => $groupAssigned]
            );

            $totalEnrolled += $cEnrolled;
            $totalAlready += $cAlready;
            $totalFailed += $cFailed;

            $courseReports[] = [
                'courseid' => $courseid,
                'course_name' => $rows[0]['course']->fullname,
                'enrolled' => $cEnrolled,
                'already_enrolled' => $cAlready,
                'failed' => $cFailed,
                'groups_assigned' => $groupAssigned,
            ];
        }

        return [
            'total_enrolled' => $totalEnrolled,
            'total_already_enrolled' => $totalAlready,
            'total_failed' => $totalFailed,
            'courses' => $courseReports,
        ];
    }
}
