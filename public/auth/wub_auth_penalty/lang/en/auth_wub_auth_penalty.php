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
 * English language strings for auth_wub_auth_penalty plugin.
 *
 * @package    auth_wub_auth_penalty
 * @copyright  2021 World University of Bangladesh (CIS)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'WUB Auth Penalty Plugin';
$string['auth_wub_auth_penaltydescription'] = 'Authenticates student users via Student Portal API and restricts login if financial dues exceed the allowable threshold (100 BDT).';
$string['base_url'] = 'UMS Base API URL';
$string['base_url_desc'] = 'Base URL of the UMS API endpoint (e.g. https://api.e-dhrubo.com/).';
$string['api_username'] = 'API Username';
$string['api_username_desc'] = 'Digest HTTP authentication username for UMS API.';
$string['api_password'] = 'API Password';
$string['api_password_desc'] = 'Digest HTTP authentication password for UMS API.';
$string['api_x_api_key'] = 'X-API-KEY Token';
$string['api_x_api_key_desc'] = 'X-API-KEY header / query token for UMS API.';
$string['minimum_due'] = 'Minimum Due Threshold (BDT)';
$string['minimum_due_desc'] = 'Allowable dues limit in BDT before blocking access (default: 100).';

$string['login_due_restriction_title'] = 'Account Access Restricted';
$string['login_due_restriction_message'] = 'Please complete the due payment to log in.';
