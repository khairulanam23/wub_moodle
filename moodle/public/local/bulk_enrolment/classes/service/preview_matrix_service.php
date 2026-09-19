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
 * Pre-enrolment verification matrix: one row per (student x course) with an explicit status.
 *
 * Statuses: READY, ALREADY_ENROLLED, ENROLMENT_SUSPENDED, IDENTITY_NOT_FOUND, AMBIGUOUS_MATCH,
 * NO_MANUAL_INSTANCE, MANUAL_INSTANCE_DISABLED, INVALID_COURSE, NOT_AUTHORIZED.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\service;

defined('MOODLE_INTERNAL') || die();

use context_course;

/**
 * Verification matrix service.
 */
class preview_matrix_service {
    /** @var string */
    public const READY = 'ENROLLABLE';
    /** @var string */
    public const ENROLLABLE = 'ENROLLABLE';
    /** @var string */
    public const ALREADY_ENROLLED = 'ALREADY_ENROLLED';
    /** @var string */
    public const ENROLMENT_SUSPENDED = 'ENROLMENT_SUSPENDED';
    /** @var string */
    public const IDENTITY_NOT_FOUND = 'IDENTITY_NOT_FOUND';
    /** @var string */
    public const AMBIGUOUS_MATCH = 'AMBIGUOUS_MATCH';
    /** @var string */
    public const NO_MANUAL_INSTANCE = 'NO_MANUAL_INSTANCE';
    /** @var string */
    public const MANUAL_INSTANCE_DISABLED = 'MANUAL_INSTANCE_DISABLED';
    /** @var string */
    public const INVALID_COURSE = 'INVALID_COURSE';
    /** @var string */
    public const NOT_AUTHORIZED = 'NOT_AUTHORIZED';

    /** @var instance_resolver */
    protected instance_resolver $instanceresolver;
    /** @var role_resolver */
    protected role_resolver $roleresolver;
    /** @var permission_service */
    protected permission_service $permissionservice;
    /** @var student_identity_service */
    protected student_identity_service $identity;

    /**
     * Constructor.
     *
     * @param instance_resolver|null $instanceresolver
     * @param role_resolver|null $roleresolver
     * @param permission_service|null $permissionservice
     * @param student_identity_service|null $identity
     */
    public function __construct(?instance_resolver $instanceresolver = null, ?role_resolver $roleresolver = null,
            ?permission_service $permissionservice = null, ?student_identity_service $identity = null) {
        $this->instanceresolver = $instanceresolver ?: new instance_resolver();
        $this->roleresolver = $roleresolver ?: new role_resolver();
        $this->permissionservice = $permissionservice ?: new permission_service();
        $this->identity = $identity ?: new student_identity_service();
    }

    /**
     * Alias for compute.
     *
     * @param int[] $courseids
     * @param array $students
     * @param int $roleid
     * @param bool $reactivatesuspended
     * @return array
     */
    public function compute_matrix(array $courseids, array $students, int $roleid = 0, bool $reactivatesuspended = false): array {
        return $this->compute($courseids, $students, $roleid, $reactivatesuspended);
    }

