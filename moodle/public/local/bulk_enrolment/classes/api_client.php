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
 * UMS REST client: transport (Digest auth + X-API-KEY, retries, timeouts, TLS) and the five documented endpoints.
 *
 * Contract verified live on 2026-09-14 (see README "Service contract"):
 *  #1 POST /students/multiple_username_wise_std_details   body: email=<usernames csv>, X-API-KEY in body
 *  #2 GET  /students/programs                              X-API-KEY in query
 *  #3 GET  /students/batches/{program_id}                  X-API-KEY in query
 *  #4 GET  /students/enroll_student_list_program_batch_wise/{program_id}/{batch_title|0}   X-API-KEY in query
 *  #5 POST /students/reg_id_wise_multi_student_info        body: ids=<usernames csv>, X-API-KEY in body
 * For POST endpoints the key MUST be in the body (query placement is rejected with HTTP 403).
 * HTTP 404 means "no records for these identifiers" and is raised as ums_not_found_exception.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment;

use cache;
use local_bulk_enrolment\exception\ums_authentication_exception;
use local_bulk_enrolment\exception\ums_configuration_exception;
use local_bulk_enrolment\exception\ums_connection_exception;
use local_bulk_enrolment\exception\ums_exception;
use local_bulk_enrolment\exception\ums_not_found_exception;
use local_bulk_enrolment\exception\ums_response_exception;
use local_bulk_enrolment\model\ums_batch;
use local_bulk_enrolment\model\ums_program;
use local_bulk_enrolment\model\ums_student;
use local_bulk_enrolment\model\ums_student_details;

/**
 * UMS API client.
 */
class api_client {
    /** @var string */
    public const ENDPOINT_STUDENT_DETAILS = '/students/multiple_username_wise_std_details';
    /** @var string */
    public const ENDPOINT_PROGRAMS = '/students/programs';
    /** @var string */
    public const ENDPOINT_BATCHES = '/students/batches';
    /** @var string */
    public const ENDPOINT_ROSTER = '/students/enroll_student_list_program_batch_wise';
    /** @var string */
    public const ENDPOINT_USER_SYNC = '/students/reg_id_wise_multi_student_info';

    /** @var string Key in query string (GET endpoints). */
    public const KEY_PLACEMENT_QUERY = 'query';
    /** @var string Key in form body (POST endpoints). */
    public const KEY_PLACEMENT_BODY = 'body';

    /** @var string Batch parameter that returns every active student of a program. */
    public const BATCH_ALL = '0';

    /** @var int Maximum attempts for transient failures. */
    public const MAX_RETRIES = 3;
    /** @var int Initial backoff (microseconds), doubled per attempt. */
    public const INITIAL_BACKOFF_US = 250000;
    /** @var int Maximum identifiers per POST lookup. */
    public const LOOKUP_CHUNK = 50;

    /**
     * Whether the integration is enabled and fully configured.
     *
     * @return bool
     */
    public function is_configured(): bool {
        try {
            $this->validate_configuration();
            return true;
        } catch (ums_configuration_exception $e) {
            return false;
        }
    }

    /**
     * Throw when the integration cannot be used.
     *
     * @throws ums_configuration_exception
     */
    public function validate_configuration(): void {
        if (!(bool)get_config('local_bulk_enrolment', 'enabled')) {
            throw new ums_configuration_exception('error_not_enabled');
        }
        $baseurl = $this->get_base_url();
        if ($baseurl === '') {
            throw new ums_configuration_exception('error_config_missing', 'Base URL is empty or invalid.');
        }
        foreach (['api_username', 'api_password', 'api_key'] as $key) {
            if (trim((string)get_config('local_bulk_enrolment', $key)) === '') {
                throw new ums_configuration_exception('error_config_missing', 'Credentials or API key missing.');
            }
        }
    }

    /**
     * Origin (scheme://host[:port]) derived from the configured base URL. Any path is ignored because
     * every endpoint path is absolute from the UMS root.
     *
     * @return string Empty string when invalid.
     */
    public function get_base_url(): string {
        $raw = trim((string)get_config('local_bulk_enrolment', 'base_url'));
        if ($raw === '') {
            return '';
        }
        $p = parse_url($raw);
        if (empty($p['scheme']) || empty($p['host']) || !in_array(strtolower($p['scheme']), ['http', 'https'], true)) {
            return '';
        }
        return strtolower($p['scheme']) . '://' . $p['host'] . (!empty($p['port']) ? ':' . $p['port'] : '');
    }

