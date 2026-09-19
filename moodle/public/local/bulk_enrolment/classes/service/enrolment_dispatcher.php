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

/**
 * Executes one bounded chunk of enrolments into one course using enrol_manual (native API only).
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\service;

defined('MOODLE_INTERNAL') || die();

use context_course;
use moodle_exception;
use Throwable;

/**
 * Chunked enrolment dispatcher.
 */
class enrolment_dispatcher {
    /** @var int Hard upper bound of users per transaction. */
    public const MAX_CHUNK = 50;

    /** @var instance_resolver */
    protected instance_resolver $instanceresolver;
    /** @var role_resolver */
    protected role_resolver $roleresolver;
    /** @var permission_service */
    protected permission_service $permissionservice;
    /** @var group_service */
    protected group_service $groupservice;

    /**
     * Constructor.
     *
     * @param instance_resolver|null $instanceresolver
     * @param role_resolver|null $roleresolver
     * @param permission_service|null $permissionservice
     * @param group_service|null $groupservice
     */
    public function __construct(?instance_resolver $instanceresolver = null, ?role_resolver $roleresolver = null,
            ?permission_service $permissionservice = null, ?group_service $groupservice = null) {
        $this->instanceresolver = $instanceresolver ?: new instance_resolver();
        $this->roleresolver = $roleresolver ?: new role_resolver();
        $this->permissionservice = $permissionservice ?: new permission_service();
        $this->groupservice = $groupservice ?: new group_service();
    }

    /**
     * Enrol up to MAX_CHUNK users into one course inside one transaction. Idempotent: existing active
     * enrolments report ALREADY_ENROLLED and are never duplicated.
     *
     * @param int $courseid
     * @param int[] $userids
     * @param int $roleid 0 = resolved student role.
     * @param int $timestart
     * @param int $timeend
     * @param bool $reactivatesuspended
     * @param array $groupoptions ['groupname' => string, 'allowcreate' => bool] Optional group assignment.
     * @return array Structured report.
     * @throws moodle_exception On course-level preconditions (permission, instance) or a rolled-back transaction.
     */
    public function enrol_chunk(int $courseid, array $userids, int $roleid = 0, int $timestart = 0, int $timeend = 0,
            bool $reactivatesuspended = false, array $groupoptions = []): array {
        global $DB;

        if ($courseid <= 1 || !$DB->record_exists('course', ['id' => $courseid])) {
            throw new moodle_exception('error_invalid_course', 'local_bulk_enrolment');
        }
        $clean = array_values(array_unique(array_filter(array_map('intval', $userids), fn($id) => $id > 0)));
        if (count($clean) > self::MAX_CHUNK) {
            throw new moodle_exception('error_chunk_too_large', 'local_bulk_enrolment', '', self::MAX_CHUNK);
        }
        if ($roleid <= 0) {
            $roleid = $this->roleresolver->get_default_student_role_id();
        }
        $perm = $this->permissionservice->validate_enrolment_permission($courseid, $roleid);
        if (!$perm['allowed']) {
            throw new moodle_exception('error_no_permission_course', 'local_bulk_enrolment', '', $courseid);
        }
        $instance = $this->instanceresolver->get_manual_instance($courseid);
        if (!$instance) {
            throw new moodle_exception('error_instance_missing', 'local_bulk_enrolment', '', $courseid);
        }
        if (!$this->instanceresolver->is_instance_active($instance)) {
            throw new moodle_exception('error_instance_disabled', 'local_bulk_enrolment', '', $courseid);
        }
        $manual = enrol_get_plugin('manual');
        if (!$manual) {
            throw new moodle_exception('enrolnotpermitted', 'enrol_manual');
        }
        $context = context_course::instance($courseid);

        // Resolve the group once per chunk (outside the enrolment transaction so a group failure is reported, not fatal).
        $groupid = 0;
        $groupname = trim((string)($groupoptions['groupname'] ?? ''));
        $grouperror = '';
        if ($groupname !== '') {
            $g = $this->groupservice->resolve_or_create_group($courseid, $groupname, (bool)($groupoptions['allowcreate'] ?? false));
            if ($g['success']) {
                $groupid = (int)$g['groupid'];
            } else {
                $grouperror = (string)$g['error'];
            }
        }

        $results = [];
        $counts = ['enrolled' => 0, 'already_enrolled' => 0, 'reactivated' => 0, 'failed' => 0, 'grouped' => 0];
        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($clean as $uid) {
                if (!$DB->record_exists('user', ['id' => $uid, 'deleted' => 0])) {
                    $results[] = ['userid' => $uid, 'status' => 'IDENTITY_NOT_FOUND', 'message' => get_string('msg_identity_not_found', 'local_bulk_enrolment'), 'group' => ''];
                    $counts['failed']++;
                    continue;
                }
                $existing = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $uid]);
                $status = '';
                if ($existing) {
                    if ((int)$existing->status === ENROL_USER_SUSPENDED && $reactivatesuspended) {
                        $manual->update_user_enrol($instance, $uid, ENROL_USER_ACTIVE, $timestart, $timeend);
                        if (!$DB->record_exists('role_assignments', ['roleid' => $roleid, 'contextid' => $context->id, 'userid' => $uid])) {
                            role_assign($roleid, $uid, $context->id);
                        }
                        $status = 'REACTIVATED';
                        $counts['reactivated']++;
                    } else if (is_enrolled($context, $uid, '', true)) {
                        $status = 'ALREADY_ENROLLED';
                        $counts['already_enrolled']++;
                    } else {
                        $status = 'ENROLMENT_SUSPENDED';
                        $counts['failed']++;
                    }
                } else {
                    $manual->enrol_user($instance, $uid, $roleid, $timestart, $timeend);
                    $status = 'ENROLLED';
                    $counts['enrolled']++;
                }
                $groupmsg = '';
                if ($groupid > 0 && in_array($status, ['ENROLLED', 'REACTIVATED', 'ALREADY_ENROLLED'], true)) {
                    if ($this->groupservice->add_member($groupid, $uid)) {
                        $groupmsg = $groupname;
                        $counts['grouped']++;
                    } else {
                        $groupmsg = get_string('msg_group_failed', 'local_bulk_enrolment');
                    }
                } else if ($grouperror !== '') {
                    $groupmsg = $grouperror;
                }
                $results[] = ['userid' => $uid, 'status' => $status, 'message' => get_string('result_' . strtolower($status), 'local_bulk_enrolment'), 'group' => $groupmsg];
            }
            $transaction->allow_commit();
        } catch (Throwable $e) {
            $transaction->rollback($e);
            throw new moodle_exception('status_system_error', 'local_bulk_enrolment', '', $e->getMessage());
        }

        if ($counts['enrolled'] > 0 || $counts['reactivated'] > 0) {
            \local_bulk_enrolment\event\bulk_enrolment_executed::log_enrolment(
                $courseid,
                $counts['enrolled'] + $counts['reactivated'],
                $roleid,
                [
                    'source' => 'dispatcher',
                    'reactivated' => $counts['reactivated'],
                    'groups_assigned' => $counts['grouped'],
                ]
            );
        }

        return [
            'courseid' => $courseid,
            'total' => count($clean),
            'enrolled' => $counts['enrolled'],
            'already_enrolled' => $counts['already_enrolled'],
            'reactivated' => $counts['reactivated'],
            'failed' => $counts['failed'],
            'grouped' => $counts['grouped'],
            'groupid' => $groupid,
            'group_error' => $grouperror,
            'results' => $results,
        ];
    }
}
