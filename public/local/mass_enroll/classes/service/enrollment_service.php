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

namespace local_mass_enroll\service;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use local_mass_enroll\repository\enrollment_repository;
use local_wub_ums\service\sync_service;
use local_wub_auth_penalty\service\penalty_checker;

/**
 * Service for mass course enrollment and UMS student synchronization.
 *
 * Consumes UMS student sync and financial penalty checking through standardized
 * service interfaces rather than duplicating API communications.
 *
 * @package    local_mass_enroll
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrollment_service {

    /** @var enrollment_repository */
    protected enrollment_repository $repository;

    /** @var sync_service */
    protected sync_service $syncService;

    /** @var penalty_checker */
    protected penalty_checker $penaltyChecker;

    /**
     * Constructor.
     *
     * @param enrollment_repository|null $repository
     * @param sync_service|null $syncService
     * @param penalty_checker|null $penaltyChecker
     */
    public function __construct(
        ?enrollment_repository $repository = null,
        ?sync_service $syncService = null,
        ?penalty_checker $penaltyChecker = null
    ) {
        $this->repository = $repository ?? new enrollment_repository();
        $this->syncService = $syncService ?? new sync_service();
        $this->penaltyChecker = $penaltyChecker ?? new penalty_checker();
    }

    /**
     * Get courses by category.
     *
     * @param int $catId
     * @return array
     */
    public function get_courses(int $catId = 0): array {
        return $this->repository->get_courses_by_category($catId);
    }

    /**
     * Enrol a user into a course using Moodle's native manual enrol plugin.
     *
     * @param int $courseId
     * @param int $userId
     * @param int $roleId
     * @param int $timeStart
     * @param int $timeEnd
     * @return bool
     */
    public function enrol_user(int $courseId, int $userId, int $roleId = 5, int $timeStart = 0, int $timeEnd = 0): bool {
        global $DB;

        $manualInstance = $this->repository->get_manual_enrol_instance($courseId);
        if (!$manualInstance) {
            return false;
        }

        $enrolPlugin = enrol_get_plugin('manual');
        if (!$enrolPlugin) {
            return false;
        }

        $enrolPlugin->enrol_user($manualInstance, $userId, $roleId, $timeStart, $timeEnd);
        return true;
    }

    /**
     * Delegate student synchronization to UMS sync service.
     *
     * @param object|array $studentData
     * @param string $programId
     * @param string $batchId
     * @return stdClass|null
     */
    public function sync_or_create_student($studentData, string $programId = '', string $batchId = ''): ?stdClass {
        return $this->syncService->sync_student($studentData, $programId, $batchId);
    }

    /**
     * Check student due status via auth penalty service.
     *
     * @param int $userId
     * @return array
     */
    public function check_due_status(int $userId): array {
        return $this->penaltyChecker->check_due_status($userId);
    }

    /**
     * Compare a list of UMS student objects against Moodle users.
     *
     * @param array $umsStudents List of student objects from UMS.
     * @return array Annotated list with 'status' (Sync / Not Sync).
     */
    public function compare_ums_students(array $umsStudents): array {
        $identifiers = [];
        foreach ($umsStudents as $student) {
            $stdObj = (object)$student;
            $studId = $stdObj->stud_id ?? $stdObj->student_id ?? $stdObj->regId ?? $stdObj->registration_no ?? '';
            $rawUsername = strtolower(trim(
                $stdObj->username ?? ($studId ? str_replace(['/', ' ', '-'], ['', '', ''], $studId) : '')
            ));
            if (!empty($rawUsername)) {
                $base = explode('@', $rawUsername)[0];
                $identifiers[] = $base;
                $identifiers[] = $base . '@student.wub.edu.bd';
            }
        }

        $existingUsers = $this->repository->find_users_by_identifiers(array_unique($identifiers));
        $existingMap = [];
        foreach ($existingUsers as $u) {
            $base = explode('@', $u->username)[0];
            $existingMap[$base] = $u;
            $existingMap[strtolower($u->email)] = $u;
        }

        $result = [];
        foreach ($umsStudents as $student) {
            $stdObj = (object)$student;
            $studId = $stdObj->stud_id ?? $stdObj->student_id ?? $stdObj->regId ?? $stdObj->registration_no ?? '';
            $rawUsername = strtolower(trim(
                $stdObj->username ?? ($studId ? str_replace(['/', ' ', '-'], ['', '', ''], $studId) : '')
            ));
            $base = explode('@', $rawUsername)[0];
            $email = strtolower($base . '@student.wub.edu.bd');

            $isSync = isset($existingMap[$base]) || isset($existingMap[$email]);
            $stdObj->sync_status = $isSync ? 'Sync' : 'Not Sync';
            $result[] = $stdObj;
        }

        return $result;
    }
}
