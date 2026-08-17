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
 * UMS REST API Client Service for local_wub_ums.
 *
 * @package    local_wub_ums
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_wub_ums;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles all HTTP cURL communications with the external WUB UMS API backend.
 */
class api_client {

    /**
     * Retrieve configuration setting with fallback to legacy local_mass_enroll config.
     *
     * @param string $setting Setting key.
     * @param string $default Default value.
     * @return string
     */
    public function get_setting(string $setting, string $default = ''): string {
        $val = get_config('local_wub_ums', $setting);
        if ($val === false || $val === null || $val === '') {
            $val = get_config('local_mass_enroll', $setting);
        }
        return ($val !== false && $val !== null && $val !== '') ? (string)$val : $default;
    }

    /**
     * Execute HTTP GET request to UMS API endpoint.
     *
     * @param string $url API target URL.
     * @return mixed Array or object returned from JSON API response.
     */
    public function get(string $url) {
        $apiKey = $this->get_setting('api_x_api_key', '9e50f38559e4bcwub12bfa9f43def1edhr1libr139ubo3f3f2ec06f3cedhrubo5c');
        $username = $this->get_setting('api_username', 'rest_admin_user');
        $password = $this->get_setting('api_password', 'EDHEECDH+CHACHA20:EECDH+AES128RUBO');

        if (!empty($apiKey) && strpos($url, 'X-API-KEY') === false) {
            $sep = (strpos($url, '?') !== false) ? '&' : '?';
            $url .= $sep . "X-API-KEY=" . urlencode($apiKey);
        }

        $curl = curl_init($url);
        if ($curl === false) {
            return [];
        }

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 35);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_USERAGENT, 'WUB-Moodle-UMS-Client/1.0');

        $headers = [];
        if (!empty($apiKey)) {
            $headers[] = 'X-API-KEY: ' . $apiKey;
        }
        if (!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        if (!empty($username) && !empty($password)) {
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_USERPWD, "$username:$password");
        }

        $response = curl_exec($curl);
        curl_close($curl);

        $output = [];
        if ($response) {
            $decoded = json_decode($response);
            if ($decoded) {
                if (is_array($decoded)) {
                    $output = $decoded;
                } else if (isset($decoded->status) && ($decoded->status == 'success' || $decoded->status === true || $decoded->status == 'true')) {
                    $output = $decoded->message ?? $decoded->data ?? $decoded;
                } else if (isset($decoded->message) && (is_array($decoded->message) || is_object($decoded->message))) {
                    $output = $decoded->message;
                } else if (isset($decoded->data) && (is_array($decoded->data) || is_object($decoded->data))) {
                    $output = $decoded->data;
                } else if (is_object($decoded)) {
                    $output = (array)$decoded;
                }
            }
        }
        return $output;
    }

    /**
     * Fetch list of available programs from UMS API (https://api.e-dhrubo.com/students/programs).
     *
     * @return array
     */
    public function get_programs(): array {
        $apiUrl = $this->get_setting('api_url_programs', 'https://api.e-dhrubo.com/students/programs');
        $raw = $this->get($apiUrl);
        $programs = [];

        if (!empty($raw) && (is_array($raw) || is_object($raw))) {
            foreach ($raw as $p) {
                $pObj = (object)$p;
                $id = $pObj->id ?? $pObj->program_id ?? $pObj->code ?? '';
                $title = $pObj->title ?? $pObj->program_name ?? $pObj->name ?? $id;
                $shortTitle = $pObj->short_title ?? $pObj->short_name ?? $pObj->code ?? $title;
                if (!empty($id)) {
                    $programs[] = (object)[
                        'id' => (string)$id,
                        'title' => (string)$title,
                        'short_title' => (string)$shortTitle,
                        'name' => (string)$title
                    ];
                }
            }
        }

        return $programs;
    }

    /**
     * Fetch list of batches for a given program ID from UMS API (https://api.e-dhrubo.com/students/batches/<programId>).
     *
     * @param string $programId Program ID or code.
     * @return array
     */
    public function get_batches(string $programId): array {
        $baseUrl = $this->get_setting('api_url_batch', 'https://api.e-dhrubo.com/students/batches/');
        if (substr($baseUrl, -1) !== '/') {
            $baseUrl .= '/';
        }
        $url = $baseUrl . urlencode($programId);
        $raw = $this->get($url);
        $batches = [];

        if (!empty($raw) && (is_array($raw) || is_object($raw))) {
            foreach ($raw as $b) {
                $bObj = (object)$b;
                $batchTitle = (string)($bObj->batch_title ?? $bObj->title ?? $bObj->name ?? $bObj->id ?? '');
                $rawId = (string)($bObj->id ?? $batchTitle);
                if (!empty($batchTitle)) {
                    $batches[] = (object)[
                        'id' => $batchTitle, // Set ID to batch_title for REST API endpoint compatibility
                        'batch_title' => $batchTitle,
                        'title' => $batchTitle,
                        'raw_id' => $rawId
                    ];
                }
            }
        }
        return $batches;
    }

    /**
     * Fetch student list for a given program and batch from UMS API.
     *
     * Uses the enroll_student_list_program_batch_wise endpoint.
     *
     * @param string $programId Program ID or code.
     * @param string $batchId Batch ID or title.
     * @return array Array of student objects from UMS.
     */
    public function get_students_by_program_batch(string $programId, string $batchId): array {
        $baseUrl = $this->get_setting('api_ums_courses', 'https://api.e-dhrubo.com/students/enroll_student_list_program_batch_wise');
        $url = rtrim($baseUrl, '/') . '/' . urlencode($programId) . '/' . urlencode($batchId);
        $raw = $this->get($url);
        $students = [];

        if (!empty($raw) && (is_array($raw) || is_object($raw))) {
            foreach ($raw as $st) {
                $students[] = (object)$st;
            }
        }
        return $students;
    }

    /**
     * Fetch payment info (dues) for a student by username.
     *
     * @param string $studentUsername Base student username (e.g. 0326735386).
     * @return mixed
     */
    public function get_student_payment_info(string $studentUsername) {
        $baseUrl = $this->get_setting('api_student_payment_info', 'https://api.e-dhrubo.com/students/student_payment_info/');
        if (substr($baseUrl, -1) !== '/') {
            $baseUrl .= '/';
        }
        $url = $baseUrl . urlencode($studentUsername);
        return $this->get($url);
    }
}