    /**
     * Compute the matrix for UMS identities (usernames) x Moodle courses.
     *
     * @param int[] $courseids
     * @param array $students [['username' => string, 'reg_id' => string, 'full_name' => string], ...] or user IDs
     * @param int $roleid 0 = resolved student role.
     * @param bool $reactivatesuspended Whether suspended enrolments count as READY.
     * @return array ['summary' => [...], 'rows' => [...], 'roleid' => int]
     */
    public function compute(array $courseids, array $students, int $roleid = 0, bool $reactivatesuspended = false): array {
        global $DB;
        if ($roleid <= 0) {
            $roleid = $this->roleresolver->get_default_student_role_id();
        }
        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        $identities = [];
        $userlookupids = [];
        foreach ($students as $s) {
            if (is_numeric($s) && (int)$s > 0) {
                $userlookupids[] = (int)$s;
            } else if (is_string($s)) {
                $un = trim($s);
                if ($un !== '' && !isset($identities[$un])) {
                    $identities[$un] = ['username' => $un, 'reg_id' => '', 'full_name' => ''];
                }
            } else if (is_object($s)) {
                if (!empty($s->id)) {
                    $userlookupids[] = (int)$s->id;
                } else if (!empty($s->username)) {
                    $un = trim((string)$s->username);
                    if ($un !== '' && !isset($identities[$un])) {
                        $identities[$un] = ['username' => $un, 'reg_id' => (string)($s->reg_id ?? $s->regId ?? ''), 'full_name' => (string)($s->full_name ?? '')];
                    }
                }
            } else if (is_array($s)) {
                if (!empty($s['id']) && empty($s['username'])) {
                    $userlookupids[] = (int)$s['id'];
                } else {
                    $un = trim((string)($s['username'] ?? ''));
                    if ($un !== '' && !isset($identities[$un])) {
                        $identities[$un] = ['username' => $un, 'reg_id' => (string)($s['reg_id'] ?? ''), 'full_name' => (string)($s['full_name'] ?? '')];
                    }
                }
            }
        }
        if (!empty($userlookupids)) {
            [$usql, $uparams] = $DB->get_in_or_equal(array_values(array_unique($userlookupids)), SQL_PARAMS_NAMED, 'uids');
            $foundusers = $DB->get_records_sql("SELECT id, username, idnumber, firstname, lastname FROM {user} WHERE id $usql AND deleted = 0", $uparams);
            foreach ($foundusers as $u) {
                $un = trim($u->username);
                if ($un !== '' && !isset($identities[$un])) {
                    $identities[$un] = ['username' => $un, 'reg_id' => (string)$u->idnumber, 'full_name' => fullname($u)];
                }
            }
        }

        $empty = ['summary' => ['total' => 0, 'ready' => 0, 'enrollable' => 0, 'already_enrolled' => 0, 'issues' => 0], 'rows' => [], 'roleid' => $roleid];
        if (empty($courseids) || empty($identities)) {
            return $empty;
        }

        $resolved = $this->identity->bulk_resolve(array_values($identities));
        $userids = [];
        foreach ($resolved as $r) {
            if ($r['user']) {
                $userids[] = (int)$r['user']->id;
            }
        }

        [$csql, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $courses = $DB->get_records_select('course', "id $csql AND id <> :site", $cparams + ['site' => SITEID], '', 'id, fullname, shortname');

        $enrolmap = [];
        if (!empty($userids)) {
            [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
            $sql = "SELECT ue.id, ue.userid, ue.status AS uestatus, e.courseid, e.enrol
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE e.courseid $csql AND ue.userid $usql";
            foreach ($DB->get_records_sql($sql, $cparams + $uparams) as $ee) {
                $enrolmap[$ee->courseid][$ee->userid] = $ee;
            }
        }

        $coursestate = [];
        foreach ($courseids as $cid) {
            if (!isset($courses[$cid])) {
                $coursestate[$cid] = ['status' => self::INVALID_COURSE, 'msg' => get_string('msg_invalid_course', 'local_bulk_enrolment')];
                continue;
            }
            $instance = $this->instanceresolver->get_manual_instance($cid);
            if (!$instance) {
                $coursestate[$cid] = ['status' => self::NO_MANUAL_INSTANCE, 'msg' => get_string('msg_no_manual_instance', 'local_bulk_enrolment')];
                continue;
            }
            if (!$this->instanceresolver->is_instance_active($instance)) {
                $coursestate[$cid] = ['status' => self::MANUAL_INSTANCE_DISABLED, 'msg' => get_string('msg_manual_instance_disabled', 'local_bulk_enrolment')];
                continue;
            }
            $perm = $this->permissionservice->validate_enrolment_permission($cid, $roleid);
            if (!$perm['allowed']) {
                $coursestate[$cid] = ['status' => self::NOT_AUTHORIZED, 'msg' => get_string($perm['reason'] === 'ROLE_NOT_ASSIGNABLE' ? 'msg_role_not_assignable' : 'msg_not_authorized', 'local_bulk_enrolment')];
                continue;
            }
            $coursestate[$cid] = ['status' => self::READY, 'msg' => ''];
        }

        $rows = [];
        $ready = 0;
        $already = 0;
        $issues = 0;
        foreach ($courseids as $cid) {
            $course = $courses[$cid] ?? null;
            $cstate = $coursestate[$cid];
            foreach ($identities as $un => $ident) {
                $res = $resolved[strtolower($un)] ?? ['status' => student_identity_service::NOT_FOUND, 'user' => null];
                $u = $res['user'];
                $row = [
                    'courseid' => $cid,
                    'coursename' => $course ? format_string($course->fullname) : get_string('msg_unknown_course', 'local_bulk_enrolment', $cid),
                    'courseshortname' => $course ? format_string($course->shortname) : '',
                    'key' => $un,
                    'username' => $un,
                    'reg_id' => $ident['reg_id'],
                    'userid' => $u ? (int)$u->id : 0,
                    'fullname' => $u ? fullname($u) : ($ident['full_name'] !== '' ? $ident['full_name'] : $un),
                    'idnumber' => $u ? (string)$u->idnumber : '',
                    'status' => self::READY,
                    'is_enrollable' => false,
                    'message' => '',
                ];
                if ($cstate['status'] !== self::READY) {
                    $row['status'] = $cstate['status'];
                    $row['message'] = $cstate['msg'];
                } else if ($res['status'] === student_identity_service::AMBIGUOUS) {
                    $row['status'] = self::AMBIGUOUS_MATCH;
                    $row['message'] = get_string('msg_ambiguous', 'local_bulk_enrolment', (int)($res['candidates'] ?? 2));
                } else if (!$u) {
                    $row['status'] = self::IDENTITY_NOT_FOUND;
                    $row['message'] = get_string('msg_identity_not_found', 'local_bulk_enrolment');
                } else if (isset($enrolmap[$cid][$u->id])) {
                    $ee = $enrolmap[$cid][$u->id];
                    if ((int)$ee->uestatus === ENROL_USER_SUSPENDED) {
                        $row['status'] = self::ENROLMENT_SUSPENDED;
                        $row['is_enrollable'] = $reactivatesuspended;
                        $row['message'] = get_string($reactivatesuspended ? 'msg_suspended_reactivate' : 'msg_suspended', 'local_bulk_enrolment');
                    } else {
                        $row['status'] = self::ALREADY_ENROLLED;
                        $row['message'] = get_string('msg_already_enrolled', 'local_bulk_enrolment', $ee->enrol);
                    }
                } else {
                    $row['is_enrollable'] = true;
                    $row['message'] = $u->suspended ? get_string('msg_ready_user_suspended', 'local_bulk_enrolment') : get_string('msg_ready', 'local_bulk_enrolment');
                }
                $row['status_label'] = get_string('status_' . strtolower($row['status']), 'local_bulk_enrolment');
                $row['badge_class'] = self::badge_for($row['status']);
                if ($row['status'] === self::ALREADY_ENROLLED) {
                    $already++;
                } else if ($row['is_enrollable']) {
                    $ready++;
                } else {
                    $issues++;
                }
                $rows[] = $row;
            }
        }
        return [
            'summary' => [
                'total' => count($rows),
                'ready' => $ready,
                'enrollable' => $ready,
                'already_enrolled' => $already,
                'issues' => $issues,
            ],
            'rows' => $rows,
            'roleid' => $roleid,
        ];
    }

    /**
     * Bootstrap badge class for a status.
     *
     * @param string $status
     * @return string
     */
    public static function badge_for(string $status): string {
        return match ($status) {
            self::READY, 'READY', 'ENROLLABLE' => 'badge-success',
            self::ALREADY_ENROLLED => 'badge-info',
            self::ENROLMENT_SUSPENDED => 'badge-warning',
            default => 'badge-danger',
        };
    }
}
