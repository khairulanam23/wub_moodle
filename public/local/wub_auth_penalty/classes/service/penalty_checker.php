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

namespace local_wub_auth_penalty\service;

defined('MOODLE_INTERNAL') || die();

use context_course;
use stdClass;
use local_wub_auth_penalty\repository\penalty_repository;

/**
 * Access Enforcement & Penalty Checker Service.
 *
 * Orchestrates login permission checks, role exemptions, special permission bypass,
 * UMS payment API querying, due calculation, threshold enforcement (> 100 BDT),
 * session caching, and error redirect generation.
 *
 * @package    local_wub_auth_penalty
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class penalty_checker
{

    /**
     * Default due threshold limit in BDT.
     */
    const DUE_THRESHOLD = 100.0;

    /**
     * Session cache TTL in seconds (10 minutes).
     */
    const CACHE_TTL = 600;

    /**
     * Student API service instance.
     * @var student_api
     */
    protected student_api $apiClient;

    /**
     * Due calculator service instance.
     * @var due_calculator
     */
    protected due_calculator $calculator;

    /**
     * Penalty repository instance.
     * @var penalty_repository
     */
    protected penalty_repository $repository;

    /**
     * Constructor.
     *
     * @param student_api|null $apiClient
     * @param due_calculator|null $calculator
     * @param penalty_repository|null $repository
     */
    public function __construct(?student_api $apiClient = null, ?due_calculator $calculator = null, ?penalty_repository $repository = null)
    {
        $this->apiClient = $apiClient ?? new student_api();
        $this->calculator = $calculator ?? new due_calculator();
        $this->repository = $repository ?? new penalty_repository();
    }

    /**
     * Get allowable due threshold limit.
     *
     * @return float
     */
    public function get_due_threshold(): float
    {
        $configured = get_config('local_wub_auth_penalty', 'due_threshold');
        if ($configured !== false && $configured !== '') {
            return (float)$configured;
        }
        return self::DUE_THRESHOLD;
    }

    /**
     * Check student due status and access authorization.
     *
     * Rules & Flow:
     * 1. Site administrators & teachers are exempt (`allowed = true`).
     * 2. Active special permission (`wub_permission` valid until 23:59:59) bypasses due checks.
     * 3. Session caching (10 mins).
     * 4. Query payment & fee info via UMS API client.
     * 5. Calculate due using `due_calculator`.
     * 6. If calculated due exceeds threshold (100 BDT), block access (`allowed = false`) and generate redirect parameters.
     *
     * @param int $userid Moodle user ID.
     * @return array Status array ['allowed' => bool, 'reason' => string, 'status' => string, 'due' => float, 'redirect_url' => string]
     */
    public function check_due_status(int $userid): array
    {
        global $SESSION;

        // 1. Site administrators exempt
        if (is_siteadmin($userid)) {
            return [
                'allowed' => true,
                'reason' => 'Administrator exempt',
                'status' => 'Active',
                'due' => 0.0,
                'redirect_url' => ''
            ];
        }

        // 2. Teachers exempt (System-wide or Course-level)
        $syscontext = \context_system::instance();
        if (
            has_capability('moodle/course:create', $syscontext, $userid) ||
            has_capability('moodle/course:update', $syscontext, $userid)
        ) {
            return [
                'allowed' => true,
                'reason' => 'Teacher exempt',
                'status' => 'Active',
                'due' => 0.0,
                'redirect_url' => ''
            ];
        }

        if ($this->repository->is_user_teacher_in_any_course($userid)) {
            return [
                'allowed' => true,
                'reason' => 'Teacher exempt',
                'status' => 'Active',
                'due' => 0.0,
                'redirect_url' => ''
            ];
        }

        $user = $this->repository->get_user($userid);
        if (!$user) {
            return [
                'allowed' => false,
                'reason' => 'User record not found',
                'status' => 'Not_Found',
                'due' => 0.0,
                'redirect_url' => '/login/index.php?msg=0'
            ];
        }

        // 3. Special Permission Bypass Check (`$user->wub_permission`)
        if ($this->has_valid_special_permission($user)) {
            return [
                'allowed' => true,
                'reason' => 'Special permission active until 23:59:59',
                'status' => 'Permission_Bypass',
                'due' => 0.0,
                'redirect_url' => ''
            ];
        }

        // 4. Session cache check (10 minutes)
        $cacheKey = 'wub_due_status_' . $userid;
        if (isset($SESSION->$cacheKey) && is_array($SESSION->$cacheKey)) {
            $cached = $SESSION->$cacheKey;
            if (isset($cached['time']) && (time() - $cached['time']) < self::CACHE_TTL) {
                return $cached['data'];
            }
        }

        $studentUsername = explode('@', $user->username)[0];
        if (empty($studentUsername)) {
            $studentUsername = explode('@', $user->email)[0];
        }

        // 5. Query UMS API
        $paymentInfo = $this->apiClient->get_student_payment_info($studentUsername);
        $feeDetails = $this->apiClient->get_student_fees_details($studentUsername);

        if ($paymentInfo === null) {
            $res = [
                'allowed' => false,
                'reason' => 'Unable to connect to UMS payment service',
                'status' => 'API_Error',
                'due' => 0.0,
                'redirect_url' => '/login/index.php?msg=2&a=connection_failed'
            ];
            $SESSION->$cacheKey = ['time' => time(), 'data' => $res];
            return $res;
        }

        // Determine program ID: prefer explicit field 'program_id' if present, otherwise fallback to department, enrol_ums_user table or profile
        $programId = null;
        if (!empty($user->program_id)) {
            $programId = $user->program_id;
        } elseif (!empty($user->department)) {
            $programId = $user->department;
        } elseif (!empty($user->profile['program_id'])) {
            $programId = $user->profile['program_id'];
        }

        if (empty($programId)) {
            $programId = $this->repository->get_student_program_id($user->id);
        }

        $dueResult = $this->calculator->getDue($paymentInfo, $programId, $feeDetails);
        $calculatedDue = $dueResult['final_due'];
        $threshold = $this->get_due_threshold();

        $allowed = true;
        $status = 'Active';
        $reason = '';
        $redirectUrl = '';

        if ($calculatedDue > $threshold) {
            $allowed = false;
            $status = 'Payment_Due';
            $reason = 'Please complete the due payment to log in.';
            $redirectUrl = '/login/index.php?msg=1';
        }

        $res = [
            'allowed' => $allowed,
            'reason' => $reason,
            'status' => $status,
            'due' => $calculatedDue,
            'redirect_url' => $redirectUrl
        ];

        $SESSION->$cacheKey = ['time' => time(), 'data' => $res];
        return $res;
    }

    public function has_valid_special_permission(stdClass $user): bool
    {
        $dbUser = $this->repository->get_user_special_permission($user->id);
        if ($dbUser) {
            $user->special_premission = $dbUser->special_premission ?? 0;
            $user->special_premission_expiry = $dbUser->special_premission_expiry ?? 0;
        }

        $isPermissionEnabled = !empty($user->special_premission) && (int)$user->special_premission === 1;

        if (!$isPermissionEnabled) {
            return false;
        }

        $expiryTimestamp = (int)($user->special_premission_expiry ?? 0);
        if ($expiryTimestamp === 0) {
            return false;
        }

        // Check expiration against current time (respecting Moodle timezone via time())
        if (time() <= $expiryTimestamp) {
            return true;
        }

        // Automatic expiration: if special_premission = true but expired, safely update DB to false via repository
        $this->repository->update_user_special_permission($user->id, 0);
        $user->special_premission = 0;
        unset_user_preference('wub_permission', $user->id);

        return false;
    }
}
