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
 * English language pack for local_bulk_enrolment.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'WUB Bulk Enrolment & UMS Integration';

// Capabilities.
$string['bulk_enrolment:view'] = 'Access the WUB bulk course enrolment interface';
$string['bulk_enrolment:manage'] = 'Manage WUB UMS settings and bulk enrolment configuration';
$string['bulk_enrolment:unenrol'] = 'Unenrol students in bulk from courses';

// Privacy.
$string['privacy:metadata'] = 'The WUB Bulk Enrolment plugin does not store persistent personal data in custom database tables. It temporarily processes user and course identifiers to execute native course enrolments through supported Moodle core APIs.';

// Tabs / Navigation.
$string['tab_enrol'] = 'Bulk Enrolment';
$string['tab_unenrol'] = 'Bulk Unenrolment';
$string['tab_sync'] = 'Student Synchronization';
$string['tab_ums'] = 'UMS Integration & Diagnostics';

// Events.
$string['event_bulk_enrolment_executed'] = 'Bulk course enrolment executed';
$string['event_bulk_unenrolment_executed'] = 'Bulk course unenrolment executed';

// CSV & Migration Strings.
$string['error_invalid_csv'] = 'The uploaded CSV file is invalid or could not be parsed.';
$string['error_csv_missing_columns'] = 'CSV header must include a student identifier column (username, email, or idnumber) and course column.';
$string['unenrol_heading'] = 'Bulk Course Unenrolment Workspace';
$string['unenrol_btn'] = 'Confirm & Execute Unenrolment';
$string['sync_heading'] = 'Student Account Synchronization & Identity Management';
$string['sync_deferred_notice'] = 'Automated student account provisioning is formally deferred pending verification of live active student batch schemas in UMS API #4 and API #1.';
$string['download_template_enrol'] = 'Download Sample Enrolment CSV Template';
$string['download_template_unenrol'] = 'Download Sample Unenrolment CSV Template';

// UI Strings - Enrolment Workspace.
$string['page_title'] = 'Bulk Course Enrolment & Roster Management';
$string['page_heading'] = 'World University of Bangladesh - Bulk Enrolment Portal';
$string['step1_title'] = 'Step 1: Select Target Courses';
$string['step2_title'] = 'Step 2: Select Academic Roster';
$string['step3_title'] = 'Step 3: Enrolment Settings & Preview';

$string['select_category'] = 'Select Faculty / Department Category';
$string['all_categories'] = '-- All Categories --';
$string['courses_available'] = 'Available Courses';
$string['selected_courses_count'] = 'Courses Selected: {$a}';
$string['no_courses_in_category'] = 'No visible courses found in this category.';

$string['roster_source'] = 'Roster Source';
$string['source_ums'] = 'WUB UMS API Roster';
$string['source_cohort'] = 'Moodle Academic Cohorts';
$string['select_program'] = 'Select Academic Program';
$string['select_batch'] = 'Select Academic Batch';
$string['select_cohort'] = 'Select Cohort';
$string['load_roster'] = 'Load Student Roster';
$string['students_available'] = 'Students in Roster';
$string['selected_students_count'] = 'Students Selected: {$a}';
$string['select_all'] = 'Select All';
$string['deselect_all'] = 'Deselect All';
$string['filter_students'] = 'Search student name, ID or email...';

$string['target_role'] = 'Target Enrolment Role';
$string['default_student_role'] = 'Student (Default)';
$string['enrol_duration'] = 'Enrolment Duration';
$string['duration_unlimited'] = 'Unlimited';
$string['generate_preview'] = 'Generate Pre-Enrolment Preview';
$string['preview_matrix_title'] = 'Pre-Enrolment Validation Matrix';
$string['preview_summary'] = 'Operations to process: {$a->total} (Enrollable: {$a->enrollable}, Already Enrolled: {$a->already_enrolled}, Warnings/Errors: {$a->issues})';
$string['execute_enrolments'] = 'Confirm & Execute Enrolments';
$string['execution_progress'] = 'Executing Enrolment Batch...';
$string['execution_complete'] = 'Batch Enrolment Execution Complete';
$string['download_results_xlsx'] = 'Download Results (Spreadsheet)';

