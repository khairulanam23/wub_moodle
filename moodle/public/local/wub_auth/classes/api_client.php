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

namespace local_wub_auth;

use local_wub_auth\exception\ums_authentication_exception;
use local_wub_auth\exception\ums_connection_exception;
use local_wub_auth\exception\ums_exception;
use local_wub_auth\exception\ums_not_found_exception;
use local_wub_auth\exception\ums_response_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Robust UMS REST Client for local_wub_auth.
 *
 * Implements Digest Auth, dynamic X-API-KEY placement, exponential backoff retries,
 * credential scrubbing, and configuration inheritance from local_bulk_enrolment.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_client {
    public const MAX_RETRIES = 3;
    public const INITIAL_BACKOFF_US = 200000; // 200ms

    /**
     * Get a plugin configuration value with fallback to local_bulk_enrolment.
     *
     * @param string $name Configuration setting name.
     * @param mixed $default Default value.
     * @return mixed
     */
    public function get_config(string $name, $default = '') {
        $val = get_config('local_wub_auth', $name);
        if ($val === false || $val === null || $val === '') {
            $val = get_config('local_bulk_enrolment', $name);
        }
        return ($val !== false && $val !== null && $val !== '') ? $val : $default;
    }

    /**
     * Get normalized base URL.
     *
     * @return string
     */
    public function get_base_url(): string {
        $url = trim((string)$this->get_config('base_url', 'https://api.e-dhrubo.com'));
        $parsed = parse_url($url);
        if ($parsed && isset($parsed['scheme']) && isset($parsed['host'])) {
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            return $parsed['scheme'] . '://' . $parsed['host'] . $port;
        }
        return 'https://api.e-dhrubo.com';
    }

    /**
     * Check if UMS credentials are configured.
     *
     * @return bool
     */
    public function is_configured(): bool {
        $user = trim((string)$this->get_config('api_username'));
        $pass = trim((string)$this->get_config('api_password'));
        $key  = trim((string)$this->get_config('api_key'));
        return ($user !== '' && $pass !== '' && $key !== '');
    }

    /**
     * Scrub sensitive credentials from strings before logging or exception messages.
     *
     * @param string $input
     * @return string
     */
    public function scrub(string $input): string {
        $secrets = array_filter([
            trim((string)$this->get_config('api_key')),
            trim((string)$this->get_config('api_password')),
            trim((string)$this->get_config('api_username')),
        ], fn($s) => strlen($s) > 2);

        foreach ($secrets as $secret) {
            $input = str_replace($secret, '***REDACTED***', $input);
            $input = str_replace(urlencode($secret), '***REDACTED***', $input);
        }
        return $input;
    }

    /**
     * Execute an HTTP request against UMS with retry policy.
     *
     * @param string $endpoint Path relative to base URL.
     * @param string $method HTTP method (GET or POST).
     * @param array $postdata Data for POST requests.
     * @return array Decoded JSON array.
     * @throws ums_exception
     */
    public function request(string $endpoint, string $method = 'GET', array $postdata = []): array {
        global $CFG;

        if (!$this->is_configured()) {
            throw new ums_connection_exception('UMS credentials are not configured.', 0);
        }

        $base = $this->get_base_url();
        $username = (string)$this->get_config('api_username');
        $password = (string)$this->get_config('api_password');
        $apikey   = (string)$this->get_config('api_key');
        $timeout  = (int)$this->get_config('timeout', 15);
        $connecttimeout = (int)$this->get_config('connect_timeout', 5);
        $sslverify = (bool)$this->get_config('ssl_verify', 1);

        $url = $base . '/' . ltrim($endpoint, '/');
        $method = strtoupper($method);

        // GET requests: pass X-API-KEY in query string and headers
        if ($method === 'GET') {
            $sep = (strpos($url, '?') !== false) ? '&' : '?';
            $url .= $sep . 'X-API-KEY=' . urlencode($apikey);
        } else if ($method === 'POST') {
            // POST requests: pass X-API-KEY in form body and headers
            if (!isset($postdata['X-API-KEY'])) {
                $postdata['X-API-KEY'] = $apikey;
            }
        }

        $headers = [
            'Accept: application/json',
            'X-API-KEY: ' . $apikey,
            'User-Agent: WUB-Moodle-Auth/1.0',
        ];

        $attempt = 0;
        $backoff = self::INITIAL_BACKOFF_US;
        $lasterror = '';
        $lasthttpcode = 0;

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connecttimeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslverify);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslverify ? 2 : 0);

            if (!empty($CFG->pathtocertificate) && file_exists($CFG->pathtocertificate)) {
                curl_setopt($ch, CURLOPT_CAINFO, $CFG->pathtocertificate);
            }

            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
            }

            $raw = curl_exec($ch);
            $errno = curl_errno($ch);
            $errstr = curl_error($ch);
            $httpcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Transport failure (network timeout, DNS failure)
            if ($errno !== 0) {
                $lasterror = "cURL error $errno: $errstr";
                $lasthttpcode = 0;
                if ($attempt < self::MAX_RETRIES) {
                    usleep($backoff);
                    $backoff *= 2;
                    continue;
                }
                throw new ums_connection_exception($this->scrub($lasterror), 0);
            }

            // HTTP 404: Not found
            if ($httpcode === 404) {
                throw new ums_not_found_exception("Resource not found at $endpoint", 404);
            }

            // HTTP 401 / 403: Authentication or key rejected
            if ($httpcode === 401 || $httpcode === 403) {
                throw new ums_authentication_exception("UMS rejected credentials (HTTP $httpcode)", $httpcode);
            }

            // HTTP 5xx: Server errors (retryable)
            if ($httpcode >= 500) {
                $lasterror = "UMS server error (HTTP $httpcode)";
                $lasthttpcode = $httpcode;
                if ($attempt < self::MAX_RETRIES) {
                    usleep($backoff);
                    $backoff *= 2;
                    continue;
                }
                throw new ums_connection_exception($this->scrub($lasterror), $httpcode);
            }

            // Decode response
            $decoded = json_decode((string)$raw, true);
            if (!is_array($decoded)) {
                throw new ums_response_exception('UMS response was not valid JSON.', $httpcode);
            }

            return $decoded;
        }

        throw new ums_connection_exception($this->scrub($lasterror), $lasthttpcode);
    }

    /**
     * Fetch student payment dues and installment details.
     * API: GET /students/student_payment_info/{username}
     *
     * @param string $username Clean student ID/username (e.g. 0326745530).
     * @return array|null Payment info array or null if not found.
     */
    public function get_student_payment_info(string $username): ?array {
        $short = explode('@', trim($username))[0];
        try {
            $data = $this->request('students/student_payment_info/' . urlencode($short), 'GET');
            if (isset($data['message']) && is_array($data['message'])) {
                return $data['message'];
            }
            if (isset($data['data']) && is_array($data['data'])) {
                return $data['data'];
            }
            return $data;
        } catch (ums_not_found_exception $e) {
            return null;
        }
    }

    /**
     * Fetch detailed student record by email or registration number.
     * API: GET /students/email_number_wise_student_details/{identifier}
     *
     * @param string $identifier Student username, email, or registration ID.
     * @return array|null Student details array or null.
     */
    public function get_student_details(string $identifier): ?array {
        $short = trim($identifier);
        try {
            $data = $this->request('students/email_number_wise_student_details/' . urlencode($short), 'GET');
            if (isset($data['message'][0]) && is_array($data['message'][0])) {
                return $data['message'][0];
            }
            if (isset($data['data'][0]) && is_array($data['data'][0])) {
                return $data['data'][0];
            }
            return null;
        } catch (ums_not_found_exception $e) {
            return null;
        }
    }

    /**
     * Probe live UMS connectivity for diagnostics.
     *
     * @return array ['success' => bool, 'programs_count' => int, 'message' => string]
     */
    public function probe_connection(): array {
        try {
            $res = $this->request('students/programs', 'GET');
            $count = is_array($res) ? count($res) : 0;
            return [
                'success' => true,
                'programs_count' => $count,
                'message' => "UMS connection active. $count academic programs retrieved.",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'programs_count' => 0,
                'message' => $this->scrub($e->getMessage()),
            ];
        }
    }
}
