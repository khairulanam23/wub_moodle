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
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Institutional Policy Acknowledgement Service.
 *
 * Enforces 30-day role-based policy acknowledgement across 20 university policies
 * with dual persistence (DB + 60-day secure HttpOnly SameSite=Lax device cookie).
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class policy_service {
    public const DEVICE_COOKIE = 'wub_policy_device';
    public const DEFAULT_VERSION = '1.0.0';
    public const DEFAULT_EXPIRY_DAYS = 30;

    /**
     * Get active policy version.
     *
     * @return string
     */
    public function get_version(): string {
        $ver = get_config('local_wub_auth', 'policy_version');
        return !empty($ver) ? trim((string)$ver) : self::DEFAULT_VERSION;
    }

    /**
     * Get policy validity duration in seconds (defaults to 30 days).
     *
     * @return int Duration in seconds.
     */
    public function get_expiry_seconds(): int {
        $days = (int)get_config('local_wub_auth', 'policy_expiry_days');
        if ($days <= 0) {
            $days = self::DEFAULT_EXPIRY_DAYS;
        }
        return $days * DAYSECS;
    }

    /**
     * Check if policy acceptance is required site-wide.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return (bool)get_config('local_wub_auth', 'policy_enabled');
    }

    /**
     * Check if user or current device has valid policy acceptance for a specific role.
     *
     * @param string $role Role name (student, teacher, admin).
     * @param int $userid Moodle user ID (defaults to current $USER->id).
     * @return bool
     */
    public function is_policy_accepted(string $role, int $userid = 0): bool {
        global $DB, $USER;

        if (!$this->is_enabled()) {
            return true;
        }

        $cleanrole = $this->normalize_role($role);
        $currentversion = $this->get_version();
        $mintime = time() - $this->get_expiry_seconds();

        if ($userid <= 0 && isloggedin() && !isguestuser()) {
            $userid = (int)$USER->id;
        }

        // 1. Authenticated User Check in Database
        if ($userid > 0) {
            $params = [
                'userid' => $userid,
                'role' => $cleanrole,
                'version' => $currentversion,
                'mintime' => $mintime,
            ];
            $sql = "SELECT id, timeaccepted
                      FROM {wub_auth_policy_accept}
                     WHERE userid = :userid
                       AND role = :role
                       AND policyversion = :version
                       AND timeaccepted >= :mintime
                  ORDER BY timeaccepted DESC";
            if ($DB->record_exists_sql($sql, $params)) {
                return true;
            }
        }

        // 2. Persistent Device Cookie Token Check (Survives logout)
        $deviceid = $this->get_device_id();
        if (!empty($deviceid)) {
            $params = [
                'deviceid' => $deviceid,
                'role' => $cleanrole,
                'version' => $currentversion,
                'mintime' => $mintime,
            ];
            $sql = "SELECT id, timeaccepted, userid
                      FROM {wub_auth_policy_accept}
                     WHERE deviceidentifier = :deviceid
                       AND role = :role
                       AND policyversion = :version
                       AND timeaccepted >= :mintime
                  ORDER BY timeaccepted DESC";
            $records = $DB->get_records_sql($sql, $params, 0, 1);
            if (!empty($records)) {
                $rec = reset($records);
                // If user is currently logged in, link this pre-login acceptance to their account
                if ($userid > 0 && (int)$rec->userid === 0) {
                    $DB->set_field('wub_auth_policy_accept', 'userid', $userid, ['id' => $rec->id]);
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Record explicit policy acceptance in database and device cookie.
     *
     * @param string $role Role name.
     * @param int $userid Moodle user ID.
     */
    public function record_acceptance(string $role, int $userid = 0): void {
        global $DB, $USER;

        $cleanrole = $this->normalize_role($role);
        $currentversion = $this->get_version();
        $now = time();

        if ($userid <= 0 && isloggedin() && !isguestuser()) {
            $userid = (int)$USER->id;
        }

        $deviceid = $this->get_or_create_device_id();
        $userip = getremoteaddr();
        $useragent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        // Check if an existing record exists for this user / device
        $existing = null;
        if ($userid > 0) {
            $existing = $DB->get_record('wub_auth_policy_accept', [
                'userid' => $userid,
                'role' => $cleanrole,
                'policyversion' => $currentversion,
            ]);
        }

        if (!$existing && !empty($deviceid)) {
            $existing = $DB->get_record('wub_auth_policy_accept', [
                'deviceidentifier' => $deviceid,
                'role' => $cleanrole,
                'policyversion' => $currentversion,
            ]);
        }

        if ($existing) {
            $existing->timeaccepted = $now;
            $existing->userip = $userip;
            $existing->useragent = $useragent;
            if ($userid > 0) {
                $existing->userid = $userid;
            }
            if (!empty($deviceid)) {
                $existing->deviceidentifier = $deviceid;
            }
            $DB->update_record('wub_auth_policy_accept', $existing);
        } else {
            $record = new stdClass();
            $record->userid = $userid > 0 ? $userid : 0;
            $record->deviceidentifier = $deviceid;
            $record->role = $cleanrole;
            $record->policyversion = $currentversion;
            $record->timeaccepted = $now;
            $record->userip = $userip;
            $record->useragent = $useragent;
            $DB->insert_record('wub_auth_policy_accept', $record);
        }

        // Audit log
        $audit = new audit_service();
        $audit->log(
            $userid,
            'POLICY_ACCEPTED',
            'SUCCESS',
            [
                'role' => $cleanrole,
                'version' => $currentversion,
                'device_id' => substr($deviceid, 0, 12) . '...',
            ]
        );
    }

    /**
     * Bind pre-login device acceptances to newly authenticated user.
     *
     * @param int $userid Authenticated user ID.
     * @param string $role Optional role string.
     */
    public function bind_device_acceptance(int $userid, string $role = ''): void {
        global $DB;

        if ($userid <= 0) {
            return;
        }

        $deviceid = $this->get_device_id();
        if (empty($deviceid)) {
            return;
        }

        $records = $DB->get_records('wub_auth_policy_accept', [
            'deviceidentifier' => $deviceid,
            'userid' => 0,
        ]);

        foreach ($records as $rec) {
            $userrec = $DB->get_record('wub_auth_policy_accept', [
                'userid' => $userid,
                'role' => $rec->role,
                'policyversion' => $rec->policyversion,
            ]);

            if ($userrec) {
                if ($rec->timeaccepted > $userrec->timeaccepted) {
                    $userrec->timeaccepted = $rec->timeaccepted;
                    $userrec->deviceidentifier = $deviceid;
                    $DB->update_record('wub_auth_policy_accept', $userrec);
                }
                $DB->delete_records('wub_auth_policy_accept', ['id' => $rec->id]);
            } else {
                $DB->set_field('wub_auth_policy_accept', 'userid', $userid, ['id' => $rec->id]);
            }
        }
    }

    /**
     * Extract valid 64-char hex device token from cookie.
     *
     * @return string|null
     */
    public function get_device_id(): ?string {
        if (!empty($_COOKIE[self::DEVICE_COOKIE])) {
            $raw = (string)$_COOKIE[self::DEVICE_COOKIE];
            if (preg_match('/^[a-f0-9]{64}$/i', $raw)) {
                return strtolower($raw);
            }
        }
        return null;
    }

    /**
     * Get or create a 64-character persistent device token cookie.
     *
     * @return string
     */
    public function get_or_create_device_id(): string {
        $existing = $this->get_device_id();
        if ($existing !== null) {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        $expiry = time() + (60 * DAYSECS); // 60 days
        $secure = is_https();

        if (!headers_sent()) {
            setcookie(self::DEVICE_COOKIE, $token, [
                'expires' => $expiry,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        $_COOKIE[self::DEVICE_COOKIE] = $token;
        return $token;
    }

    /**
     * Normalize role name.
     *
     * @param string $role
     * @return string
     */
    public function normalize_role(string $role): string {
        $clean = strtolower(trim($role));
        if ($clean === 'administrator') {
            return 'admin';
        }
        if ($clean === 'faculty' || $clean === 'instructor') {
            return 'teacher';
        }
        if (in_array($clean, ['student', 'teacher', 'admin'])) {
            return $clean;
        }
        return 'student';
    }

    /**
     * Get 20 university policies structured in 4 categories.
     *
     * @return array
     */
    public function get_categories_and_policies(): array {
        return [
            [
                'id' => 'cat-1',
                'title' => get_string('category_account_security', 'local_wub_auth'),
                'icon' => 'fa-shield-halved',
                'policies' => [
                    ['num' => 1, 'title' => get_string('policy_1_title', 'local_wub_auth'), 'content' => get_string('policy_1_content', 'local_wub_auth')],
                    ['num' => 2, 'title' => get_string('policy_2_title', 'local_wub_auth'), 'content' => get_string('policy_2_content', 'local_wub_auth')],
                    ['num' => 3, 'title' => get_string('policy_3_title', 'local_wub_auth'), 'content' => get_string('policy_3_content', 'local_wub_auth')],
                    ['num' => 4, 'title' => get_string('policy_4_title', 'local_wub_auth'), 'content' => get_string('policy_4_content', 'local_wub_auth')],
                ],
            ],
            [
                'id' => 'cat-2',
                'title' => get_string('category_academic_assessments', 'local_wub_auth'),
                'icon' => 'fa-graduation-cap',
                'policies' => [
                    ['num' => 5, 'title' => get_string('policy_5_title', 'local_wub_auth'), 'content' => get_string('policy_5_content', 'local_wub_auth')],
                    ['num' => 6, 'title' => get_string('policy_6_title', 'local_wub_auth'), 'content' => get_string('policy_6_content', 'local_wub_auth')],
                    ['num' => 7, 'title' => get_string('policy_7_title', 'local_wub_auth'), 'content' => get_string('policy_7_content', 'local_wub_auth')],
                    ['num' => 8, 'title' => get_string('policy_8_title', 'local_wub_auth'), 'content' => get_string('policy_8_content', 'local_wub_auth')],
                ],
            ],
            [
                'id' => 'cat-3',
                'title' => get_string('category_conduct_communication', 'local_wub_auth'),
                'icon' => 'fa-comments',
                'policies' => [
                    ['num' => 9, 'title' => get_string('policy_9_title', 'local_wub_auth'), 'content' => get_string('policy_9_content', 'local_wub_auth')],
                    ['num' => 10, 'title' => get_string('policy_10_title', 'local_wub_auth'), 'content' => get_string('policy_10_content', 'local_wub_auth')],
                    ['num' => 11, 'title' => get_string('policy_11_title', 'local_wub_auth'), 'content' => get_string('policy_11_content', 'local_wub_auth')],
                    ['num' => 12, 'title' => get_string('policy_12_title', 'local_wub_auth'), 'content' => get_string('policy_12_content', 'local_wub_auth')],
                ],
            ],
            [
                'id' => 'cat-4',
                'title' => get_string('category_ip_privacy_governance', 'local_wub_auth'),
                'icon' => 'fa-scale-balanced',
                'policies' => [
                    ['num' => 13, 'title' => get_string('policy_13_title', 'local_wub_auth'), 'content' => get_string('policy_13_content', 'local_wub_auth')],
                    ['num' => 14, 'title' => get_string('policy_14_title', 'local_wub_auth'), 'content' => get_string('policy_14_content', 'local_wub_auth')],
                    ['num' => 15, 'title' => get_string('policy_15_title', 'local_wub_auth'), 'content' => get_string('policy_15_content', 'local_wub_auth')],
                    ['num' => 16, 'title' => get_string('policy_16_title', 'local_wub_auth'), 'content' => get_string('policy_16_content', 'local_wub_auth')],
                    ['num' => 17, 'title' => get_string('policy_17_title', 'local_wub_auth'), 'content' => get_string('policy_17_content', 'local_wub_auth')],
                    ['num' => 18, 'title' => get_string('policy_18_title', 'local_wub_auth'), 'content' => get_string('policy_18_content', 'local_wub_auth')],
                    ['num' => 19, 'title' => get_string('policy_19_title', 'local_wub_auth'), 'content' => get_string('policy_19_content', 'local_wub_auth')],
                    ['num' => 20, 'title' => get_string('policy_20_title', 'local_wub_auth'), 'content' => get_string('policy_20_content', 'local_wub_auth')],
                ],
            ],
        ];
    }
}
