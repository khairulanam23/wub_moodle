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
 * English language strings for local_wub_special_permission plugin.
 *
 * @package    local_wub_special_permission
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'WUB Special Login Permission Manager';
$string['wub_special_permission:manage'] = 'Manage student special login permissions';
$string['nav_setting_name'] = 'Special Login Permissions';
$string['nav_setting_desc'] = 'Grant and manage temporary login permission bypasses for students.';

$string['heading_title'] = 'Manage Student Special Login Permission';
$string['search_student_heading'] = 'Search Student';
$string['search_input_label'] = 'Student ID, Username, or Email';
$string['search_button'] = 'Search';
$string['search_help'] = 'Enter student registration number, username, or institutional email address.';
$string['student_not_found'] = 'No student user record found matching the specified identifier.';

$string['student_details_heading'] = 'Student Details';
$string['label_student_id'] = 'Student Registration ID / Username';
$string['label_student_name'] = 'Full Name';
$string['label_student_email'] = 'Email Address';
$string['label_program'] = 'Academic Program / Department';

$string['permission_status_heading'] = 'Current Special Permission Status';
$string['status_active'] = 'Active';
$string['status_expired'] = 'Expired';
$string['status_none'] = 'No Active Permission';
$string['valid_until_format'] = 'Valid Until: {$a}';
$string['expired_on_format'] = 'Expired On: {$a}';

$string['grant_permission_heading'] = 'Grant Special Permission';
$string['valid_until_label'] = 'Permission Expiration Date';
$string['grant_button'] = 'Grant Permission';
$string['revoke_button'] = 'Revoke Permission';
$string['confirm_grant_button'] = 'Confirm & Replace Permission';

$string['overwrite_warning_title'] = 'Active Special Permission Exists';
$string['overwrite_warning_msg'] = 'This student already has active special permission valid until {$a}. Are you sure you want to replace it with the selected expiration date?';

$string['msg_permission_granted'] = 'Special permission successfully granted for student {$a->name} ({$a->username}) until {$a->date}.';
$string['msg_permission_updated'] = 'Special permission updated for student {$a->name} ({$a->username}) to {$a->date}.';
$string['msg_permission_revoked'] = 'Special permission revoked for student {$a->name} ({$a->username}).';

$string['err_invalid_date'] = 'The selected expiration date is invalid. Please select a future date.';
$string['err_student_required'] = 'A valid student user must be selected.';
$string['event_permission_updated'] = 'Special login permission updated for student ID {$a->relateduserid} by administrator ID {$a->userid}. Expiry date set to: {$a->other}.';
