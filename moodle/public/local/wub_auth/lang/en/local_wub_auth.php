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
 * Language strings for local_wub_auth.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin identity.
$string['pluginname'] = 'WUB Authentication & Institutional Access Control';
$string['plugindesc'] = 'World University of Bangladesh institutional authentication, UMS fallback, financial access control, waivers, and policy management system.';

// Capabilities.
$string['wub_auth:manage'] = 'Manage WUB authentication and institutional access settings';
$string['wub_auth:managewaivers'] = 'Grant and revoke student financial access waivers';
$string['wub_auth:viewaudit'] = 'View authentication and access control audit records';
$string['wub_auth:bypassfinancial'] = 'Bypass institutional financial restrictions';

// Settings Headers & Groups.
$string['setting_group_ums'] = 'UMS Connection & Credentials';
$string['setting_group_ums_desc'] = 'Configuration for connecting to the World University of Bangladesh University Management System (UMS) backend. If left blank, values are inherited from local_bulk_enrolment.';
$string['setting_group_auth'] = 'Authentication Orchestration & Fallback';
$string['setting_group_auth_desc'] = 'Configure native authentication rules, UMS fallback behavior, and password synchronization.';
$string['setting_group_financial'] = 'Institutional Financial Access Control';
$string['setting_group_financial_desc'] = 'Enforce institutional tuition and dues policies before granting access to learning services.';
$string['setting_group_policy'] = 'Institutional Policy Acknowledgement';
$string['setting_group_policy_desc'] = 'Configure 30-day role-based policy acknowledgement requirements.';
$string['setting_group_integration'] = 'ExamController Integration Boundary';
$string['setting_group_integration_desc'] = 'Cryptographic trust boundary for future ExamController authentication integration.';

// UMS Settings.
$string['setting_base_url'] = 'UMS Base URL';
$string['setting_base_url_desc'] = 'The base REST API URL (e.g., https://api.e-dhrubo.com).';
$string['setting_api_username'] = 'API Username';
$string['setting_api_username_desc'] = 'HTTP Digest/Basic authentication username for UMS.';
$string['setting_api_password'] = 'API Password';
$string['setting_api_password_desc'] = 'HTTP Digest/Basic authentication password for UMS.';
$string['setting_api_key'] = 'API Key (X-API-KEY)';
$string['setting_api_key_desc'] = 'Authoritative security token passed in headers or body to UMS endpoints.';
$string['setting_timeout'] = 'HTTP Request Timeout (seconds)';
$string['setting_timeout_desc'] = 'Maximum time to wait for a UMS API response (default: 15s).';
$string['setting_connect_timeout'] = 'Connect Timeout (seconds)';
$string['setting_connect_timeout_desc'] = 'Maximum time to wait when establishing a connection to UMS (default: 5s).';
$string['setting_ssl_verify'] = 'Verify SSL Certificate';
$string['setting_ssl_verify_desc'] = 'Strictly verify UMS TLS/SSL certificate validity.';

// Auth Settings.
$string['setting_enable_ums_fallback'] = 'Enable UMS Fallback Authentication';
$string['setting_enable_ums_fallback_desc'] = 'When local Moodle password authentication fails, attempt authentication against the authoritative UMS student portal.';
$string['setting_sync_password_on_ums_login'] = 'Synchronize Password upon UMS Login';
$string['setting_sync_password_on_ums_login_desc'] = 'Upon successful UMS credential verification, update the local Moodle password hash to enable faster subsequent local logins.';