// Status Codes.
$string['status_enrollable'] = 'Enrollable';
$string['status_enrolled'] = 'Successfully Enrolled';
$string['status_already_enrolled'] = 'Already Enrolled';
$string['status_role_added_concurrently'] = 'Role Added (Preserved Existing Role)';
$string['status_enrolment_reactivated'] = 'Enrolment Reactivated';
$string['status_manual_enrolment_disabled'] = 'Manual Enrolment Disabled';
$string['status_manual_enrolment_missing'] = 'Manual Enrolment Instance Missing';
$string['status_permission_denied'] = 'Permission Denied in Course';
$string['status_role_not_assignable'] = 'Role Not Assignable in Context';
$string['status_invalid_user'] = 'User Record Not Found';
$string['status_invalid_course'] = 'Course Record Not Found';
$string['status_duplicate_input'] = 'Duplicate Input (Deduplicated)';
$string['status_not_matched'] = 'Student Not Matched in Moodle';
$string['status_course_not_mapped'] = 'Course Not Registered in UMS Roster';
$string['status_ums_not_configured'] = 'UMS Not Configured in Site Administration';
$string['status_ums_unavailable'] = 'UMS Service Unavailable';
$string['status_system_error'] = 'Database Error / Rolled Back';

// Warnings & Errors.
$string['error_select_course'] = 'Please select at least one course.';
$string['error_select_student'] = 'Please select at least one student.';
$string['error_no_permission_course'] = 'You do not have permission to enrol users into course "{$a}".';
$string['error_instance_disabled'] = 'Manual enrolment is disabled in course "{$a}".';
$string['error_instance_missing'] = 'No active manual enrolment instance found in course "{$a}".';
$string['ums_not_installed'] = 'The WUB UMS integration is not configured. You may select students using Moodle Academic Cohorts.';
$string['ums_offline'] = 'The external UMS REST API is currently unreachable. You can continue using local Moodle Cohorts.';

// UMS Settings.
$string['settings_heading_portal'] = 'Bulk Enrolment Navigation';
$string['settings_heading_portal_desc'] = 'Quick links to the administrative enrolment interface and diagnostic tooling.';
$string['settings_portal_link'] = 'Bulk Enrolment Workspace';
$string['settings_portal_link_desc'] = '<a href="{$a}">Open Bulk Enrolment Workspace</a> to enrol students across programs and batches.';

$string['settings_heading_connection'] = 'UMS API Connection';
$string['settings_heading_connection_desc'] = 'Configure connection parameters to the external World University of Bangladesh Management System (UMS) backend.';
$string['enabled'] = 'Enable UMS Integration';
$string['enabled_desc'] = 'When enabled, Moodle integrates with the external WUB UMS backend to fetch authoritative programs, batches, and student rosters.';
$string['base_url'] = 'UMS API Base URL';
$string['base_url_desc'] = 'Base HTTPS URL of the UMS API gateway (e.g. https://api.e-dhrubo.com). Endpoint paths are managed as standard application constants.';
$string['connect_timeout'] = 'Connection Timeout (seconds)';
$string['connect_timeout_desc'] = 'Maximum time in seconds to wait for initial TCP connection establishment.';
$string['timeout'] = 'Request Timeout (seconds)';
$string['timeout_desc'] = 'Maximum time in seconds to wait for UMS API response transfer.';
$string['ssl_verify'] = 'Verify SSL Certificate';
$string['ssl_verify_desc'] = 'Recommended in production to verify SSL peer and host certificates. Only disable in local development environments.';

$string['settings_heading_auth'] = 'Authentication Credentials';
$string['settings_heading_auth_desc'] = 'Configure HTTP Digest Authentication credentials and the X-API-KEY secret. All fields are kept empty by default and must be provided by an authorized administrator.';
$string['api_username'] = 'Digest Auth Username';
$string['api_username_desc'] = 'HTTP Digest Authentication username for the UMS API.';
$string['api_password'] = 'Digest Auth Password';
$string['api_password_desc'] = 'HTTP Digest Authentication password for the UMS API.';
$string['api_key'] = 'X-API-KEY Secret';
$string['api_key_desc'] = 'Secret API key passed as query parameter on GET endpoints and in request body on POST endpoints per the WUB UMS contract.';

$string['test_connection_link'] = 'Test UMS Connection & Diagnostics';
$string['test_connection_link_desc'] = 'Test connectivity, verify authentication, and view available programs: <a href="{$a}">Launch UMS Diagnostic Console</a>';

// UMS Diagnostic Console.
$string['diag_title'] = 'WUB UMS Diagnostic Console';
$string['diag_heading'] = 'UMS Connection Status & Endpoint Diagnostics';
$string['diag_status_card'] = 'Configuration Status';
$string['diag_test_card'] = 'Live Connection Test';
$string['diag_endpoints_card'] = 'Supported UMS API Contract';
$string['diag_actions'] = 'Diagnostic Actions';
$string['diag_btn_test'] = 'Run Live UMS Test';
$string['diag_btn_purge'] = 'Purge UMS Cache';
$string['diag_cache_purged'] = 'Successfully purged UMS programs and batches MUC cache.';

$string['status_configured'] = 'Configured';
$string['status_not_configured'] = 'Not Configured';
$string['status_enabled'] = 'Enabled';
$string['status_disabled'] = 'Disabled';
$string['status_ssl_active'] = 'Active (Peer & Host verified)';
$string['status_ssl_disabled'] = 'Disabled (Insecure)';

