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
use moodle_exception;
use context_system;
use local_bulk_enrolment\api_client;
use local_bulk_enrolment\service\roster_service;

global $CFG;
require_once($CFG->dirroot . '/user/lib.php');

/**
 * Service for WUB UMS student synchronization, comparison matrix, and safe metadata updates.
 *
 * CRITICAL ARCHITECTURAL CONFORMANCE:
 * - Separates synchronization from course enrolment (sync != enrolment).
 * - Compares authoritative UMS student records against local Moodle user database.
 * - Does NOT create synthetic accounts or guess identities.
 * - For unmatched UMS students, reports STATUS_PROVISION_DEFERRED (never creates fake accounts).
 * - For existing matched students, safely updates institutional metadata (department, institution, idnumber)
 *   via native user_update_user() without overwriting passwords, authentication, or roles.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_service {

    public const STATUS_SYNCED = 'SYNCED';
    public const STATUS_CAN_UPDATE = 'CAN_UPDATE';
    public const STATUS_UNMATCHED = 'PROVISION_DEFERRED';
    public const STATUS_AMBIGUOUS = 'AMBIGUOUS_MATCH';

    protected api_client $apiclient;
    protected student_identity_service $identityservice;
    protected user_provisioner $provisioner;

    public function __construct(
        ?api_client $apiclient = null,
        ?student_identity_service $identityservice = null,
        ?user_provisioner $provisioner = null
    ) {
        $this->apiclient = $apiclient ?: new api_client();
        $this->identityservice = $identityservice ?: new student_identity_service();
        $this->provisioner = $provisioner ?: new user_provisioner();
    }

    /**
     * Compare UMS students for a given program and batch against the local Moodle database.
     *
     * @param string $programid UMS Program ID
     * @param string $batchid UMS Batch ID, Batch name, or '0' for all batches
     * @return array [
     *     'summary' => [
     *         'total_ums' => int,
     *         'synced_count' => int,
     *         'updatable_count' => int,
     *         'unmatched_count' => int,
     *         'ambiguous_count' => int,
     *     ],
     *     'students' => array of comparison items
     * ]
     */
    public function compare_students(string $programid, string $batchid = '0'): array {
        $cleanProgramId = trim($programid);
        $cleanBatch = trim($batchid);
        if (empty($cleanProgramId)) {
            return [
                'summary' => [
                    'total_ums' => 0,
                    'synced_count' => 0,
                    'updatable_count' => 0,
                    'unmatched_count' => 0,
                    'ambiguous_count' => 0,
                ],
                'students' => [],
            ];
        }
        // Batch parameter is the UMS batch TITLE (validated by roster_service); '0' / '' / 'all' = whole program.
        $titles = ($cleanBatch === '' || $cleanBatch === '0' || strtolower($cleanBatch) === 'all') ? [] : [$cleanBatch];
        $loaded = (new roster_service($this->apiclient, $this->identityservice))->load_roster([['program_id' => $cleanProgramId, 'batch_titles' => $titles]]);
        return $this->compare_student_models(array_values($loaded['students']), $cleanProgramId, $cleanBatch);
    }
    public function compare_student_models(array $students, string $defaultProgram = '', string $defaultBatch = ''): array {
        $syncedCount = 0;
        $updatableCount = 0;
        $unmatchedCount = 0;
        $ambiguousCount = 0;

        $items = [];
        $resolvedAll = $this->identityservice->bulk_resolve(array_map(
            fn($st) => ['username' => $st->username, 'reg_id' => $st->reg_id], $students));

        foreach ($students as $st) {
            $username = trim($st->username);
            if (empty($username)) {
                continue;
            }

            $fullName = $st->full_name ?: $username;
            $regId = $st->reg_id ?: $username;
            $progName = $st->program_name ?: $defaultProgram;
            $batchName = $st->get_batch() ?: ($defaultBatch !== '0' ? $defaultBatch : '');

            // Identity resolved in bulk above (one SQL pass for the whole roster).
            $resolution = $resolvedAll[strtolower($username)] ?? ['status' => 'NOT_FOUND', 'user' => null];
            $email = $resolution['user']->email ?? '';

            $moodleUser = $resolution['user'] ?? null;
            $status = self::STATUS_UNMATCHED;
            $statusLabel = 'Not in Moodle (Ready to Create)';
            $badgeClass = 'badge-info';
            $selectable = true;
            $updateFields = [];

            if ($moodleUser === null && empty($email)) {
                $email = user_provisioner::get_student_email($username);
            }

            if ($resolution['status'] === 'AMBIGUOUS_MATCH') {
                $status = self::STATUS_AMBIGUOUS;
                $statusLabel = 'Ambiguous Identity in Moodle';
                $badgeClass = 'badge-danger';
                $selectable = false;
                $ambiguousCount++;
            } else if ($moodleUser) {
                // Check if metadata already matches.
                $deptMatches = (trim((string)$moodleUser->department) === trim($progName));
                $instMatches = empty($batchName) || (trim((string)$moodleUser->institution) === trim($batchName));
                $idMatches = empty($regId) || (trim((string)$moodleUser->idnumber) === trim($regId));

                if ($deptMatches && $instMatches && $idMatches) {
                    $status = self::STATUS_SYNCED;
                    $statusLabel = 'Synced (In Local DB)';
                    $badgeClass = 'badge-success';
                    $selectable = true;
                    $syncedCount++;
                } else {
                    $status = self::STATUS_CAN_UPDATE;
                    $statusLabel = 'Ready for Safe Update';
                    $badgeClass = 'badge-primary';
                    $selectable = true;
                    $updatableCount++;

                    if (!$deptMatches) {
                        $updateFields[] = 'department';
                    }
                    if (!$instMatches) {
                        $updateFields[] = 'institution';
                    }
                    if (!$idMatches) {
                        $updateFields[] = 'idnumber';
                    }
                }
            } else {
                $unmatchedCount++;
            }

            $items[] = [
                'username' => $username,
                'full_name' => $fullName,
                'reg_id' => $regId,
                'email' => $email,
                'program_name' => $progName,
                'batch_name' => $batchName,
                'status' => $status,
                'status_label' => $statusLabel,
                'badge_class' => $badgeClass,
                'selectable' => $selectable,
                'can_update' => ($status === self::STATUS_CAN_UPDATE),
                'ready_to_create' => ($status === self::STATUS_UNMATCHED),
                'is_ambiguous' => ($status === self::STATUS_AMBIGUOUS),
                'is_existing' => ($moodleUser !== null),
                'moodle_user_id' => $moodleUser ? (int)$moodleUser->id : 0,
                'update_fields' => implode(', ', $updateFields),
                'course_codes' => method_exists($st, 'get_course_codes_summary') ? $st->get_course_codes_summary() : '',
            ];
        }

        return [
            'summary' => [
                'total_ums' => count($items),
                'synced_count' => $syncedCount,
                'updatable_count' => $updatableCount,
                'unmatched_count' => $unmatchedCount,
                'ambiguous_count' => $ambiguousCount,
            ],
            'students' => $items,
        ];
    }

    /**
     * Safely synchronize selected student accounts in Moodle with authoritative UMS metadata.
     *
     * @param string[] $selectedUsernames Usernames selected for synchronization
     * @param string $programid Program ID
     * @param string $batchid Batch ID
     * @return array Result report
     */
    public function execute_sync(array $selectedUsernames, string $programid, string $batchid = '0'): array {
        $comparison = $this->compare_students($programid, $batchid);
        return $this->execute_sync_for_records($selectedUsernames, $comparison['students']);
    }

    /**
     * Synchronize selected students given an already computed list of comparison records.
     *
     * @param string[] $selectedUsernames
     * @param array $comparisonRecords
     * @return array
     */
    public function execute_sync_for_records(array $selectedUsernames, array $comparisonRecords): array {
        $studentMap = [];
        foreach ($comparisonRecords as $item) {
            $studentMap[$item['username']] = $item;
        }

        $cleanSelected = array_values(array_unique(array_filter(array_map('trim', $selectedUsernames))));
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $log = [];

        foreach ($cleanSelected as $un) {
            $record = $studentMap[$un] ?? null;
            if (!$record) {
                $skipped++;
                $log[] = ['username' => $un, 'status' => 'SKIPPED', 'badge_class' => 'badge-secondary', 'reason' => 'Not found in comparison list.'];
                continue;
            }

            if ($record['status'] === self::STATUS_AMBIGUOUS) {
                $failed++;
                $log[] = ['username' => $un, 'status' => 'BLOCKED', 'badge_class' => 'badge-warning text-dark', 'reason' => 'Ambiguous identity mapping in Moodle.'];
                continue;
            }

            // Existing Moodle student: safely update approved metadata fields.
            if ($record['is_existing'] && $record['moodle_user_id'] > 0) {
                $res = $this->provisioner->update_student_account((int)$record['moodle_user_id'], $record);
                if ($res['status'] === 'UPDATED') {
                    $updated++;
                    $log[] = ['username' => $un, 'status' => 'UPDATED', 'badge_class' => 'badge-success', 'reason' => $res['reason']];
                } else if ($res['status'] === 'ALREADY_SYNCED') {
                    $skipped++;
                    $log[] = ['username' => $un, 'status' => 'ALREADY_SYNCED', 'badge_class' => 'badge-secondary', 'reason' => $res['reason']];
                } else if ($res['status'] === 'BLOCKED') {
                    $failed++;
                    $log[] = ['username' => $un, 'status' => 'BLOCKED', 'badge_class' => 'badge-warning text-dark', 'reason' => $res['reason']];
                } else {
                    $failed++;
                    $log[] = ['username' => $un, 'status' => 'FAILED', 'badge_class' => 'badge-danger', 'reason' => $res['reason']];
                }
            } else {
                // Non-existing student: provision new account from authoritative UMS data.
                $res = $this->provisioner->create_student_account($record);
                if ($res['status'] === 'CREATED') {
                    $created++;
                    $log[] = ['username' => $un, 'status' => 'CREATED', 'badge_class' => 'badge-primary', 'reason' => $res['reason']];
                } else if ($res['status'] === 'UPDATED') {
                    $updated++;
                    $log[] = ['username' => $un, 'status' => 'UPDATED', 'badge_class' => 'badge-success', 'reason' => $res['reason']];
                } else if ($res['status'] === 'ALREADY_SYNCED') {
                    $skipped++;
                    $log[] = ['username' => $un, 'status' => 'ALREADY_SYNCED', 'badge_class' => 'badge-secondary', 'reason' => $res['reason']];
                } else if ($res['status'] === 'BLOCKED') {
                    $failed++;
                    $log[] = ['username' => $un, 'status' => 'BLOCKED', 'badge_class' => 'badge-warning text-dark', 'reason' => $res['reason']];
                } else {
                    $failed++;
                    $log[] = ['username' => $un, 'status' => 'FAILED', 'badge_class' => 'badge-danger', 'reason' => $res['reason']];
                }
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'has_log' => !empty($log),
            'log' => $log,
        ];
    }
}
