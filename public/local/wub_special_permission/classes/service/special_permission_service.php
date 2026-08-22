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

namespace local_wub_special_permission\service;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use local_wub_special_permission\repository\special_permission_repository;

/**
 * Canonical Service for WUB Student Special Login Permission Management.
 *
 * Responsibilities:
 * - Search student user records in Moodle DB.
 * - Retrieve special permission state, expiration status, and formatted human-readable dates.
 * - Grant, update, or revoke student special login permissions using the dedicated repository.
 * - Auto-expire stale permissions safely.
 *
 * @package    local_wub_special_permission
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class special_permission_service {

    /** @var special_permission_repository */
    protected special_permission_repository $repository;

    /**
     * Preference key for legacy fallback compatibility.
     */
    const PERMISSION_PREFERENCE_KEY = 'wub_permission';

    /**
     * Constructor.
     *
     * @param special_permission_repository|null $repository
     */
    public function __construct(?special_permission_repository $repository = null) {
        $this->repository = $repository ?? new special_permission_repository();
    }

    /**
     * Search student record by query string (ID, username, email).
     *
     * @param string $query
     * @return stdClass|null
     */
    public function search_student(string $query): ?stdClass {
        global $DB;

        $rawQuery = strtolower(trim($query));
        if (empty($rawQuery)) {
            return null;
        }

        $shortUsername = explode('@', $rawQuery)[0];
        $digits = preg_replace('/[^0-9]/', '', $shortUsername);
        $cleanId = !empty($digits) ? $digits : $shortUsername;

        // 1. Try exact lookup by username, idnumber, or email
        $user = $DB->get_record_select(
            'user',
            'deleted = 0 AND (username = :u1 OR username = :u2 OR idnumber = :i1 OR email = :e1 OR email = :e2)',
            [
                'u1' => $rawQuery,
                'u2' => $cleanId,
                'i1' => $rawQuery,
                'e1' => $rawQuery,
                'e2' => $cleanId . '@student.wub.edu.bd'
            ]
        );

        if ($user) {
            return $user;
        }

        // 2. Try numeric ID lookup
        if (is_numeric($rawQuery)) {
            $user = $DB->get_record('user', ['id' => (int)$rawQuery, 'deleted' => 0]);
            if ($user) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Retrieve current special login permission status for a student.
     *
     * @param stdClass $user Moodle user record.
     * @return array Status array ['status' => 'active'|'expired'|'none', 'special_premission' => bool, 'permission_date' => string|null, 'expiry_timestamp' => int|null, 'formatted_expiry' => string]
     */
    public function get_permission_status(stdClass $user): array {
        $userId = (int)$user->id;
        $perm = $this->repository->get_permission_by_userid($userId);

        if (!$perm || (int)$perm->status !== 1) {
            // Check legacy preference if any
            $legacyPref = get_user_preferences(self::PERMISSION_PREFERENCE_KEY, null, $userId);
            if (!empty($legacyPref)) {
                $expiryTimestamp = is_numeric($legacyPref) ? (int)$legacyPref : strtotime($legacyPref . ' 23:59:59');
                if ($expiryTimestamp && time() <= $expiryTimestamp) {
                    $this->repository->grant_permission($userId, $expiryTimestamp, 0, 'Self-healed from user preference');
                    $perm = $this->repository->get_permission_by_userid($userId);
                }
            }
        }

        if (!$perm || (int)$perm->status !== 1 || empty($perm->timeend)) {
            return [
                'status' => 'none',
                'special_premission' => false,
                'permission_date' => null,
                'expiry_timestamp' => null,
                'formatted_expiry' => get_string('status_none', 'local_wub_special_permission')
            ];
        }

        $expiryTimestamp = (int)$perm->timeend;
        $isExpired = (time() > $expiryTimestamp);

        if ($isExpired) {
            $this->repository->expire_permission($userId);
            unset_user_preference(self::PERMISSION_PREFERENCE_KEY, $userId);
            return [
                'status' => 'expired',
                'special_premission' => false,
                'permission_date' => date('Y-m-d', $expiryTimestamp),
                'expiry_timestamp' => $expiryTimestamp,
                'formatted_expiry' => userdate($expiryTimestamp, get_string('strftimedatetime', 'langconfig'))
            ];
        }

        return [
            'status' => 'active',
            'special_premission' => true,
            'permission_date' => date('Y-m-d', $expiryTimestamp),
            'expiry_timestamp' => $expiryTimestamp,
            'formatted_expiry' => userdate($expiryTimestamp, get_string('strftimedatetime', 'langconfig'))
        ];
    }

    /**
     * Check if user currently holds an active, non-expired special permission.
     *
     * @param int|stdClass $user
     * @return bool
     */
    public function has_valid_permission($user): bool {
        $userObj = is_object($user) ? $user : (object)['id' => (int)$user];
        $status = $this->get_permission_status($userObj);
        return !empty($status['special_premission']);
    }

    /**
     * Grant or update special login permission for student.
     *
     * @param stdClass $student Student user record.
     * @param string $expiryDate Date string in 'YYYY-MM-DD' format.
     * @param int $adminUserId Admin user ID.
     * @param string $reason Optional grant reason.
     * @return bool
     */
    public function grant_permission(stdClass $student, string $expiryDate, int $adminUserId, string $reason = ''): bool {
        $cleanDate = trim($expiryDate);
        if (empty($cleanDate)) {
            return false;
        }

        $timestamp = strtotime($cleanDate . ' 23:59:59');
        if ($timestamp === false) {
            return false;
        }

        $formattedDate = date('Y-m-d', strtotime($cleanDate));
        $previousStatus = $this->get_permission_status($student);

        $this->repository->grant_permission((int)$student->id, $timestamp, $adminUserId, $reason);
        set_user_preference(self::PERMISSION_PREFERENCE_KEY, $formattedDate, $student->id);

        // Trigger audit event
        $event = \local_wub_special_permission\event\permission_updated::create([
            'context' => \context_system::instance(),
            'userid' => $adminUserId,
            'relateduserid' => $student->id,
            'other' => [
                'action' => 'granted',
                'previous_expiry' => $previousStatus['permission_date'],
                'expiry_date' => $formattedDate,
                'reason' => $reason
            ]
        ]);
        $event->trigger();

        return true;
    }

    /**
     * Revoke special login permission for student.
     *
     * @param stdClass $student
     * @param int $adminUserId
     * @return bool
     */
    public function revoke_permission(stdClass $student, int $adminUserId): bool {
        $previousStatus = $this->get_permission_status($student);

        $this->repository->revoke_permission((int)$student->id);
        unset_user_preference(self::PERMISSION_PREFERENCE_KEY, $student->id);

        // Trigger audit event
        $event = \local_wub_special_permission\event\permission_updated::create([
            'context' => \context_system::instance(),
            'userid' => $adminUserId,
            'relateduserid' => $student->id,
            'other' => [
                'action' => 'revoked',
                'previous_expiry' => $previousStatus['permission_date'],
                'expiry_date' => null
            ]
        ]);
        $event->trigger();

        return true;
    }

    /**
     * Run full migration from legacy {user} table into {wub_special_permission}.
     *
     * @return array
     */
    public function migrate_all(): array {
        return $this->repository->migrate_all_legacy_records();
    }
}