// Financial Settings.
$string['setting_financial_control_enabled'] = 'Enable Financial Access Control';
$string['setting_financial_control_enabled_desc'] = 'Block students with outstanding tuition dues exceeding the institutional threshold from accessing Moodle.';
$string['setting_due_threshold'] = 'Due Threshold (BDT)';
$string['setting_due_threshold_desc'] = 'Maximum allowable net due amount before institutional access is restricted (default: 100 BDT).';
$string['setting_buffer_deduction'] = 'Baseline Buffer Deduction (BDT)';
$string['setting_buffer_deduction_desc'] = 'Standard institutional buffer deduction applied to raw dues (default: 100 BDT).';
$string['setting_installment_day_cutoff'] = 'Installment Cutoff Day of Month';
$string['setting_installment_day_cutoff_desc'] = 'Up to this calendar day of the month, the current monthly installment is deducted from dues (default: 15).';
$string['setting_exempt_programs'] = 'Exempt Program IDs';
$string['setting_exempt_programs_desc'] = 'Comma-separated list of program IDs exempt from due enforcement until the annual cutoff date (default: 324, 351, 359, 360, 363, 352, 361, 362, 313).';
$string['setting_exempt_date_cutoff'] = 'Exempt Program Cutoff Date (YYYY-MM-DD or MM-DD)';
$string['setting_exempt_date_cutoff_desc'] = 'Cutoff date for exempt programs in YYYY-MM-DD or annual MM-DD format (default: 09-10).';
$string['setting_exempt_departments'] = 'Exempt Department IDs/Names';
$string['setting_exempt_departments_desc'] = 'Comma-separated list of department IDs or names exempt from payment monitoring until the department cutoff date.';
$string['setting_exempt_department_date_cutoff'] = 'Exempt Department Cutoff Date (YYYY-MM-DD or MM-DD)';
$string['setting_exempt_department_date_cutoff_desc'] = 'Cutoff date for exempt departments in YYYY-MM-DD or annual MM-DD format (defaults to program cutoff if blank).';
$string['setting_outage_policy'] = 'UMS Outage Access Policy';
$string['setting_outage_policy_desc'] = 'Action to take when UMS is unreachable and no valid cached clearance exists.';
$string['setting_outage_policy_restrict'] = 'Restrict Access (Fail-Closed, Safe Institutional Policy)';
$string['setting_outage_policy_allow'] = 'Allow Access (Fail-Open, Emergency Fallback)';

// Policy Settings.
$string['setting_policy_enabled'] = 'Enable Policy Acknowledgement';
$string['setting_policy_enabled_desc'] = 'Require users to review and accept university policies before proceeding.';
$string['setting_policy_version'] = 'Active Policy Version';
$string['setting_policy_version_desc'] = 'Incrementing this version number prompts all users to re-accept the updated policy on their next login attempt.';
$string['setting_policy_expiry_days'] = 'Policy Acceptance Validity (Days)';
$string['setting_policy_expiry_days_desc'] = 'Number of days an accepted policy remains valid before requiring re-acceptance (default: 30 days).';

// Integration Settings.
$string['setting_integration_secret'] = 'ExamController Shared Secret';
$string['setting_integration_secret_desc'] = 'Cryptographic secret key used to sign and verify identity verification tokens for ExamController.';
$string['setting_token_ttl'] = 'Token Validity (Seconds)';
$string['setting_token_ttl_desc'] = 'Lifetime of signed identity tokens exchanged with ExamController (default: 300s).';

// Login Experience.
$string['login_title'] = 'World University of Bangladesh - Institutional Login';
$string['login_subtitle'] = 'Welcome to the WUB eLearning Portal. Sign in using your institutional credentials.';
$string['username_or_id'] = 'Student ID, Email, or Username';
$string['username_or_id_help'] = 'Enter your 10-digit Student ID (e.g. 0326745530), institutional email (0326745530@student.wub.edu.bd), or faculty username.';
$string['password'] = 'Password';
$string['remember_username'] = 'Remember username';
$string['sign_in'] = 'Sign In';
$string['portal_role_student'] = 'Student';
$string['portal_role_teacher'] = 'Faculty';
$string['portal_role_admin'] = 'Staff / Admin';
$string['portal_role_all'] = 'Portal Login';
$string['forgot_password'] = 'Forgot password or need help?';
$string['support_contact'] = 'For login assistance, contact: support@wub.edu.bd or call +880 9643 204060';

// Login Error Messages.
$string['error_invalid_credentials'] = 'Invalid username, student ID, or password. Please verify and try again.';
$string['error_empty_credentials'] = 'Please enter both your institutional identifier and password.';
$string['error_account_not_provisioned'] = 'Your institutional credentials are valid in UMS, but your Moodle account has not been provisioned yet. Please contact the Registrar or your Department Coordinator.';
$string['error_account_suspended'] = 'Your Moodle user account is currently suspended. Please contact institutional administration.';
$string['error_account_nologin'] = 'This account is not permitted to log in directly.';
$string['error_ambiguous_identity'] = 'Multiple conflicting user accounts match this identifier. For security, automated login is disabled. Please contact the administrator.';
$string['error_login_due_restriction'] = 'Institutional access is restricted due to outstanding semester dues. Please complete your fee clearance to proceed.';
$string['error_ums_unavailable'] = 'Unable to connect to the institutional UMS service at this time. Please try again in a few minutes or contact support.';
$string['error_policy_required'] = 'You must review and accept the institutional e-learning policies before accessing Moodle.';
$string['error_session_failed'] = 'Unable to establish a secure session. Please try again.';
$string['error_unauthorized_role'] = 'You do not have the required institutional authorization for this role.';
$string['error_unauthorized_persona'] = 'Your account is not authorized for the {$a->role} portal. Please sign in through the appropriate institutional portal.';