    /**
     * Remove the API key and credentials from any text that might reach logs or the UI.
     *
     * @param string $text
     * @return string
     */
    protected function scrub(string $text): string {
        foreach (['api_key', 'api_password', 'api_username'] as $key) {
            $val = (string)get_config('local_bulk_enrolment', $key);
            if ($val !== '') {
                $text = str_replace($val, '[redacted]', $text);
            }
        }
        return $text;
    }

    /**
     * Perform one HTTP request (with retries for transport errors and 5xx) and decode JSON.
     *
     * @param string $method GET or POST.
     * @param string $endpointpath Absolute endpoint path.
     * @param array $params Query (GET) or form fields (POST).
     * @param string $keyplacement KEY_PLACEMENT_QUERY or KEY_PLACEMENT_BODY.
     * @param int $attempt Internal retry counter.
     * @return mixed Decoded JSON (object or array).
     * @throws ums_exception
     */
    public function request(string $method, string $endpointpath, array $params = [],
            string $keyplacement = self::KEY_PLACEMENT_QUERY, int $attempt = 1): mixed {
        global $CFG;

        $this->validate_configuration();

        $username = (string)get_config('local_bulk_enrolment', 'api_username');
        $password = (string)get_config('local_bulk_enrolment', 'api_password');
        $apikey = (string)get_config('local_bulk_enrolment', 'api_key');
        $connecttimeout = max(1, (int)(get_config('local_bulk_enrolment', 'connect_timeout') ?: 10));
        $timeout = max(5, (int)(get_config('local_bulk_enrolment', 'timeout') ?: 30));
        $sslverify = (bool)get_config('local_bulk_enrolment', 'ssl_verify');

        $url = $this->get_base_url() . '/' . ltrim($endpointpath, '/');
        if ($keyplacement === self::KEY_PLACEMENT_QUERY) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'X-API-KEY=' . urlencode($apikey);
        } else {
            $params['X-API-KEY'] = $apikey;
        }

