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

namespace local_wub_special_permission\repository;

defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Repository for WUB Special Login Permissions.
 *
 * Encapsulates direct database persistence and queries for {wub_special_permission}
 * with seamless fallback and migration synchronization from legacy {user} table columns.
 *
 * @package    local_wub_special_permission
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class special_permission_repository {

    /**
     * Retrieve special permission record for a user.
     *
     * @param int $userId
     * @return stdClass|null
     */
    public function get_permission_by_userid(int $userId): ?stdClass {
        global $DB;

        if ($DB->get_manager()->table_exists('wub_special_permission')) {
            $record = $DB->get_record('wub_special_permission', ['userid' => $userId]);
            if ($record) {
                return $record;
            }
        }

        // Fallback to legacy {user} table columns if not yet present in dedicated table
        $legacy = $DB->get_record('user', ['id' => $userId], 'id, special_premission, special_premission_expiry');
        if ($legacy && (!empty($legacy->special_premission) || !empty($legacy->special_premission_expiry))) {
            return (object)[
                'id' => 0,
                'userid' => (int)$legacy->id,
                'status' => (int)($legacy->special_premission ?? 0),
                'timestart' => 0,
                'timeend' => (int)($legacy->special_premission_expiry ?? 0),
                'grantedby' => 0,
                'reason' => 'Legacy user record fallback',
                'timecreated' => time(),
                'timemodified' => time(),
            ];
        }

        return null;
    }

    /**
     * Grant or update special permission for a user in the dedicated table.
     * Also synchronizes to legacy column for maximum backward compatibility during transition.
     *
     * @param int $userId
     * @param int $expiryTimestamp
     * @param int $grantedBy
     * @param string $reason
     * @return int Permission record ID
     */
    public function grant_permission(
        int $userId,
        int $expiryTimestamp,
        int $grantedBy = 0,
        string $reason = ''
    ): int {
        global $DB;

        $now = time();
        $recordId = 0;

        if ($DB->get_manager()->table_exists('wub_special_permission')) {
            $existing = $DB->get_record('wub_special_permission', ['userid' => $userId]);
            if ($existing) {
                $update = (object)[
                    'id' => $existing->id,
                    'status' => 1,
                    'timestart' => $now,
                    'timeend' => $expiryTimestamp,
                    'grantedby' => $grantedBy,
                    'reason' => $reason,
                    'timemodified' => $now,
                ];
                $DB->update_record('wub_special_permission', $update);
                $recordId = (int)$existing->id;
            } else {
                $insert = (object)[
                    'userid' => $userId,
                    'status' => 1,
                    'timestart' => $now,
                    'timeend' => $expiryTimestamp,
                    'grantedby' => $grantedBy,
                    'reason' => $reason,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
                $recordId = (int)$DB->insert_record('wub_special_permission', $insert, true);
            }
        }

        // Keep legacy column synchronized for rollback safety
        $DB->execute(
            "UPDATE {user} SET special_premission = 1, special_premission_expiry = ? WHERE id = ?",
            [$expiryTimestamp, $userId]
        );

        return $recordId;
    }

    /**
     * Revoke special permission for a user.
     *
     * @param int $userId
     * @return bool
     */
    public function revoke_permission(int $userId): bool {
        global $DB;

        $now = time();

        if ($DB->get_manager()->table_exists('wub_special_permission')) {
            $existing = $DB->get_record('wub_special_permission', ['userid' => $userId]);
            if ($existing) {
                $update = (object)[
                    'id' => $existing->id,
                    'status' => 0,
                    'timemodified' => $now,
                ];
                $DB->update_record('wub_special_permission', $update);
            }
        }

        // Keep legacy column synchronized
        $DB->execute(
            "UPDATE {user} SET special_premission = 0 WHERE id = ?",
            [$userId]
        );

        return true;
    }

    /**
     * Mark expired permission as inactive in database.
     *
     * @param int $userId
     * @return bool
     */
    public function expire_permission(int $userId): bool {
        return $this->revoke_permission($userId);
    }

    /**
     * Migrate all legacy special permission records from {user} into {wub_special_permission}.
     *
     * @return array Migration summary counts.
     */
    public function migrate_all_legacy_records(): array {
        global $DB;

        $summary = [
            'legacy_scanned' => 0,
            'migrated_new' => 0,
            'updated_existing' => 0,
        ];

        if (!$DB->get_manager()->table_exists('wub_special_permission')) {
            return $summary;
        }

        $legacyUsers = $DB->get_records_select(
            'user',
            'special_premission = 1 OR special_premission_expiry > 0',
            null,
            'id ASC',
            'id, special_premission, special_premission_expiry'
        );

        $now = time();

        foreach ($legacyUsers as $user) {
            $summary['legacy_scanned']++;
            $userId = (int)$user->id;
            $status = (int)($user->special_premission ?? 0);
            $expiry = (int)($user->special_premission_expiry ?? 0);

            $existing = $DB->get_record('wub_special_permission', ['userid' => $userId]);
            if ($existing) {
                $update = (object)[
                    'id' => $existing->id,
                    'status' => $status,
                    'timeend' => $expiry,
                    'timemodified' => $now,
                ];
                $DB->update_record('wub_special_permission', $update);
                $summary['updated_existing']++;
            } else {
                $insert = (object)[
                    'userid' => $userId,
                    'status' => $status,
                    'timestart' => 0,
                    'timeend' => $expiry,
                    'grantedby' => 0,
                    'reason' => 'Migrated from mdl_user legacy columns',
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
                $DB->insert_record('wub_special_permission', $insert);
                $summary['migrated_new']++;
            }
        }

        return $summary;
    }
}