$string['test_result_success'] = 'UMS connection test succeeded.';
$string['test_result_failure'] = 'UMS connection test failed.';
$string['test_endpoint_tested'] = 'Endpoint Tested';
$string['test_http_status'] = 'HTTP Response Code';
$string['test_latency'] = 'Roundtrip Latency';
$string['test_programs_count'] = 'Programs Retrieved';
$string['test_error_message'] = 'Error Details';

// UMS API Contract Descriptions.
$string['api1_title'] = 'API #1: Student Details by Multiple Usernames';
$string['api1_desc'] = 'POST /students/multiple_username_wise_std_details (Digest + Body X-API-KEY)';
$string['api2_title'] = 'API #2: Academic Programs Catalog';
$string['api2_desc'] = 'GET /students/programs (Digest + Query X-API-KEY, Cached)';
$string['api3_title'] = 'API #3: Batches by Program';
$string['api3_desc'] = 'GET /students/batches/{program_id} (Digest + Query X-API-KEY, Cached)';
$string['api4_title'] = 'API #4: Student Enrolment List by Program & Batch';
$string['api4_desc'] = 'GET /students/enroll_student_list_program_batch_wise/{program_id}/{batch_id} (Primary Roster Source)';
$string['api5_title'] = 'API #5: User Sync by Registration IDs';
$string['api5_desc'] = 'POST /students/reg_id_wise_multi_student_info (Digest + Body X-API-KEY)';

// UMS Client Exceptions / Errors.
$string['error_not_enabled'] = 'WUB UMS integration is currently disabled in Site Administration.';
$string['error_config_missing'] = 'UMS configuration is incomplete. Base URL, username, password, and API key are required.';
$string['error_invalid_url'] = 'Invalid UMS base URL. A valid HTTPS URL is required.';
$string['error_auth_failed'] = 'UMS authentication failed (HTTP {$a}). Please verify Digest credentials and X-API-KEY.';
$string['error_connection_timeout'] = 'Connection to UMS backend timed out after {$a} seconds.';
$string['error_connection_failed'] = 'Failed to connect to UMS backend: {$a}';
$string['error_invalid_json'] = 'Invalid JSON response received from UMS backend.';
$string['error_unexpected_response'] = 'Unexpected response structure from UMS: {$a}';
$string['error_program_not_found'] = 'Program ID "{$a}" not found in UMS.';
$string['error_batch_not_found'] = 'Batch ID "{$a}" not found in UMS.';

// ---------------------------------------------------------------------------
// v3 (2026-09-14): UMS-driven workspace, typed errors, verification statuses.
// ---------------------------------------------------------------------------
$string['error_server_error'] = 'UMS returned a server error (HTTP {$a}).';
$string['error_unexpected_schema'] = 'UMS returned an unexpected response structure: {$a}';
$string['error_http_client'] = 'UMS rejected the request (HTTP {$a}).';
$string['error_not_found'] = 'UMS has no records for this request ({$a}).';
$string['error_invalid_batch'] = 'Unknown batch title for the selected program: {$a}';
$string['error_chunk_too_large'] = 'A chunk may contain at most {$a} users.';
$string['error_invalid_course'] = 'The selected course does not exist.';
$string['warn_batch_mismatch'] = 'UMS returned {$a->dropped} student(s) outside batch {$a->batch}; they were ignored.';
$string['warn_enrich_failed'] = 'Student details (e-mail, shift) could not be loaded from UMS ({$a}); the roster itself is complete.';

$string['uierr_ums_auth'] = 'UMS authentication failed. Check the Digest username/password in the plugin settings.';
$string['uierr_ums_forbidden'] = 'UMS rejected the API key (HTTP 403). Check the X-API-KEY in the plugin settings.';
$string['uierr_ums_timeout'] = 'The UMS request timed out. Try again or raise the timeout in the plugin settings.';
$string['uierr_ums_connection'] = 'Could not connect to UMS (network or TLS failure).';
$string['uierr_ums_http_4xx'] = 'UMS rejected the request.';
$string['uierr_ums_http_5xx'] = 'UMS is currently failing (server error).';
$string['uierr_ums_bad_json'] = 'UMS returned malformed JSON.';
$string['uierr_ums_schema'] = 'UMS returned an unexpected response structure.';
$string['uierr_ums_not_configured'] = 'The UMS integration is disabled or not configured.';
$string['uierr_ums_not_found'] = 'UMS has no records for this request.';
$string['uierr_invalid_batch'] = 'The selected batch does not belong to the selected program.';
$string['uierr_invalid_program'] = 'Invalid program.';
$string['uierr_ums_error'] = 'The UMS request failed.';
$string['uierr_unexpected'] = 'An unexpected error occurred.';