// Landing Page.
$string['landing_title'] = 'World University of Bangladesh - Portal Access';
$string['landing_welcome'] = 'WELCOME TO WUB';
$string['landing_instruction'] = 'Click below to access the portal as a student, teacher, or administrative personnel';
$string['landing_role_student'] = 'Student';
$string['landing_role_teacher'] = 'Teacher';
$string['landing_role_admin'] = 'Administration';
$string['landing_catalog_instruction'] = 'Click the button below to check out the course catalog';
$string['landing_catalog_btn'] = 'Course Catalog';
$string['landing_contact_us'] = 'CONTACT US';
$string['landing_how_to_guides'] = 'HOW-TO GUIDES';
$string['landing_footer_text'] = 'Copyright © 2022 - Developed by <a href="https://cisbd.com" target="_blank" rel="noopener">Computing and Information Services Ltd.</a> Powered by <a href="https://moodle.org" target="_blank" rel="noopener">Moodle</a>';

// How-To Guides.
$string['guides_title'] = 'WUB Portal How-To Guides';
$string['guides_heading'] = 'How-To Guides & Portal Assistance';

// Financial Restriction Notice.
$string['restricted_title'] = 'Institutional Access Restricted - Tuition Clearance Required';
$string['restricted_heading'] = 'Notice: Outstanding Semester Dues';
$string['restricted_lead'] = 'According to institutional records, your account currently has an outstanding tuition balance exceeding the allowable limit.';
$string['restricted_due_amount'] = 'Net Calculated Due';
$string['restricted_raw_due'] = 'Total Outstanding Balance';
$string['restricted_installment'] = 'Current Monthly Installment';
$string['restricted_threshold'] = 'Permitted Threshold';
$string['restricted_steps_title'] = 'Steps to Restore Access';
$string['restricted_step1'] = 'Complete your due payment through the official WUB Student Portal or authorized payment channels (bKash / Nagad / Bank).';
$string['restricted_step2'] = 'Payments are automatically reconciled with UMS. Once updated, your access will be restored immediately.';
$string['restricted_step3'] = 'If you have already paid or hold an approved waiver/special permission, please contact the Accounts Office or your Department Coordinator.';
$string['restricted_accounts_contact'] = 'Accounts Office: accounts@wub.edu.bd | Ext: 104 / 105';
$string['restricted_btn_logout'] = 'Sign Out';
$string['restricted_btn_refresh'] = 'I Have Completed Payment - Re-check Access';

// Waivers Management.
$string['waivers_title'] = 'Student Financial Waivers & Special Permissions';
$string['waivers_desc'] = 'Grant or revoke temporary institutional access waivers for students with pending financial clearance.';
$string['waiver_search_label'] = 'Search Student';
$string['waiver_search_placeholder'] = 'Enter Student ID, Registration ID, Username, or Email...';
$string['waiver_search_btn'] = 'Find Student';
$string['waiver_status_active'] = 'Active Waiver';
$string['waiver_status_expired'] = 'Expired';
$string['waiver_status_revoked'] = 'Revoked';
$string['waiver_status_none'] = 'No Active Waiver';
$string['waiver_grant_title'] = 'Grant Special Permission / Waiver';
$string['waiver_expiry_date'] = 'Expiry Date';
$string['waiver_reason'] = 'Administrative Reason / Reference';
$string['waiver_reason_placeholder'] = 'e.g. Accounts approval ref #1234, special exam permission, scholarship verification...';
$string['waiver_grant_btn'] = 'Grant Waiver';
$string['waiver_revoke_btn'] = 'Revoke Waiver';
$string['waiver_granted_success'] = 'Waiver granted successfully for student {$a->name} until {$a->expiry}.';
$string['waiver_revoked_success'] = 'Waiver revoked successfully for student {$a->name}.';
$string['waiver_self_grant_prohibited'] = 'You cannot grant a waiver to your own account.';
$string['waiver_student_not_found'] = 'No student record found matching the specified identifier.';
$string['waiver_history_title'] = 'Recent Waiver Actions';
$string['waiver_col_student'] = 'Student';
$string['waiver_col_status'] = 'Status';
$string['waiver_col_expires'] = 'Expires';
$string['waiver_col_granted_by'] = 'Granted By';
$string['waiver_col_reason'] = 'Reason';
$string['waiver_col_actions'] = 'Actions';

