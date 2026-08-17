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
 * Public library and procedural facade for local_wub_auth_penalty plugin.
 *
 * Exposes clean procedural entrypoints mapping to modular service classes:
 * - \local_wub_auth_penalty\service\student_api
 * - \local_wub_auth_penalty\service\authentication
 * - \local_wub_auth_penalty\service\due_calculator
 * - \local_wub_auth_penalty\service\penalty_checker
 *
 * @package    local_wub_auth_penalty
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Get instance of Student API Client.
 *
 * @return \local_wub_auth_penalty\service\student_api
 */
function wub_auth_penalty_get_api_client(): \local_wub_auth_penalty\service\student_api {
    static $client = null;
    if ($client === null) {
        $client = new \local_wub_auth_penalty\service\student_api();
    }
    return $client;
}

/**
 * Get instance of Student Authentication Service.
 *
 * @return \local_wub_auth_penalty\service\authentication
 */
function wub_auth_penalty_get_authenticator(): \local_wub_auth_penalty\service\authentication {
    static $authenticator = null;
    if ($authenticator === null) {
        $authenticator = new \local_wub_auth_penalty\service\authentication(wub_auth_penalty_get_api_client());
    }
    return $authenticator;
}

/**
 * Get instance of Due Calculator Service.
 *
 * @return \local_wub_auth_penalty\service\due_calculator
 */
function wub_auth_penalty_get_due_calculator(): \local_wub_auth_penalty\service\due_calculator {
    static $calculator = null;
    if ($calculator === null) {
        $calculator = new \local_wub_auth_penalty\service\due_calculator();
    }
    return $calculator;
}

/**
 * Get instance of Access Enforcement & Penalty Checker Service.
 *
 * @return \local_wub_auth_penalty\service\penalty_checker
 */
function wub_auth_penalty_get_checker(): \local_wub_auth_penalty\service\penalty_checker {
    static $checker = null;
    if ($checker === null) {
        $checker = new \local_wub_auth_penalty\service\penalty_checker(
            wub_auth_penalty_get_api_client(),
            wub_auth_penalty_get_due_calculator()
        );
    }
    return $checker;
}

/**
 * Check whether a student is restricted due to outstanding dues (> 100 BDT) or status in UMS.
 * Site administrators and teachers are completely exempt.
 * Results are cached in session for 10 minutes (600s).
 *
 * @param int $userid Moodle user ID.
 * @return array ['allowed' => bool, 'reason' => string, 'status' => string, 'due' => float, 'redirect_url' => string]
 */
function wub_auth_penalty_check_student_due_status(int $userid): array {
    return wub_auth_penalty_get_checker()->check_due_status($userid);
}

/**
 * Authenticate student credentials via Moodle DB or Student Portal API.
 *
 * @param string $username
 * @param string $password
 * @return stdClass|false Moodle user record on success, or false.
 */
function wub_auth_penalty_user_login(string $username, string $password) {
    return wub_auth_penalty_get_authenticator()->user_login($username, $password);
}

/**
 * Calculate due analysis for student.
 *
 * @param mixed $paymentInfo Raw payment payload.
 * @param int|string|null $programId Academic program ID.
 * @param mixed $feeDetails Itemized fee breakdown.
 * @return array
 */
function wub_auth_penalty_get_due($paymentInfo, $programId = null, $feeDetails = null): array {
    return wub_auth_penalty_get_due_calculator()->getDue($paymentInfo, $programId, $feeDetails);
}

/**
 * Get allowable student due threshold limit (default 100.0 BDT).
 *
 * @return float
 */
function wub_auth_penalty_get_due_threshold(): float {
    return wub_auth_penalty_get_checker()->get_due_threshold();
}

/**
 * Orchestrate login page hook & redirect management.
 *
 * Redirects:
 * - /login/index.php?msg=0  -> Invalid credentials / student authentication failed.
 * - /login/index.php?msg=1&due=... -> Due threshold (> 100 BDT) exceeded.
 * - /login/index.php?msg=2&a=...   -> API connection / payload error.
 *
 * @param string $username Submitted login username.
 * @param string $password Submitted login password.
 * @return stdClass|null User object if authorized, or null if redirected/failed.
 */
function wub_auth_penalty_login_hook(string $username, string $password): ?stdClass {
    $user = wub_auth_penalty_user_login($username, $password);
    if (!$user) {
        redirect(new moodle_url('/login/index.php', ['msg' => '0']));
        return null;
    }

    $status = wub_auth_penalty_check_student_due_status((int)$user->id);
    if (!empty($status) && isset($status['allowed']) && $status['allowed'] === false) {
        $redirectUrl = !empty($status['redirect_url']) ? $status['redirect_url'] : '/local/mass_enroll/payment_notice.php';
        redirect(new moodle_url($redirectUrl));
        return null;
    }

    return $user;
}
