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
 * Helper library for local_wub_policy with 30-day role-independent persistence.
 *
 * @package    local_wub_policy
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** Cookie name for long-lived 30-day device policy acceptance token. */
define('WUB_POLICY_DEVICE_COOKIE', 'wub_policy_device');

/** Default policy version. */
define('WUB_POLICY_DEFAULT_VERSION', '1.0.0');

/** Default policy expiry in days. */
define('WUB_POLICY_DEFAULT_EXPIRY_DAYS', 30);

/**
 * Get current configured policy version.
 *
 * @return string Version string (e.g. '1.0.0').
 */
function wub_policy_get_version(): string {
    $version = get_config('local_wub_policy', 'policyversion');
    return !empty($version) ? trim((string)$version) : WUB_POLICY_DEFAULT_VERSION;
}

/**
 * Get policy validity duration in seconds (defaults to 30 days).
 *
 * @return int Duration in seconds.
 */
function wub_policy_get_expiry_seconds(): int {
    $days = (int)get_config('local_wub_policy', 'policyexpiry_days');
    if ($days <= 0) {
        $days = WUB_POLICY_DEFAULT_EXPIRY_DAYS;
    }
    return $days * DAYSECS;
}

/**
 * Get device identifier from cookie if present and valid.
 *
 * @return string|null 64-character hex device token, or null if not set.
 */
function wub_policy_get_device_id(): ?string {
    if (!empty($_COOKIE[WUB_POLICY_DEVICE_COOKIE])) {
        $raw = (string)$_COOKIE[WUB_POLICY_DEVICE_COOKIE];
        if (preg_match('/^[a-f0-9]{64}$/i', $raw)) {
            return strtolower($raw);
        }
    }
    return null;
}

/**
 * Get or generate a persistent device token and send 30+ day secure cookie.
 *
 * @return string 64-character hex device token.
 */
function wub_policy_get_or_create_device_id(): string {
    $existing = wub_policy_get_device_id();
    if ($existing !== null) {
        return $existing;
    }

    try {
        $token = bin2hex(random_bytes(32));
    } catch (\Exception $e) {
        $token = hash('sha256', uniqid('wub_policy_', true) . microtime(true) . (string)getremoteaddr());
    }

    // Set cookie valid for 60 days (longer than 30-day acceptance window to survive).
    $expiry = time() + (60 * DAYSECS);
    $secure = is_https();
    $httponly = true;
    $samesite = 'Lax';

    if (!headers_sent()) {
        if (PHP_VERSION_ID >= 70300) {
            setcookie(WUB_POLICY_DEVICE_COOKIE, $token, [
                'expires' => $expiry,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite,
            ]);
        } else {
            setcookie(WUB_POLICY_DEVICE_COOKIE, $token, $expiry, '/; samesite=' . $samesite, '', $secure, $httponly);
        }
    }

    $_COOKIE[WUB_POLICY_DEVICE_COOKIE] = $token;
    return $token;
}

/**
 * Normalize role string.
 *
 * @param string $role
 * @return string
 */
function wub_policy_normalize_role(string $role): string {
    $role = strtolower(trim($role));
    if ($role === 'administrator') {
        return 'admin';
    }
    $valid = ['student', 'teacher', 'admin'];
    return in_array($role, $valid) ? $role : 'student';
}

/**
 * Check if the policy for a specific role has been accepted within the last 30 days
 * for the current policy version.
 *
 * Checks in order:
 * 1. Current PHP session memory cache.
 * 2. Authenticated user ID in mdl_local_wub_policy_accept.
 * 3. Persistent device identifier in mdl_local_wub_policy_accept.
 *
 * @param string $role Role name (student, teacher, admin)
 * @param int $userid Optional user ID (defaults to current $USER->id if logged in)
 * @return bool True if valid 30-day acceptance exists for this specific role and version.
 */