// Diagnostics Page.
$string['diagnostics_title'] = 'WUB Authentication & Access Control Diagnostics';
$string['diagnostics_card_ums'] = 'UMS Connection';
$string['diagnostics_card_cache'] = 'Cache System';
$string['diagnostics_card_waivers'] = 'Active Waivers';
$string['diagnostics_card_policy'] = 'Policy Version';
$string['diagnostics_probe_ums'] = 'Test Live UMS Connection';
$string['diagnostics_probe_success'] = 'Successfully connected to UMS backend. Programs count: {$a}.';
$string['diagnostics_probe_failed'] = 'Failed to connect to UMS backend: {$a}';
$string['diagnostics_test_identity'] = 'Identity Resolver Test';
$string['diagnostics_test_identity_btn'] = 'Test Identity Resolution';
$string['diagnostics_test_due'] = 'Financial Clearance Test';
$string['diagnostics_test_due_btn'] = 'Calculate Student Dues';
$string['diagnostics_purge_cache_btn'] = 'Purge Auth Caches';
$string['diagnostics_cache_purged'] = 'Financial clearance and policy caches have been purged successfully.';

// Policy Page Strings.
$string['policy_header'] = 'World University of Bangladesh - E-Learning Policies';
$string['policy_subtitle'] = 'Please review the institutional terms, conditions, and code of conduct governing the WUB Learning Management System. Acceptance is valid for 30 days.';
$string['policy_role_badge_student'] = 'Student Access Agreement';
$string['policy_role_badge_teacher'] = 'Faculty & Instructor Agreement';
$string['policy_role_badge_admin'] = 'Staff & Administrator Agreement';
$string['policy_agree_checkbox'] = 'I have read, understood, and agree to strictly abide by the 20 University E-Learning Policies, Academic Integrity Code, and System Regulations.';
$string['policy_btn_accept'] = 'Accept & Proceed';
$string['policy_btn_decline'] = 'Decline & Exit';
$string['policy_validity_notice'] = 'Policy Version {$a->version} • Acceptance valid for {$a->days} days';

// 20 University Policies in 4 Categories.
$string['category_account_security'] = 'Part I: Account Security, Identity & Acceptable Use';
$string['category_academic_assessments'] = 'Part II: Academic Integrity, Assessments & Submissions';
$string['category_conduct_communication'] = 'Part III: Digital Conduct, Communication & Forum Etiquette';
$string['category_ip_privacy_governance'] = 'Part IV: Intellectual Property, Privacy & Governance';

$string['policy_1_title'] = '1. Account Ownership & Personal Identity Responsibility';
$string['policy_1_content'] = 'Every user account on the WUB LMS is issued exclusively to an individual. Users are strictly responsible for all academic, administrative, and communicative activities carried out under their assigned username and credentials.';

$string['policy_2_title'] = '2. Credential Confidentiality & Anti-Sharing Regulations';
$string['policy_2_content'] = 'Sharing your LMS password, session tokens, or authentication access with any other person is strictly prohibited. Any unauthorized access resulting from credential sharing will be treated as an institutional violation.';

$string['policy_3_title'] = '3. Authorized Device & Network Access';
$string['policy_3_content'] = 'Users must access the LMS through secure devices and networks. The university monitors authentication attempts and logs access IPs for institutional security and audit compliance.';

$string['policy_4_title'] = '4. Immediate Security Incident Reporting';
$string['policy_4_content'] = 'Users must immediately report any suspected compromise of their account, unexpected password changes, or unauthorized activity to the IT Support Team at support@wub.edu.bd.';

$string['policy_5_title'] = '5. Academic Honesty & Anti-Plagiarism Standards';
$string['policy_5_content'] = 'All assessments, quizzes, assignments, and submitted coursework must be the student’s own original work. Plagiarism, unauthorized AI generation, and collusion are subject to academic disciplinary action.';

$string['policy_6_title'] = '6. Assessment Integrity & Examination Regulations';
$string['policy_6_content'] = 'During online quizzes, midterms, and final examinations, students must adhere to the proctoring rules, timing constraints, and submission deadlines set by course instructors and the Examination Controller.';

$string['policy_7_title'] = '7. Timely Assignment Submissions & Technical Responsibility';
$string['policy_7_content'] = 'Students are responsible for submitting coursework ahead of deadlines. Technical difficulties should be reported immediately to the course instructor with relevant evidence before the due date expires.';

$string['policy_8_title'] = '8. Grading Integrity & Faculty Responsibility';
$string['policy_8_content'] = 'Instructors must evaluate student work impartially, record grades accurately in accordance with university grading policies, and maintain student academic records confidentially.';