$string['status_ready'] = 'Ready';
$string['status_enrolment_suspended'] = 'Enrolment suspended';
$string['status_identity_not_found'] = 'No Moodle account';
$string['status_ambiguous_match'] = 'Ambiguous identity';
$string['status_no_manual_instance'] = 'No manual enrolment';
$string['status_manual_instance_disabled'] = 'Manual enrolment disabled';
$string['status_not_authorized'] = 'Not authorised';

$string['msg_ready'] = 'Will be enrolled with the selected role.';
$string['msg_ready_user_suspended'] = 'Will be enrolled; note the Moodle account itself is suspended.';
$string['msg_already_enrolled'] = 'Already actively enrolled (via {$a}); nothing will change.';
$string['msg_suspended'] = 'Enrolment exists but is suspended. Enable "reactivate suspended enrolments" to restore it.';
$string['msg_suspended_reactivate'] = 'Suspended enrolment will be reactivated.';
$string['msg_identity_not_found'] = 'No Moodle account matches this UMS student (username, idnumber or institutional e-mail). Provisioning is deferred.';
$string['msg_ambiguous'] = '{$a} Moodle accounts match this student; blocked until the duplicate accounts are resolved.';
$string['msg_no_manual_instance'] = 'The course has no manual enrolment method; add one in the course enrolment methods first.';
$string['msg_manual_instance_disabled'] = 'The manual enrolment method of this course is disabled.';
$string['msg_invalid_course'] = 'The course does not exist.';
$string['msg_unknown_course'] = 'Course #{$a}';
$string['msg_not_authorized'] = 'You are not allowed to enrol users in this course (enrol/manual:enrol).';
$string['msg_role_not_assignable'] = 'You cannot assign the selected role in this course.';
$string['msg_group_failed'] = 'Group membership could not be added.';

$string['result_enrolled'] = 'Enrolled.';
$string['result_already_enrolled'] = 'Already enrolled; no duplicate created.';
$string['result_reactivated'] = 'Suspended enrolment reactivated.';
$string['result_enrolment_suspended'] = 'Enrolment exists but is suspended (reactivation not requested).';
$string['result_identity_not_found'] = 'User does not exist.';

$string['ws_title'] = 'Bulk enrolment workspace';
$string['ws_intro'] = 'Select UMS programs and batches, review the live student roster, choose Moodle courses, verify, then enrol.';
$string['ws_programs'] = 'Academic programs';
$string['ws_programs_search'] = 'Search programs…';
$string['ws_batches'] = 'Batches';
$string['ws_batches_hint'] = 'No batch selected = every active student of the program.';
$string['ws_all_batches'] = 'All batches';
$string['ws_roster'] = 'Student roster';
$string['ws_roster_search'] = 'Search name, username, registration id, course code…';
$string['ws_roster_empty'] = 'Select at least one program to load students from UMS.';
$string['ws_roster_none'] = 'UMS returned no students for this selection.';
$string['ws_roster_filtered_none'] = 'No students match the current filter.';
$string['ws_courses'] = 'Moodle courses';
$string['ws_courses_search'] = 'Search courses…';
$string['ws_courses_none'] = 'No visible courses found. Create a course before enrolling students.';
$string['ws_options'] = 'Enrolment options';
$string['ws_group_none'] = 'No group assignment';
$string['ws_group_batch'] = 'Group named after the UMS batch (per student)';
$string['ws_group_custom'] = 'Custom group name';
$string['ws_group_create'] = 'Create the group if it does not exist';
$string['ws_reactivate'] = 'Reactivate suspended enrolments';
$string['ws_verify'] = 'Verify';
$string['ws_verification'] = 'Verification matrix';
$string['ws_execute'] = 'Enrol students';
$string['ws_confirm_title'] = 'Confirm bulk enrolment';
$string['ws_confirm_body'] = 'Enrol {$a->students} student(s) into {$a->courses} course(s)? {$a->ready} enrolment(s) will be created; already enrolled students are left untouched.';
$string['ws_results'] = 'Execution results';
$string['ws_selected'] = 'selected';
$string['ws_select_page'] = 'Select page';
$string['ws_select_all_matching'] = 'Select all matching';
$string['ws_clear'] = 'Clear';
$string['ws_refresh'] = 'Refresh from UMS';
$string['ws_courses_history'] = 'Registered courses (UMS)';
$string['ws_loading'] = 'Loading…';
$string['ws_match_matched'] = 'Moodle account';
$string['ws_match_not_found'] = 'No account';
$string['ws_match_ambiguous'] = 'Ambiguous';
$string['ws_group_batch_hint'] = 'Each student is added to a course group named exactly like their UMS batch (e.g. "74F").';