        $ch = curl_init();
        if ($ch === false) {
            throw new ums_connection_exception('Failed to initialise cURL.');
        }
        $headers = ['Accept: application/json'];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $connecttimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'WUB-Moodle-UMS-Client/3.0',
            CURLOPT_SSL_VERIFYPEER => $sslverify,
            CURLOPT_SSL_VERIFYHOST => $sslverify ? 2 : 0,
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST | CURLAUTH_BASIC,
            CURLOPT_USERPWD => "$username:$password",
        ]);
        if (!empty($CFG->pathtocertificate) && file_exists($CFG->pathtocertificate)) {
            curl_setopt($ch, CURLOPT_CAINFO, $CFG->pathtocertificate);
        }
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            if (!empty($params)) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
            }
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = $this->scrub((string)curl_error($ch));
        $httpcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            $istimeout = ($errno === CURLE_OPERATION_TIMEDOUT);
            if ($attempt < self::MAX_RETRIES) {
                usleep(self::INITIAL_BACKOFF_US * (2 ** ($attempt - 1)));
                return $this->request($method, $endpointpath, $params, $keyplacement, $attempt + 1);
            }
            throw new ums_connection_exception($error ?: "cURL error {$errno}", $istimeout, $timeout);
        }
        if ($httpcode === 401 || $httpcode === 403) {
            throw new ums_authentication_exception($httpcode, "Access denied at {$endpointpath}");
        }
        if ($httpcode >= 500) {
            if ($attempt < self::MAX_RETRIES) {
                usleep(self::INITIAL_BACKOFF_US * (2 ** ($attempt - 1)));
                return $this->request($method, $endpointpath, $params, $keyplacement, $attempt + 1);
            }
            throw new ums_connection_exception("HTTP {$httpcode} from UMS at {$endpointpath}", false, 0, $httpcode);
        }
        $body = (string)$response;
        $decoded = $body !== '' ? json_decode($body) : null;
        $jsonok = ($body !== '' && ($decoded !== null || json_last_error() === JSON_ERROR_NONE));

        if ($httpcode === 404) {
            $detail = '';
            if ($jsonok && is_object($decoded)) {
                $detail = $this->scrub((string)($decoded->message ?? $decoded->error ?? ''));
            }
            throw new ums_not_found_exception($endpointpath, $detail);
        }
        if ($httpcode < 200 || $httpcode >= 300) {
            throw new ums_response_exception("HTTP {$httpcode} from UMS at {$endpointpath}", false, $httpcode);
        }
        if ($body === '') {
            throw new ums_response_exception('Empty response body from UMS at ' . $endpointpath, false, $httpcode, true);
        }
        if (!$jsonok) {
            throw new ums_response_exception(json_last_error_msg(), true, $httpcode);
        }
        $this->validate_status_envelope($decoded, $endpointpath);
        return $decoded;
    }

    /**
     * UMS wraps every payload as {"status": "success", "message": ...}. Reject anything else.
     *
     * @param mixed $decoded
     * @param string $endpointpath
     * @throws ums_response_exception
     */
    protected function validate_status_envelope(mixed $decoded, string $endpointpath): void {
        if (is_array($decoded)) {
            return; // A bare list is acceptable.
        }
        if (!is_object($decoded)) {
            throw new ums_response_exception("Response is not a JSON object at {$endpointpath}", false, 200, true);
        }
        if (isset($decoded->status)) {
            $status = strtolower((string)$decoded->status);
            if (!in_array($status, ['success', 'true', '1', 'ok'], true)) {
                $msg = $decoded->message ?? $decoded->error ?? 'status not successful';
                throw new ums_response_exception($this->scrub(is_string($msg) ? $msg : json_encode($msg)), false, 200, true);
            }
        }
        if (!isset($decoded->message)) {
            throw new ums_response_exception("Missing 'message' element at {$endpointpath}", false, 200, true);
        }
    }

    /**
     * Extract the list under "message" (or the bare list).
     *
     * @param mixed $response
     * @param string $endpointpath
     * @return array
     * @throws ums_response_exception
     */
    protected function extract_list(mixed $response, string $endpointpath): array {
        if (is_array($response)) {
            return $response;
        }
        $m = $response->message ?? null;
        if (is_array($m)) {
            return $m;
        }
        if (is_object($m)) {
            return array_values((array)$m);
        }
        throw new ums_response_exception("'message' is not a list at {$endpointpath}", false, 200, true);
    }

    // ---------------------------------------------------------------- API #2.

    /**
     * All UMS programs (cached 30 minutes).
     *
     * @param bool $bustcache
     * @return ums_program[]
     * @throws ums_exception
     */
    public function get_programs(bool $bustcache = false): array {
        $cache = cache::make('local_bulk_enrolment', 'programs');
        if (!$bustcache) {
            $cached = $cache->get('all_programs');
            if (is_array($cached)) {
                return array_map(fn($a) => ums_program::from_raw($a), $cached);
            }
        }
        $list = $this->extract_list($this->request('GET', self::ENDPOINT_PROGRAMS), self::ENDPOINT_PROGRAMS);
        $programs = [];
        foreach ($list as $item) {
            $p = ums_program::from_raw($item);
            if ($p->id !== '') {
                $programs[] = $p;
            }
        }
        $cache->set('all_programs', array_map(fn($p) => $p->to_array(), $programs));
        return $programs;
    }

    // ---------------------------------------------------------------- API #3.

    /**
     * Batches of a program (cached 15 minutes).
     *
     * @param string $programid
     * @param bool $bustcache
     * @return ums_batch[]
     * @throws ums_exception
     */
    public function get_batches(string $programid, bool $bustcache = false): array {
        $pid = trim($programid);
        if ($pid === '') {
            return [];
        }
        $cache = cache::make('local_bulk_enrolment', 'batches');
        $key = 'batches_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $pid);
        if (!$bustcache) {
            $cached = $cache->get($key);
            if (is_array($cached)) {
                return array_map(fn($a) => ums_batch::from_raw($a, $pid), $cached);
            }
        }
        $endpoint = self::ENDPOINT_BATCHES . '/' . rawurlencode($pid);
        try {
            $list = $this->extract_list($this->request('GET', $endpoint), $endpoint);
        } catch (ums_not_found_exception $e) {
            $list = []; // A program without batches.
        }
        $batches = [];
        foreach ($list as $item) {
            $b = ums_batch::from_raw($item, $pid);
            if ($b->title !== '') {
                $batches[] = $b;
            }
        }
        $cache->set($key, array_map(fn($b) => $b->to_array(), $batches));
        return $batches;
    }

    /**
     * Find a batch of a program by its title (case-insensitive).
     *
     * @param string $programid
     * @param string $title
     * @return ums_batch|null
     * @throws ums_exception
     */
    public function find_batch(string $programid, string $title): ?ums_batch {
        $t = strtolower(trim($title));
        foreach ($this->get_batches($programid) as $b) {
            if (strtolower($b->title) === $t) {
                return $b;
            }
        }
        return null;
    }

    /**
     * Alias for get_batches.
     *
     * @param string|int $programid
     * @param bool $bustcache
     * @return ums_batch[]
     */
    public function get_batches_by_program(string|int $programid, bool $bustcache = false): array {
        return $this->get_batches((string)$programid, $bustcache);
    }

    /**
     * Alias for get_roster with graceful 404 handling.
     *
     * @param string|int $programid
     * @param string|null $batchtitle
     * @return ums_student[]
     */
    public function get_students_by_program_batch(string|int $programid, ?string $batchtitle = null): array {
        try {
            return $this->get_roster((string)$programid, $batchtitle);
        } catch (ums_not_found_exception $e) {
            return [];
        }
    }

    // ---------------------------------------------------------------- API #4.

    /**
     * Roster of a program, optionally restricted to one batch TITLE.
     *
     * Callers must validate the title via find_batch() first: UMS returns the whole program for unknown titles
     * and HTTP 404 (ums_not_found_exception) when a batch has no registered students.
     *
     * @param string $programid
     * @param string|null $batchtitle Batch title, or null / '0' / 'all' for the whole program.
     * @return ums_student[]
     * @throws ums_exception
     */
    public function get_roster(string $programid, ?string $batchtitle = null): array {
        $pid = trim($programid);
        if ($pid === '') {
            return [];
        }
        $b = trim((string)$batchtitle);
        if ($b === '' || strtolower($b) === 'all') {
            $b = self::BATCH_ALL;
        }
        $endpoint = self::ENDPOINT_ROSTER . '/' . rawurlencode($pid) . '/' . rawurlencode($b);
        $list = $this->extract_list($this->request('GET', $endpoint), $endpoint);
        $students = [];
        foreach ($list as $item) {
            $s = ums_student::from_raw($item);
            if ($s->username !== '') {
                $students[] = $s;
            }
        }
        return $students;
    }

    // ---------------------------------------------------------------- API #1.

    /**
     * Identity details for a list of usernames (chunked, 404 for a chunk = no records).
     *
     * @param string[] $usernames
     * @return ums_student_details[] Keyed by username.
     * @throws ums_exception
     */
    public function get_student_details_by_usernames(array $usernames): array {
        $clean = array_values(array_unique(array_filter(array_map('trim', $usernames))));
        $result = [];
        foreach (array_chunk($clean, self::LOOKUP_CHUNK) as $chunk) {
            try {
                $response = $this->request('POST', self::ENDPOINT_STUDENT_DETAILS, ['email' => implode(',', $chunk)], self::KEY_PLACEMENT_BODY);
            } catch (ums_not_found_exception $e) {
                continue;
            }
            $m = $response->message ?? null;
            $list = is_object($m) && isset($m->StudentDetails) ? (array)$m->StudentDetails : (is_array($m) ? $m : []);
            foreach ($list as $item) {
                $d = ums_student_details::from_api1($item);
                if ($d->username !== '') {
                    $result[$d->username] = $d;
                }
            }
        }
        return $result;
    }

    // ---------------------------------------------------------------- API #5.

    /**
     * Identity details by 10-digit student numbers (= Moodle usernames). Chunked; 404 for a chunk = no records.
     *
     * @param string[] $ids
     * @return ums_student_details[] Keyed by username.
     * @throws ums_exception
     */
    public function get_students_by_ids(array $ids): array {
        $clean = array_values(array_unique(array_filter(array_map('trim', $ids))));
        $result = [];
        foreach (array_chunk($clean, self::LOOKUP_CHUNK) as $chunk) {
            try {
                $response = $this->request('POST', self::ENDPOINT_USER_SYNC, ['ids' => implode(',', $chunk)], self::KEY_PLACEMENT_BODY);
            } catch (ums_not_found_exception $e) {
                continue;
            }
            foreach ($this->extract_list($response, self::ENDPOINT_USER_SYNC) as $item) {
                $d = ums_student_details::from_api5($item);
                if ($d->username !== '') {
                    $result[$d->username] = $d;
                }
            }
        }
        return $result;
    }

    // ---------------------------------------------------------------- Diagnostics.

    /**
     * Live connectivity test against API #2 (cache bypassed).
     *
     * @return array success, http_code, latency_ms, endpoint, programs_count, error, error_code
     */
    public function test_connection(): array {
        $start = microtime(true);
        $out = ['success' => false, 'http_code' => 0, 'latency_ms' => 0, 'endpoint' => self::ENDPOINT_PROGRAMS, 'programs_count' => 0, 'error' => '', 'error_code' => ''];
        try {
            $programs = $this->get_programs(true);
            $out['success'] = true;
            $out['http_code'] = 200;
            $out['programs_count'] = count($programs);
        } catch (ums_exception $e) {
            $out['http_code'] = $e->get_http_code();
            $out['error'] = $this->scrub($e->getMessage());
            $out['error_code'] = $e->get_error_code();
        } catch (\Throwable $e) {
            $out['error'] = $this->scrub($e->getMessage());
            $out['error_code'] = 'UNEXPECTED';
        }
        $out['latency_ms'] = round((microtime(true) - $start) * 1000, 2);
        return $out;
    }
}