$string['policy_9_title'] = '9. Respectful Digital Communication & Etiquette';
$string['policy_9_content'] = 'All forum discussions, messaging, and online interactions must maintain a professional, respectful tone. Harassment, abusive language, discriminatory remarks, or personal attacks will not be tolerated.';

$string['policy_10_title'] = '10. Prohibition of Commercial & Unsolicited Messaging';
$string['policy_10_content'] = 'The LMS communication channels may not be used for advertising, commercial solicitation, spam, political campaigning, or non-academic mass messaging.';

$string['policy_11_title'] = '11. Constructive Participation in Learning Forums';
$string['policy_11_content'] = 'Discussion forums are dedicated academic spaces. Contributions should be relevant, constructive, and adhere to scholarly discourse guidelines.';

$string['policy_12_title'] = '12. Online Classroom & Live Session Decorum';
$string['policy_12_content'] = 'During synchronous online lectures, workshops, or meetings, participants must observe professional decorum, keep microphones muted unless speaking, and respect peers and facilitators.';

$string['policy_13_title'] = '13. Copyright & Intellectual Property Protection';
$string['policy_13_content'] = 'Course materials, recorded lectures, lecture slides, and examination questions are the intellectual property of WUB and instructors. Unauthorized reproduction, distribution, or uploading to third-party platforms is prohibited.';

$string['policy_14_title'] = '14. Protection of Student Academic & Personal Data';
$string['policy_14_content'] = 'The university complies with privacy guidelines. Personal and academic data stored in the LMS is collected solely for educational administration and will not be disclosed to unauthorized third parties.';

$string['policy_15_title'] = '15. System Integrity & Anti-Tampering Standards';
$string['policy_15_content'] = 'Any attempt to probe, scan, bypass security measures, exploit vulnerabilities, or disrupt LMS infrastructure is a severe violation resulting in immediate disciplinary and legal action.';

$string['policy_16_title'] = '16. Prohibited Content & Malicious File Uploads';
$string['policy_16_content'] = 'Uploading malware, corrupted files, offensive media, or unauthorized executable code is strictly forbidden. Automated security scanners inspect all uploads.';

$string['policy_17_title'] = '17. System Maintenance & Service Availability';
$string['policy_17_content'] = 'Scheduled system maintenance will be communicated in advance. WUB IT strives for high availability but advises students not to delay essential submissions until the final minutes.';

$string['policy_18_title'] = '18. Disciplinary Procedures & Penalties';
$string['policy_18_content'] = 'Violations of LMS policies are referred to the University Disciplinary Committee and may result in course failure, academic suspension, expulsion, or legal proceedings.';

$string['policy_19_title'] = '19. Policy Revision & Periodic Updates';
$string['policy_19_content'] = 'WUB reserves the right to revise institutional e-learning policies. Material changes will require renewed user acknowledgement upon subsequent login.';

$string['policy_20_title'] = '20. Acceptance of Terms & Governance';
$string['policy_20_content'] = 'By clicking "Accept", you confirm that you have read, understood, and agreed to be bound by these 20 University E-Learning Policies for your assigned role.';

// Privacy Provider Strings.
$string['privacy:metadata:waivers'] = 'Stores temporary financial access waivers granted to students.';
$string['privacy:metadata:waivers:userid'] = 'The ID of the student user who received the waiver.';
$string['privacy:metadata:waivers:status'] = 'Active or revoked status of the waiver.';
$string['privacy:metadata:waivers:timeend'] = 'The timestamp when the waiver expires.';
$string['privacy:metadata:waivers:grantedby'] = 'The user ID of the administrator who granted the waiver.';
$string['privacy:metadata:waivers:reason'] = 'Administrative reason for granting the waiver.';
$string['privacy:metadata:policy_accept'] = 'Stores user policy acceptances, timestamps, and device tokens.';
$string['privacy:metadata:policy_accept:userid'] = 'The ID of the user who accepted the policy.';
$string['privacy:metadata:policy_accept:role'] = 'The role under which the policy was accepted.';
$string['privacy:metadata:policy_accept:policyversion'] = 'The version of the policy accepted.';
$string['privacy:metadata:policy_accept:timeaccepted'] = 'Timestamp of acceptance.';
$string['privacy:metadata:audit'] = 'Stores authentication and access control audit records.';
$string['privacy:metadata:audit:userid'] = 'The ID of the user associated with the audit event.';
$string['privacy:metadata:audit:action'] = 'The authentication or access control action performed.';
$string['privacy:metadata:audit:status'] = 'The outcome of the audit event.';
$string['privacy:metadata:audit:timecreated'] = 'Timestamp of the event.';
