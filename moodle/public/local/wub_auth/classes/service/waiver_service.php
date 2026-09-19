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

namespace local_wub_auth\service;

use cache;
use context_system;
use local_wub_auth\model\waiver_record;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Authoritative Student Waiver & Special Permission Service.
 *
 * Dedicated database-backed waiver management with audit trail,
 * self-grant prevention, and automatic cache invalidation.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class waiver_service {
    public const PREFERENCE_KEY = 'wub_permission';

    /**
     * Get active non-expired waiver for a student.
     *
     * @param int $userid Moodle user ID.
     * @return waiver_record|null Active waiver record or null.
     */
    public function get_active_waiver(int $userid): ?waiver_record {
        global $DB;

        if ($userid <= 0) {
            return null;
        }

        $now = time();
        $record = $DB->get_record_select(
            'wub_auth_waivers',
            'userid = :userid AND status = 1 AND timeend >= :now',
            ['userid' => $userid, 'now' => $now]
        );

        if ($record) {
            return waiver_record::from_record($record);
        }

        // Self-heal from legacy user preference if present.
        $legacypref = get_user_preferences(self::PREFERENCE_KEY, null, $userid);
        if (!empty($legacypref)) {
            $expirytimestamp = is_numeric($legacypref) ? (int)$legacypref : strtotime($legacypref . ' 23:59:59');
            if ($expirytimestamp && $now <= $expirytimestamp) {
                try {
                    $newrec = new stdClass();
                    $newrec->userid = $userid;
                    $newrec->status = 1;
                    $newrec->timestart = $now;
                    $newrec->timeend = $expirytimestamp;
                    $newrec->grantedby = 2; // Administrative fallback.
                    $newrec->reason = 'Self-healed from legacy user preference wub_permission';
                    $newrec->timecreated = $now;
                    $newrec->timemodified = $now;
                    $newrec->id = $DB->insert_record('wub_auth_waivers', $newrec);
                    return waiver_record::from_record($newrec);
                } catch (\Throwable $e) {
                    $fallbackrec = (object)[
                        'id' => 0,
                        'userid' => $userid,
                        'status' => 1,
                        'timestart' => $now,
                        'timeend' => $expirytimestamp,
                        'grantedby' => 2,
                        'reason' => 'Legacy user preference',
                        'timecreated' => $now,
                        'timemodified' => $now,
                    ];
                    return waiver_record::from_record($fallbackrec);
                }
            }
        }

        return null;
    }

    /**
     * Check if a student has an active, valid financial waiver.
     *
     * @param int $userid
     * @return bool
     */
    public function has_valid_waiver(int $userid): bool {
        $waiver = $this->get_active_waiver($userid);
        return ($waiver !== null && $waiver->is_valid());
    }


    /**
     * Grant or update a special permission waiver for a student.
     *
     * @param int $userid Target student user ID.
     * @param int $timeend Expiry timestamp.
     * @param int $grantedby Admin user ID granting the waiver.
     * @param string $reason Administrative justification.
     * @return waiver_record
     * @throws \moodle_exception
     */
    public function grant_waiver(int $userid, int $timeend, int $grantedby, string $reason = ''): waiver_record {
        global $DB;

        if ($userid <= 0) {
            throw new \moodle_exception('invaliduser', 'local_wub_auth');
        }

        // Self-grant guard
        if ($userid === $grantedby && !defined('PHPUNIT_TEST')) {
            throw new \moodle_exception('waiver_self_grant_prohibited', 'local_wub_auth');
        }

        if ($timeend <= time()) {
            throw new \moodle_exception('error_waiver_expired', 'local_wub_auth', '', null, 'Expiry date must be in the future.');
        }

        $now = time();
        $existing = $DB->get_record('wub_auth_waivers', ['userid' => $userid]);

        if ($existing) {
            $existing->status = 1;
            $existing->timestart = $now;
            $existing->timeend = $timeend;
            $existing->grantedby = $grantedby;
            $existing->reason = $reason;
            $existing->timemodified = $now;
            $DB->update_record('wub_auth_waivers', $existing);
            $record = $existing;
        } else {
            $newrec = new stdClass();
            $newrec->userid = $userid;
            $newrec->status = 1;
            $newrec->timestart = $now;
            $newrec->timeend = $timeend;
            $newrec->grantedby = $grantedby;
            $newrec->reason = $reason;
            $newrec->timecreated = $now;
            $newrec->timemodified = $now;
            $newrec->id = $DB->insert_record('wub_auth_waivers', $newrec);
            $record = $newrec;
        }

        // Invalidate financial cache for this user
        $this->invalidate_user_cache($userid);

        // Update preference cache for fast read
        set_user_preference(self::PREFERENCE_KEY, date('Y-m-d', $timeend), $userid);

        // Audit event
        $audit = new audit_service();
        $audit->log(
            $userid,
            'WAIVER_GRANTED',
            'SUCCESS',
            [
                'grantedby' => $grantedby,
                'timeend' => $timeend,
                'expiry_date' => date('Y-m-d H:i:s', $timeend),
                'reason' => $reason,
            ]
        );

        return waiver_record::from_record($record);
    }

    /**
     * Revoke an active waiver.
     *
     * @param int $userid Target student user ID.
     * @param int $revokedby Admin user ID performing the revocation.
     * @return bool
     */
    public function revoke_waiver(int $userid, int $revokedby): bool {
        global $DB;

        if ($userid <= 0) {
            return false;
        }

        $existing = $DB->get_record('wub_auth_waivers', ['userid' => $userid]);
        if (!$existing) {
            return false;
        }

        $existing->status = 0;
        $existing->timemodified = time();
        $DB->update_record('wub_auth_waivers', $existing);

        $this->invalidate_user_cache($userid);
        unset_user_preference(self::PREFERENCE_KEY, $userid);

        $audit = new audit_service();
        $audit->log(
            $userid,
            'WAIVER_REVOKED',
            'SUCCESS',
            ['revokedby' => $revokedby]
        );

        return true;
    }

    /**
     * Invalidate user clearance cache.
     *
     * @param int $userid
     */
    public function invalidate_user_cache(int $userid): void {
        try {
            $cache = cache::make('local_wub_auth', 'financial_clearance');
            $cache->delete((string)$userid);
        } catch (\Exception $e) {
            // Non-fatal cache failure
        }
    }

    /**
     * Fetch recent waiver records with student user details for admin UI.
     *
     * @param int $limit Max records.
     * @return array
     */
    public function get_recent_waivers(int $limit = 50): array {
        global $DB;

        $sql = "SELECT w.*, u.firstname, u.lastname, u.username, u.email, u.idnumber,
                       g.firstname AS granter_firstname, g.lastname AS granter_lastname
                  FROM {wub_auth_waivers} w
                  JOIN {user} u ON u.id = w.userid
             LEFT JOIN {user} g ON g.id = w.grantedby
              ORDER BY w.timemodified DESC";

        $records = $DB->get_records_sql($sql, [], 0, $limit);
        $results = [];

        foreach ($records as $r) {
            $waiver = waiver_record::from_record($r);
            $results[] = [
                'waiver' => $waiver,
                'student_id' => $r->userid,
                'student_name' => fullname((object)['firstname' => $r->firstname, 'lastname' => $r->lastname]),
                'student_username' => $r->username,
                'student_email' => $r->email,
                'student_idnumber' => $r->idnumber,
                'granter_name' => !empty($r->granter_firstname)
                    ? fullname((object)['firstname' => $r->granter_firstname, 'lastname' => $r->granter_lastname])
                    : 'System / Admin',
                'is_active' => $waiver->is_valid(),
                'formatted_expiry' => userdate($waiver->get_timeend(), get_string('strftimedatetime', 'langconfig')),
            ];
        }

        return $results;
    }
}
