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

use local_wub_auth\api_client;
use local_wub_auth\exception\ums_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * UMS Fallback Authenticator.
 *
 * Verifies credentials against the authoritative UMS backend when local Moodle
 * authentication fails.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ums_authenticator {
    protected api_client $apiclient;

    public function __construct(?api_client $apiclient = null) {
        $this->apiclient = $apiclient ?? new api_client();
    }

    /**
     * Check whether UMS fallback authentication is enabled.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return (bool)$this->apiclient->get_config('enable_ums_fallback', 1)
            && $this->apiclient->is_configured();
    }

    /**
     * Authenticate student credentials against UMS.
     *
     * @param string $identifier Student ID or email.
     * @param string $password Submitted plaintext password.
     * @param array|null $prefetcheddata Optional pre-fetched UMS student record.
     * @return array ['authenticated' => bool, 'outage' => bool, 'ums_data' => array|null, 'reason' => string]
     */
    public function authenticate(string $identifier, string $password, ?array $prefetcheddata = null): array {
        if (!$this->is_enabled()) {
            return [
                'authenticated' => false,
                'outage' => false,
                'ums_data' => null,
                'reason' => 'UMS fallback authentication is disabled or not configured.',
            ];
        }

        $cleanid = explode('@', trim($identifier))[0];
        $digits = preg_replace('/[^0-9]/', '', $cleanid);
        $lookupid = !empty($digits) ? $digits : $cleanid;

        $studentdata = $prefetcheddata;
        if (empty($studentdata)) {
            try {
                $studentdata = $this->apiclient->get_student_details($lookupid);
                if (!$studentdata && $lookupid !== trim($identifier)) {
                    $studentdata = $this->apiclient->get_student_details(trim($identifier));
                }
            } catch (ums_exception $e) {
                return [
                    'authenticated' => false,
                    'outage' => true,
                    'ums_data' => null,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        if (empty($studentdata)) {
            return [
                'authenticated' => false,
                'outage' => false,
                'ums_data' => null,
                'reason' => 'Student record not found in UMS.',
            ];
        }

        // UMS stores the MD5 hash of the student password
        $umshash = (string)($studentdata['password'] ?? '');
        $expectedhash = md5($password);

        $matched = false;
        if (!empty($umshash) && hash_equals($umshash, $expectedhash)) {
            $matched = true;
        }

        // Support initial password convention: username / student ID as initial password
        if (!$matched && empty($umshash)) {
            if ($password === $lookupid || $password === trim($identifier)) {
                $matched = true;
            }
        }

        if ($matched) {
            return [
                'authenticated' => true,
                'outage' => false,
                'ums_data' => $studentdata,
                'reason' => 'Credentials verified against UMS.',
            ];
        }

        return [
            'authenticated' => false,
            'outage' => false,
            'ums_data' => null,
            'reason' => 'Invalid UMS password.',
        ];
    }
}
