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
 * Language strings for local_wub_auth_penalty.
 *
 * @package    local_wub_auth_penalty
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'WUB Auth Student Due & Penalty Restriction Gate';
$string['due_restriction_reason'] = 'Access to the Moodle dashboard is restricted due to outstanding dues of {$a->due} BDT (exceeding the allowable limit of {$a->limit} BDT). Please clear your pending dues in UMS to restore access.';
$string['admin_exempt'] = 'Administrator exempt';
$string['teacher_exempt'] = 'Teacher exempt';
$string['user_not_found'] = 'User record not found';

// Settings strings
$string['setting_api_url'] = 'UMS API Base URL';
$string['setting_api_url_desc'] = 'Base URL for WUB UMS REST services (e.g. https://api.e-dhrubo.com/).';
$string['setting_api_username'] = 'API Username';
$string['setting_api_username_desc'] = 'Digest/Basic HTTP authentication username.';
$string['setting_api_password'] = 'API Password';
$string['setting_api_password_desc'] = 'Digest/Basic HTTP authentication password.';
$string['setting_api_x_api_key'] = 'X-API-KEY';
$string['setting_api_x_api_key_desc'] = 'Secret header key for UMS API authorization.';
$string['setting_due_threshold'] = 'Allowable Due Threshold (BDT)';
$string['setting_due_threshold_desc'] = 'Maximum allowed student financial dues in BDT before access is locked (default 100.00).';

// Error redirect message strings
$string['msg_auth_failed'] = 'Invalid username or password. Please check your credentials and try again.';
$string['msg_due_exceeded'] = 'Your account has outstanding financial dues exceeding the allowable limit of {$a} BDT. Access is temporarily on hold.';
$string['msg_api_error'] = 'Unable to verify student status with UMS server. Please try again later or contact support.';
