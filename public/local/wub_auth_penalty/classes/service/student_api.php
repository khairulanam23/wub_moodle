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

namespace local_wub_auth_penalty\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Dedicated API Communication Service for UMS Student & Financial Portal.
 *
 * Handles HTTP requests, cURL initialization, URL building, Digest/Basic authentication,
 * and X-API-KEY injection for all student-related external endpoints:
 * - students/student_login/
 * - students/student_payment_info/
 * - students/email_number_wise_student_details/
 * - payments/student_fees_details/
 *
 * @package    local_wub_auth_penalty
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_api {

    /**
     * Base API URL.
     * @var string
     */
    protected string $baseUrl;

    /**
     * API Username for Digest/Basic authentication.
     * @var string
     */
    protected string $apiUsername;

    /**
     * API Password for Digest/Basic authentication.
     * @var string
     */
    protected string $apiPassword;

    /**
     * API Key for X-API-KEY authentication.
     * @var string
     */
    protected string $apiKey;

    /**
     * Constructor initializing API configuration with fallbacks.
     */
    public function __construct() {
        $url = get_config('auth_wub_auth_penalty', 'base_url');
        if (empty($url) || strpos($url, 'http') === false) {
            $url = get_config('local_wub_auth_penalty', 'api_url');
        }
        if (empty($url) || strpos($url, 'http') === false) {
            $url = get_config('local_mass_enroll', 'api_url');
        }
        if (empty($url) || strpos($url, 'http') === false) {
            $url = 'https://api.e-dhrubo.com/';
        }

        // Normalize base URL to root domain (e.g. https://api.e-dhrubo.com/)
        $parsedUrl = parse_url($url);
        if ($parsedUrl && isset($parsedUrl['scheme']) && isset($parsedUrl['host'])) {
            $url = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . '/';
        } else {
            $url = 'https://api.e-dhrubo.com/';
        }
        $this->baseUrl = rtrim($url, '/') . '/';

        $un = get_config('auth_wub_auth_penalty', 'api_username');
        if (empty($un)) {
            $un = get_config('local_wub_auth_penalty', 'api_username');
        }
        if (empty($un)) {
            $un = get_config('local_mass_enroll', 'api_username');
        }
        $this->apiUsername = (string)$un;

        $pw = get_config('auth_wub_auth_penalty', 'api_password');
        if (empty($pw)) {
            $pw = get_config('local_wub_auth_penalty', 'api_password');
        }
        if (empty($pw)) {
            $pw = get_config('local_mass_enroll', 'api_password');
        }
        $this->apiPassword = (string)$pw;

        $key = get_config('auth_wub_auth_penalty', 'api_x_api_key');
        if (empty($key)) {
            $key = get_config('local_wub_auth_penalty', 'api_x_api_key');
        }
        if (empty($key)) {
            $key = get_config('local_mass_enroll', 'api_x_api_key');
        }
        $this->apiKey = (string)$key;
    }

    /**
     * Execute an authenticated cURL request against the UMS API.
     *
     * @param string $endpoint Relative API endpoint.
     * @param string $method HTTP method ('GET' or 'POST').
     * @param array $postData Optional POST fields.
     * @return mixed Decoded API response or null on error.
     */
    protected function request(string $endpoint, string $method = 'GET', array $postData = []) {
        $endpoint = ltrim($endpoint, '/');
        $fullUrl = $this->baseUrl . $endpoint;

        if (!empty($this->apiKey) && strpos($fullUrl, 'X-API-KEY') === false) {
            $sep = (strpos($fullUrl, '?') !== false) ? '&' : '?';
            $fullUrl .= $sep . 'X-API-KEY=' . urlencode($this->apiKey);
        }

        $curl = curl_init($fullUrl);
        if ($curl === false) {
            return null;
        }

        global $CFG;

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 35);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);

        if (!empty($CFG->pathtocertificate) && file_exists($CFG->pathtocertificate)) {
            curl_setopt($curl, CURLOPT_CAINFO, $CFG->pathtocertificate);
        }

        $headers = [];
        if (!empty($this->apiKey)) {
            $headers[] = 'X-API-KEY: ' . $this->apiKey;
        }
        if (!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        if (!empty($this->apiUsername) && !empty($this->apiPassword)) {
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_USERPWD, $this->apiUsername . ':' . $this->apiPassword);
        }

        if (strtoupper($method) === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            if (!empty($postData)) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postData));
            }
        }

        $rawResponse = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($rawResponse && ($httpCode >= 200 && $httpCode < 400)) {
            $decoded = json_decode($rawResponse);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Authenticate student credentials directly against the Student Portal API.
     * API Endpoint: students/student_login/
     *
     * @param string $username Student portal username (without @student.wub.ac.bd).
     * @param string $password Student portal password.
     * @return array ['success' => bool, 'message' => string, 'data' => mixed]
     */
    public function student_login(string $username, string $password): array {
        $shortUsername = explode('@', trim($username))[0];
        $postData = [
            'username' => $shortUsername,
            'password' => $password,
        ];

        $res = $this->request('students/student_login/', 'POST', $postData);
        if (!$res) {
            // Also try GET request format if POST returns null
            $res = $this->request('students/student_login/?username=' . urlencode($shortUsername) . '&password=' . urlencode($password), 'GET');
        }

        if (!empty($res) && (is_object($res) || is_array($res))) {
            $resObj = (object)$res;
            $status = $resObj->status ?? $resObj->code ?? '';
            $isSuccess = ($status === 'success' || $status === 200 || $status === true || $status === 'true');
            $message = $resObj->message ?? ($isSuccess ? 'Login successful' : 'Invalid credentials');
            return [
                'success' => $isSuccess,
                'message' => is_string($message) ? $message : json_encode($message),
                'data' => $resObj
            ];
        }

        return [
            'success' => false,
            'message' => 'API communication error or invalid response',
            'data' => null
        ];
    }

    /**
     * Fetch payment info for student from UMS.
     * API Endpoint: students/student_payment_info/{$username}
     *
     * @param string $studentUsername Base student username.
     * @return mixed Decoded payment object/array or null.
     */
    public function get_student_payment_info(string $studentUsername) {
        $shortUsername = explode('@', trim($studentUsername))[0];
        $res = $this->request('students/student_payment_info/' . urlencode($shortUsername));
        if ($res) {
            $rObj = (object)$res;
            if (isset($rObj->message->StudentPaymentInfo)) {
                return $rObj->message->StudentPaymentInfo;
            } else if (isset($rObj->data)) {
                return $rObj->data;
            } else if (isset($rObj->message)) {
                return $rObj->message;
            }
            return $res;
        }
        return null;
    }

    /**
     * Fetch detailed student record by email or registration number.
     * API Endpoint: students/email_number_wise_student_details/{$identifier}
     *
     * @param string $identifier Student email or registration ID.
     * @return mixed Student details object or null.
     */
    public function get_email_number_wise_student_details(string $identifier) {
        $res = $this->request('students/email_number_wise_student_details/' . urlencode($identifier));
        if ($res) {
            $rObj = (object)$res;
            if (isset($rObj->message->StudentDetails)) {
                return $rObj->message->StudentDetails;
            } else if (isset($rObj->data)) {
                return $rObj->data;
            } else if (isset($rObj->message)) {
                return $rObj->message;
            }
            return $res;
        }
        return null;
    }

    /**
     * Fetch itemized student fees details.
     * API Endpoint: payments/student_fees_details/{$username}
     *
     * @param string $studentUsername
     * @return mixed Fee breakdown object or null.
     */
    public function get_student_fees_details(string $studentUsername) {
        $shortUsername = explode('@', trim($studentUsername))[0];
        $res = $this->request('payments/student_fees_details/' . urlencode($shortUsername));
        if ($res) {
            $rObj = (object)$res;
            return $rObj->message ?? $rObj->data ?? $res;
        }
        return null;
    }
}