function wub_policy_is_accepted(string $role, int $userid = 0): bool {
    global $DB, $USER, $SESSION;

    $role = wub_policy_normalize_role($role);
    $currentversion = wub_policy_get_version();
    $mintime = time() - wub_policy_get_expiry_seconds();

    if ($userid <= 0 && isloggedin() && !isguestuser()) {
        $userid = (int)$USER->id;
    }

    // 1. Session Memory Cache Check.
    if (isset($SESSION->wub_policy_accepted) && is_array($SESSION->wub_policy_accepted)) {
        if (!empty($SESSION->wub_policy_accepted[$role])) {
            $sessdata = $SESSION->wub_policy_accepted[$role];
            if (is_array($sessdata)) {
                $sesstime = (int)($sessdata['time'] ?? 0);
                $sessver = (string)($sessdata['version'] ?? '');
                if ($sesstime >= $mintime && $sessver === $currentversion) {
                    return true;
                }
            } else if ($sessdata === true) {
                // Fall through to database check for proper timestamp verification.
            }
        }
    }

    // 2. Authenticated User ID Database Check.
    if ($userid > 0) {
        $params = [
            'userid' => $userid,
            'role' => $role,
            'policyversion' => $currentversion,
            'mintime' => $mintime,
        ];
        $sql = "SELECT id, timeaccepted, policyversion FROM {local_wub_policy_accept}
                 WHERE userid = :userid
                   AND role = :role
                   AND policyversion = :policyversion
                   AND timeaccepted >= :mintime
              ORDER BY timeaccepted DESC";
        $records = $DB->get_records_sql($sql, $params, 0, 1);
        if (!empty($records)) {
            $rec = reset($records);
            // Cache in session.
            if (!isset($SESSION->wub_policy_accepted) || !is_array($SESSION->wub_policy_accepted)) {
                $SESSION->wub_policy_accepted = [];
            }
            $SESSION->wub_policy_accepted[$role] = [
                'time' => (int)$rec->timeaccepted,
                'version' => (string)$rec->policyversion,
            ];
            return true;
        }
    }

    // 3. Persistent Device Token Database Check (Survives logout and new browser sessions).
    $deviceid = wub_policy_get_device_id();
    if (!empty($deviceid)) {
        $params = [
            'deviceid' => $deviceid,
            'role' => $role,
            'policyversion' => $currentversion,
            'mintime' => $mintime,
        ];
        $sql = "SELECT id, timeaccepted, policyversion, userid FROM {local_wub_policy_accept}
                 WHERE deviceidentifier = :deviceid
                   AND role = :role
                   AND policyversion = :policyversion
                   AND timeaccepted >= :mintime
              ORDER BY timeaccepted DESC";
        $records = $DB->get_records_sql($sql, $params, 0, 1);
        if (!empty($records)) {
            $rec = reset($records);

            // If user is currently logged in but this record was created pre-login, link userid.
            if ($userid > 0 && (int)$rec->userid === 0) {
                $DB->set_field('local_wub_policy_accept', 'userid', $userid, ['id' => $rec->id]);
            }

            // Cache in session.
            if (!isset($SESSION->wub_policy_accepted) || !is_array($SESSION->wub_policy_accepted)) {
                $SESSION->wub_policy_accepted = [];
            }
            $SESSION->wub_policy_accepted[$role] = [
                'time' => (int)$rec->timeaccepted,
                'version' => (string)$rec->policyversion,
            ];
            return true;
        }
    }

    return false;
}

/**
 * Record explicit policy acceptance for a specific role in database, device cookie, and session.
 *
 * @param string $role Role name (student, teacher, admin)
 * @param int $userid Optional user ID (defaults to current $USER->id if logged in)
 */
function wub_policy_record_acceptance(string $role, int $userid = 0): void {
    global $DB, $USER, $SESSION;

    $role = wub_policy_normalize_role($role);
    $currentversion = wub_policy_get_version();
    $now = time();

    if ($userid <= 0 && isloggedin() && !isguestuser()) {
        $userid = (int)$USER->id;
    }

    $deviceid = wub_policy_get_or_create_device_id();
    $userip = getremoteaddr();
    $useragent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    // 1. Database Persistence.
    $existing = null;

    if ($userid > 0) {
        $existing = $DB->get_record('local_wub_policy_accept', [
            'userid' => $userid,
            'role' => $role,
            'policyversion' => $currentversion,
        ]);
    }

    if (!$existing && !empty($deviceid)) {
        $existing = $DB->get_record('local_wub_policy_accept', [
            'deviceidentifier' => $deviceid,
            'role' => $role,
            'policyversion' => $currentversion,
        ]);
    }

    if ($existing) {
        $record = new \stdClass();
        $record->id = $existing->id;
        $record->timeaccepted = $now;
        $record->userip = $userip;
        $record->useragent = $useragent;
        if ($userid > 0) {
            $record->userid = $userid;
        }
        if (!empty($deviceid)) {
            $record->deviceidentifier = $deviceid;
        }
        $DB->update_record('local_wub_policy_accept', $record);
    } else {
        $record = new \stdClass();
        $record->userid = $userid > 0 ? $userid : 0;
        $record->deviceidentifier = $deviceid;
        $record->role = $role;
        $record->policyversion = $currentversion;
        $record->timeaccepted = $now;
        $record->userip = $userip;
        $record->useragent = $useragent;
        $DB->insert_record('local_wub_policy_accept', $record);
    }

    // 2. Session Cache Update.
    if (!isset($SESSION->wub_policy_accepted) || !is_array($SESSION->wub_policy_accepted)) {
        $SESSION->wub_policy_accepted = [];
    }
    $SESSION->wub_policy_accepted[$role] = [
        'time' => $now,
        'version' => $currentversion,
        'device' => $deviceid,
    ];
}

/**
 * Bind device policy acceptance to authenticated user upon login.
 *
 * @param int $userid The authenticated user ID.
 * @param string $role The intended role (optional).
 */
function wub_policy_bind_user_acceptance(int $userid, string $role = ''): void {
    global $DB;

    if ($userid <= 0) {
        return;
    }

    $deviceid = wub_policy_get_device_id();
    if (empty($deviceid)) {
        return;
    }

    // Link all pre-login acceptances on this device to the authenticated user.
    $records = $DB->get_records('local_wub_policy_accept', [
        'deviceidentifier' => $deviceid,
        'userid' => 0,
    ]);

    foreach ($records as $rec) {
        // Check if user already has a record for this role and version.
        $userrec = $DB->get_record('local_wub_policy_accept', [
            'userid' => $userid,
            'role' => $rec->role,
            'policyversion' => $rec->policyversion,
        ]);

        if ($userrec) {
            // Keep the latest timestamp.
            if ($rec->timeaccepted > $userrec->timeaccepted) {
                $userrec->timeaccepted = $rec->timeaccepted;
                $userrec->deviceidentifier = $deviceid;
                $DB->update_record('local_wub_policy_accept', $userrec);
            }
            $DB->delete_records('local_wub_policy_accept', ['id' => $rec->id]);
        } else {
            $DB->set_field('local_wub_policy_accept', 'userid', $userid, ['id' => $rec->id]);
        }
    }
}
