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
use enrol_manual_plugin;
use context_course;
use moodle_exception;
use Throwable;

/**
 * Service for secure, course-scoped bulk unenrolment.
 *
 * CRITICAL ARCHITECTURAL CONFORMANCE:
 * - Strictly validates 'enrol/manual:unenrol' in context_course.
 * - Uses native enrol_manual_plugin::unenrol_user().
 * - Scoped strictly to target course; never affects unrelated enrolments or system roles.
 * - Never deletes user accounts (mdl_user).
 * - Executes in 50-student transactional chunks.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unenrolment_service {

    protected instance_resolver $instanceresolver;
    protected student_identity_service $identityservice;

    public function __construct(
        ?instance_resolver $instanceresolver = null,
        ?student_identity_service $identityservice = null
    ) {
        $this->instanceresolver = $instanceresolver ?: new instance_resolver();
        $this->identityservice = $identityservice ?: new student_identity_service();
    }

    /**
     * Preview students to be unenrolled from a course.
     *
     * @param int $courseid Target Moodle Course ID
     * @param string[] $identifiers User identifiers (usernames, emails, IDs)
     * @return array [
     *     'courseid' => int,
     *     'course_name' => string,
     *     'total' => int,
     *     'unenrolable' => int,
     *     'not_enrolled' => int,
     *     'invalid' => int,
     *     'rows' => array of candidate rows
     * ]
     */
    /**
     * Alias for preview_unenrolment.
     *
     * @param int $courseid
     * @param array $identifiers
     * @return array
     */
    public function generate_preview(int $courseid, array $identifiers): array {
        return $this->preview_unenrolment($courseid, $identifiers);
    }

    public function preview_unenrolment(int $courseid, array $identifiers): array {
        global $DB;

        if ($courseid <= 0) {
            throw new moodle_exception('error_invalid_course', 'local_bulk_enrolment');
        }

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            throw new moodle_exception('error_invalid_course', 'local_bulk_enrolment');
        }

        // 1. Verify unenrolment capability in course context.
        $context = context_course::instance($courseid);
        if (!has_capability('enrol/manual:unenrol', $context)) {
            throw new moodle_exception('error_no_permission_course', 'local_bulk_enrolment', '', $courseid);
        }

        // 2. Resolve manual enrolment instance.
        $instance = $this->instanceresolver->get_manual_instance($courseid);
        if (!$instance) {
            throw new moodle_exception('error_instance_missing', 'local_bulk_enrolment', '', $courseid);
        }

        $rows = [];
        $unenrolableCount = 0;
        $notEnrolledCount = 0;
        $invalidCount = 0;

        foreach ($identifiers as $ident) {
            $clean = trim($ident);
            if (empty($clean)) continue;

            $res = $this->identityservice->resolve_identifier($clean);
            if ($res['status'] !== 'RESOLVED' || !$res['user']) {
                $rows[] = [
                    'identifier' => $clean,
                    'user' => null,
                    'status' => $res['status'],
                    'allowed' => false,
                    'message' => "User '{$clean}' could not be resolved ({$res['status']}).",
                ];
                $invalidCount++;
                continue;
            }

            $user = $res['user'];
            $ue = $DB->get_record('user_enrolments', [
                'enrolid' => $instance->id,
                'userid' => $user->id,
            ]);

            if ($ue) {
                $rows[] = [
                    'identifier' => $clean,
                    'user' => $user,
                    'status' => 'UNENROLABLE',
                    'allowed' => true,
                    'message' => 'Active manual enrolment found. Ready for unenrolment.',
                ];
                $unenrolableCount++;
            } else {
                $rows[] = [
                    'identifier' => $clean,
                    'user' => $user,
                    'status' => 'NOT_ENROLLED',
                    'allowed' => false,
                    'message' => 'User is not enrolled via manual enrolment in this course.',
                ];
                $notEnrolledCount++;
            }
        }

        return [
            'courseid' => $courseid,
            'course_name' => $course->fullname,
            'total' => count($rows),
            'unenrolable' => $unenrolableCount,
            'not_enrolled' => $notEnrolledCount,
            'invalid' => $invalidCount,
            'rows' => $rows,
        ];
    }

    /**
     * Execute bulk unenrolment for verified user IDs in a course.
     *
     * Processes in 50-student transactional chunks.
     *
     * @param int $courseid Target Course ID
     * @param int[] $userids Array of Moodle User IDs to unenrol
     * @return array Execution report
     */
    public function execute_unenrolment(int $courseid, array $userids): array {
        global $DB;

        $context = context_course::instance($courseid);
        if (!has_capability('enrol/manual:unenrol', $context)) {
            throw new moodle_exception('error_no_permission_course', 'local_bulk_enrolment', '', $courseid);
        }

        $instance = $this->instanceresolver->get_manual_instance($courseid);
        if (!$instance) {
            throw new moodle_exception('error_instance_missing', 'local_bulk_enrolment', '', $courseid);
        }

        /** @var enrol_manual_plugin $manualPlugin */
        $manualPlugin = enrol_get_plugin('manual');
        if (!$manualPlugin) {
            throw new moodle_exception('enrolnotpermitted', 'enrol_manual');
        }

        $cleanUserIds = array_values(array_filter(array_unique($userids), fn($id) => $id > 0));
        $chunks = array_chunk($cleanUserIds, 50);

        $totalUnenrolled = 0;
        $totalFailed = 0;
        $results = [];

        foreach ($chunks as $chunk) {
            $transaction = $DB->start_delegated_transaction();
            try {
                foreach ($chunk as $uid) {
                    $ue = $DB->get_record('user_enrolments', [
                        'enrolid' => $instance->id,
                        'userid' => $uid,
                    ]);

                    if ($ue) {
                        $manualPlugin->unenrol_user($instance, $uid);
                        $results[] = [
                            'userid' => $uid,
                            'status' => 'UNENROLLED',
                            'message' => 'Successfully unenrolled.',
                        ];
                        $totalUnenrolled++;
                    } else {
                        $results[] = [
                            'userid' => $uid,
                            'status' => 'NOT_ENROLLED',
                            'message' => 'User was not enrolled.',
                        ];
                    }
                }
                $transaction->allow_commit();
            } catch (Throwable $e) {
                $transaction->rollback($e);
                throw new moodle_exception('status_system_error', 'local_bulk_enrolment', '', $e->getMessage());
            }
        }

        // Log Moodle audit event.
        if ($totalUnenrolled > 0) {
            \local_bulk_enrolment\event\bulk_unenrolment_executed::log_unenrolment(
                $courseid,
                $totalUnenrolled,
                ['source' => 'bulk_unenrolment']
            );
        }

        return [
            'courseid' => $courseid,
            'total' => count($cleanUserIds),
            'unenrolled' => $totalUnenrolled,
            'failed' => $totalFailed,
            'results' => $results,
        ];
    }
}
