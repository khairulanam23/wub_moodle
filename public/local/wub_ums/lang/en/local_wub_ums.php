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
 * English language strings for local_wub_ums plugin.
 *
 * @package    local_wub_ums
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'WUB UMS Integration Service';
$string['ums_settings'] = 'UMS Integration Settings';
$string['ums_settings_desc'] = 'Configure external UMS REST API endpoints and authentication parameters.';
$string['api_url'] = 'UMS API URL';
$string['api_url_help'] = 'Base URL for UMS student details API.';
$string['api_username'] = 'API Username';
$string['api_username_help'] = 'HTTP authentication username for UMS API calls.';
$string['api_password'] = 'API Password';
$string['api_password_help'] = 'HTTP authentication password for UMS API calls.';
$string['api_x_api_key'] = 'API Key (X-API-KEY)';
$string['api_x_api_key_help'] = 'API security header key.';
$string['api_url_programs'] = 'Programs API URL';
$string['api_url_batch'] = 'Batches API URL';
$string['api_ums_courses'] = 'Batch Enrolment Courses API URL';
$string['api_student_payment_info'] = 'Student Dues & Payment API URL';
$string['payment_due_threshold'] = 'Max Allowable Dues (BDT)';
$string['payment_due_threshold_help'] = 'Maximum allowed student dues before dashboard restriction (Default: 100 BDT).';
