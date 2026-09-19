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

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Authentication and Access Control Audit Logger.
 *
 * Persists structured, privacy-conscious audit records to mdl_wub_auth_audit.
 * Strictly redacts passwords, session IDs, and sensitive tokens.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audit_service {
    /**
     * Record an audit event.
     *
     * @param int $userid Moodle user ID or 0.
     * @param string $action Action name (e.g. LOGIN_SUCCESS, ACCESS_RESTRICTED).
     * @param string $status Result status (SUCCESS, FAILURE, RESTRICTED).
     * @param array $details Safe non-secret contextual metadata.
     * @param string $rawidentifier Optional identifier to hash for privacy.
     * @return int Created audit record ID.
     */
    public function log(
        int $userid,
        string $action,
        string $status,
        array $details = [],
        string $rawidentifier = ''
    ): int {
        global $DB;

        // Scrub any sensitive keys from details
        $scrubbed = $this->scrub_metadata($details);

        $record = new stdClass();
        $record->userid = max(0, $userid);
        $record->action = substr(trim($action), 0, 50);
        $record->identifier_hash = !empty($rawidentifier) ? hash('sha256', strtolower(trim($rawidentifier))) : null;
        $record->status = substr(trim($status), 0, 30);
        $record->details = !empty($scrubbed) ? json_encode($scrubbed, JSON_UNESCAPED_SLASHES) : null;
        $record->userip = substr(getremoteaddr(), 0, 45);
        $record->timecreated = time();

        try {
            return (int)$DB->insert_record('wub_auth_audit', $record);
        } catch (\Exception $e) {
            // Fail safely: audit logging error must never break authentication
            return 0;
        }
    }

    /**
     * Recursively remove sensitive keys from metadata array.
     *
     * @param array $data
     * @return array
     */
    protected function scrub_metadata(array $data): array {
        $blacklisted = [
            'password', 'pass', 'pw', 'token', 'key', 'secret',
            'api_key', 'session_id', 'sesskey', 'cookie', 'auth'
        ];

        $cleaned = [];
        foreach ($data as $k => $v) {
            $lower = strtolower((string)$k);
            if (in_array($lower, $blacklisted, true)) {
                $cleaned[$k] = '***REDACTED***';
            } else if (is_array($v)) {
                $cleaned[$k] = $this->scrub_metadata($v);
            } else {
                $cleaned[$k] = $v;
            }
        }
        return $cleaned;
    }
}
