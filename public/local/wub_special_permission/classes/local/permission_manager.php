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

namespace local_wub_special_permission\local;

defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Service Manager for WUB Student Special Login Permission Management.
 *
 * Responsibilities:
 * - Search student user records in Moodle DB.
 * - Retrieve special_premission boolean state (false / true) and expiration date.
 * - Grant, update, or revoke student special login permissions.
 * - Trigger auditable Moodle events upon permission modification.
 *
 * Architectural Rule:
 * - Does NOT calculate student financial dues.
 * - Does NOT perform login decisions or authentication checks.
 * - Manages the single source of truth (`special_premission` column in {user} table).
 *
 * @package    local_wub_special_permission
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission_manager {

    /**
     * Preference key for legacy fallback compatibility.
     */
    const PERMISSION_PREFERENCE_KEY = 'wub_permission';

    /**
     * Search Moodle user database for student record by username, ID number, or email.
     *
     * @param string $query User input identifier string.
     * @return stdClass|null User record if found, or null.
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
        $user = $DB->get_record_select('user', 'deleted = 0 AND (username = :u1 OR username = :u2 OR idnumber = :i1 OR email = :e1 OR email = :e2)', [
            'u1' => $rawQuery,
            'u2' => $cleanId,
            'i1' => $rawQuery,
            'e1' => $rawQuery,
            'e2' => $cleanId . '@student.wub.edu.bd'
        ]);

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
     * Retrieve current special login permission status for student.
     *
     * @param stdClass $user Moodle user record.
     * @return array Status array ['status' => 'active'|'expired'|'none', 'special_premission' => bool, 'permission_date' => string|null, 'expiry_timestamp' => int|null, 'formatted_expiry' => string]
     */
    public function get_permission_status(stdClass $user): array {
        global $DB;

        // Load special_premission fields from DB if missing on transient user object
        if (!isset($user->special_premission) || !isset($user->special_premission_expiry)) {
            $dbUser = $DB->get_record('user', ['id' => $user->id], 'id, special_premission, special_premission_expiry');
            if ($dbUser) {
                $user->special_premission = $dbUser->special_premission ?? 0;
                $user->special_premission_expiry = $dbUser->special_premission_expiry ?? 0;
            }
        }

        $isPermissionEnabled = !empty($user->special_premission) && (int)$user->special_premission === 1;

        if (!$isPermissionEnabled) {
            // Check legacy preference if any
            $legacyPref = get_user_preferences(self::PERMISSION_PREFERENCE_KEY, null, $user->id);
            if (empty($legacyPref)) {
                return [
                    'status' => 'none',
                    'special_premission' => false,
                    'permission_date' => null,
                    'expiry_timestamp' => null,
                    'formatted_expiry' => get_string('status_none', 'local_wub_special_permission')
                ];
            }
            $expiryTimestamp = is_numeric($legacyPref) ? (int)$legacyPref : strtotime($legacyPref . ' 23:59:59');
            if ($expiryTimestamp && time() <= $expiryTimestamp) {
                // Self-heal DB record
                $DB->execute("UPDATE {user} SET special_premission = 1, special_premission_expiry = ? WHERE id = ?", [$expiryTimestamp, $user->id]);
                $user->special_premission = 1;
                $user->special_premission_expiry = $expiryTimestamp;
                $isPermissionEnabled = true;
            } else {
                return [
                    'status' => 'none',
                    'special_premission' => false,
                    'permission_date' => null,
                    'expiry_timestamp' => null,
                    'formatted_expiry' => get_string('status_none', 'local_wub_special_permission')
                ];
            }
        }

        $expiryTimestamp = (int)($user->special_premission_expiry ?? 0);
        if ($expiryTimestamp === 0) {
            return [
                'status' => 'none',
                'special_premission' => false,
                'permission_date' => null,
                'expiry_timestamp' => null,
                'formatted_expiry' => get_string('status_none', 'local_wub_special_permission')
            ];
        }

        $permissionDate = date('Y-m-d', $expiryTimestamp);
        $formattedExpiry = userdate($expiryTimestamp, get_string('strftimedatetime', 'langconfig'));
        $isExpired = (time() > $expiryTimestamp);

        if ($isExpired) {
            // Auto-expire: update DB state safely
            $DB->execute("UPDATE {user} SET special_premission = 0 WHERE id = ?", [$user->id]);
            $user->special_premission = 0;
            unset_user_preference(self::PERMISSION_PREFERENCE_KEY, $user->id);
        }

        return [
            'status' => $isExpired ? 'expired' : 'active',
            'special_premission' => !$isExpired,
            'permission_date' => $permissionDate,
            'expiry_timestamp' => $expiryTimestamp,
            'formatted_expiry' => $formattedExpiry
        ];
    }

    /**
     * Grant or update special login permission for student.
     *
     * @param stdClass $student Student user record.
     * @param string $expiryDate Date string in 'YYYY-MM-DD' format.
     * @param int $adminUserId Moodle user ID of administering user.
     * @return bool True on success.
     */
    public function grant_permission(stdClass $student, string $expiryDate, int $adminUserId): bool {
        global $DB;

        $cleanDate = trim($expiryDate);
        if (empty($cleanDate)) {
            return false;
        }

        // Validate date format YYYY-MM-DD and compute end of day timestamp
        $timestamp = strtotime($cleanDate . ' 23:59:59');
        if ($timestamp === false) {
            return false;
        }

        $formattedDate = date('Y-m-d', strtotime($cleanDate));

        // Get previous permission for audit log
        $previousStatus = $this->get_permission_status($student);

        // Update database record: special_premission = 1 (true)
        $DB->execute("UPDATE {user} SET special_premission = 1, special_premission_expiry = ? WHERE id = ?", [$timestamp, $student->id]);
        $student->special_premission = 1;
        $student->special_premission_expiry = $timestamp;

        // Store legacy user preference for redundancy
        set_user_preference(self::PERMISSION_PREFERENCE_KEY, $formattedDate, $student->id);

        // Trigger audit event
        $event = \local_wub_special_permission\event\permission_updated::create([
            'context' => \context_system::instance(),
            'userid' => $adminUserId,
            'relateduserid' => $student->id,
            'other' => [
                'action' => 'granted',
                'previous_expiry' => $previousStatus['permission_date'],
                'expiry_date' => $formattedDate
            ]
        ]);
        $event->trigger();

        return true;
    }

    /**
     * Revoke special login permission for student.
     *
     * @param stdClass $student Student user record.
     * @param int $adminUserId Moodle user ID of administering user.
     * @return bool True on success.
     */
    public function revoke_permission(stdClass $student, int $adminUserId): bool {
        global $DB;

        $previousStatus = $this->get_permission_status($student);

        // Update database record: special_premission = 0 (false)
        $DB->execute("UPDATE {user} SET special_premission = 0 WHERE id = ?", [$student->id]);
        $student->special_premission = 0;

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
}
