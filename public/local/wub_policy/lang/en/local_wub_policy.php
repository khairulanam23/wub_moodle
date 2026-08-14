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
 * Language strings for local_wub_policy with 20 Comprehensive University E-Learning Policies.
 *
 * @package    local_wub_policy
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'WUB E-Learning Terms & University Policies';
$string['policyheader'] = 'University E-Learning Policies & Code of Conduct';
$string['policysubtitle'] = 'Please review the institutional terms and conditions governing the World University of Bangladesh Learning Management System (LMS). Acceptance is required independently for each user role and remains valid for 30 days.';
$string['role_student'] = 'Student Portal';
$string['role_teacher'] = 'Faculty & Instructor Portal';
$string['role_admin'] = 'System Administration Portal';

$string['setting_policyversion'] = 'Policy Version';
$string['setting_policyversion_desc'] = 'Current active version string of the university e-learning policy. Incrementing this value prompts all users to re-accept the updated policy on their next login attempt.';
$string['setting_policyexpiry_days'] = 'Policy Expiry Period (Days)';
$string['setting_policyexpiry_days_desc'] = 'Number of days an accepted policy remains valid before requiring renewed acceptance (default is 30 days).';

$string['agreecheckbox'] = 'I have read, understood, and agree to strictly abide by the 20 University E-Learning Policies, Academic Integrity Code, and System Regulations for my selected role.';
$string['continuebtn'] = 'Accept & Proceed to Login';
$string['cancelbtn'] = 'Decline & Return to Landing';
$string['mustagree'] = 'You must review the policies and check the confirmation box before proceeding.';
$string['invalidrole'] = 'Invalid role specified for policy review.';
$string['policyversionlabel'] = 'Policy Version';
$string['policyvaliditylabel'] = 'Acceptance Validity';
$string['policyvalidityvalue'] = '30 Days per Role';
$string['tableofcontents'] = 'Policy Navigation';
$string['role_notice_student'] = 'You are reviewing these policies under the <strong>Student Role</strong>. As an enrolled student, you are held to strict standards of academic honesty, independent assessment submissions, and respectful peer collaboration.';
$string['role_notice_teacher'] = 'You are reviewing these policies under the <strong>Teacher / Instructor Role</strong>. You are responsible for ethical grade administration, copyright-compliant teaching material uploads, and protecting confidential student academic records.';
$string['role_notice_admin'] = 'You are reviewing these policies under the <strong>Administrator Role</strong>. Administrative access entails privileged system control, strict data governance obligations, and comprehensive audit accountability.';

// Category Headers
$string['category_account_security'] = 'Part I: Account Security, Access & Acceptable Use';
$string['category_academic_assessments'] = 'Part II: Academic Integrity, Assessments & Submissions';
$string['category_conduct_communication'] = 'Part III: Digital Conduct, Communication & Forum Etiquette';
$string['category_ip_privacy_governance'] = 'Part IV: Intellectual Property, Privacy, System Safeguards & Violations';

// 20 Detailed Policies
$string['policy_1_title'] = '1. Account Ownership & Personal Identity Responsibility';
$string['policy_1_content'] = '<p>Every user account provisioned on the World University of Bangladesh (WUB) Learning Management System (LMS) is issued exclusively to an individual authenticated user. Users are strictly responsible for all academic, administrative, and communicative activities carried out under their assigned username and credentials.</p>
<p>Impersonation of another student, faculty member, or staff member, as well as accessing or attempting to access an account not explicitly registered to your identity, constitutes a major violation of university policy. Account sharing for convenience, group coursework, or proxy attendance is strictly prohibited under all circumstances.</p>';

$string['policy_2_title'] = '2. Credential Protection, Password Security & Two-Factor Authentication';
$string['policy_2_content'] = '<p>Users are required to maintain the confidentiality of their passwords and multi-factor authentication tokens at all times. Passwords must comply with institutional complexity standards, combining uppercase and lowercase letters, numeric digits, and special characters. Users must never share passwords with peers, family members, or third parties.</p>
<p>In the event that an account compromise, credential leak, or unauthorized login attempt is suspected, the user must immediately change their password and notify the University IT Helpdesk at <strong>moodle@wub.edu.bd</strong>. University personnel will never request your password via email, phone, or instant messaging.</p>';

$string['policy_3_title'] = '3. Acceptable Use of Institutional LMS Infrastructure';
$string['policy_3_content'] = '<p>The WUB LMS infrastructure is provided exclusively for legitimate academic instruction, learning, research, and official university administrative purposes. Any commercial exploitation, unauthorized advertising, solicitations, or transmission of non-academic materials is strictly forbidden.</p>
<p>Users must not utilize university server bandwidth or storage repositories for hosting personal media archives, copyrighted software, non-educational files, or peer-to-peer file sharing services. The university reserves the right to monitor storage utilization to preserve system performance.</p>';

$string['policy_4_title'] = '4. Academic Integrity & Institutional Honor Code';
$string['policy_4_content'] = '<p>Academic honesty is the cornerstone of World University of Bangladesh. All students and academic personnel are bound by the University Honor Code, which demands absolute honesty, authentic scholarship, and ethical intellectual conduct across all online learning modules.</p>
<p>Engaging in academic dishonesty, including but not limited to contract cheating, fabrication of data, unauthorized assistance during evaluations, and falsification of academic documents, will result in immediate disciplinary referral to the University Academic Disciplinary Committee.</p>';

$string['policy_5_title'] = '5. Anti-Plagiarism Standards & Artificial Intelligence (AI) Usage Guidelines';
$string['policy_5_content'] = '<p>All submitted assignments, project reports, essays, computer programs, and thesis documentation must represent the student’s authentic, individual, and unassisted intellectual work, unless explicitly designated as group work by the course instructor. Proper academic citations and references must accompany all quotations, paraphrased concepts, and third-party data sources.</p>
<p>The unauthorized submission of automated AI-generated content (e.g., ChatGPT, generative language models) without explicit faculty authorization and clear attribution is treated as academic misconduct. All digital submissions are subject to automated similarity verification and plagiarism detection scans.</p>';

$string['policy_6_title'] = '6. Examination, Quiz & Online Assessment Protocol';
$string['policy_6_content'] = '<p>Online examinations, timed quizzes, and continuous assessments administered via the LMS must be taken solely by the registered student without unapproved external aids, collaboration, or proxy test-takers. Opening unapproved browser tabs, utilizing secondary devices, screen sharing, or communicating during an active examination session is prohibited.</p>
<p>Where online proctoring, webcam monitoring, or secure browser requirements are mandated by the faculty, students must comply with environmental verification requirements. Any interruption, suspicious window switching, or abnormal test submission timing is permanently recorded in system audit logs and subject to faculty review.</p>';

$string['policy_7_title'] = '7. Assignment Submission Guidelines & Deadline Compliance';
$string['policy_7_content'] = '<p>Assignments must be uploaded through the official LMS submission portals prior to the stipulated due date and time. Students are responsible for verifying that uploaded files are complete, non-corrupted, in the required format (e.g., PDF, DOCX, ZIP), and properly submitted before the deadline.</p>
<p>Claims of technical failure must be supported by verifiable timestamped system logs or helpdesk communication prior to the deadline. Instructors reserve the right to apply institutional late-submission grade deductions or reject overdue submissions in accordance with individual course syllabi.</p>';

$string['policy_8_title'] = '8. Active Course Participation, Attendance & Engagement Standards';
$string['policy_8_content'] = '<p>Regular and timely digital engagement with enrolled courses is mandatory. The LMS automatically records access frequency, module completion timestamps, video lecture playback progress, and participation in required interactive learning objects to establish verifiable digital attendance records.</p>
<p>Prolonged inactivity, unexcused absence from synchronous sessions, or failure to access required course materials may result in administrative warnings, attendance grade forfeitures, or de-registration from the course in accordance with university academic regulations.</p>';

$string['policy_9_title'] = '9. Professional Digital Netiquette & Respectful Communication';
$string['policy_9_content'] = '<p>All communications conducted through the LMS—including internal messaging, forum discussions, announcements, and peer feedback—must remain professional, constructive, and respectful. The university does not tolerate discriminatory remarks, harassment, cyberbullying, hate speech, derogatory language, or personal attacks based on race, gender, religion, ethnicity, or disability.</p>
<p>Users must adhere to formal academic communication etiquette when addressing faculty members, administrative staff, and fellow students. Inappropriate, threatening, or offensive messages will result in immediate communication privilege suspension and disciplinary investigation.</p>';

$string['policy_10_title'] = '10. Discussion Forum Regulations & Collaborative Workspace Conduct';
$string['policy_10_content'] = '<p>Discussion forums and collaborative wiki spaces are designed to foster intellectual discourse, peer learning, and collaborative problem-solving. Posts must remain on-topic, relevant to the course curriculum, and academically substantive.</p>
<p>Posting spam, chain messages, unauthorized promotional links, examination question leaks, or assignment answer keys to public course boards is strictly prohibited. Course instructors and forum moderators retain the authority to hide, remove, or archive inappropriate posts and report repeat offenders.</p>';

$string['policy_11_title'] = '11. Intellectual Property, Copyright Compliance & Institutional Ownership';
$string['policy_11_content'] = '<p>All course syllabi, lecture slides, video recordings, laboratory manuals, quiz questions, software codes, and assessment materials published on the LMS are the intellectual property of the World University of Bangladesh and its respective faculty members, protected under national and international copyright laws.</p>
<p>Students and system users are granted a limited, revocable, non-exclusive license to access and review course materials solely for their personal academic enrichment during the active semester. No intellectual property rights are transferred to the user.</p>';

$string['policy_12_title'] = '12. Prohibition on Commercial Redistribution & Third-Party Sharing';
$string['policy_12_content'] = '<p>Under no circumstances may students or unauthorized third parties upload, distribute, broadcast, sell, or license university course materials, lecture videos, exam questions, or instructor solutions to external websites, commercial document-sharing platforms (e.g., Course Hero, Chegg, Studocu), or public social media repositories.</p>
<p>Violators will be subject to immediate academic expulsion, legal copyright infringement proceedings, and civil liability claims for damages caused by the unauthorized dissemination of institutional materials.</p>';

$string['policy_13_title'] = '13. Student Privacy, Confidentiality & Institutional Data Protection';
$string['policy_13_content'] = '<p>The university is committed to safeguarding the personal data, academic records, grading histories, and privacy of all students and staff in compliance with institutional data governance policies and recognized international privacy standards.</p>
<p>Faculty, teaching assistants, and administrative staff must never disclose student grades, personal contact information, or sensitive academic standings in publicly accessible forums or unencrypted channels. LMS activity data is processed strictly for educational evaluation and system administration.</p>';

$string['policy_14_title'] = '14. Audio/Video Recordings, Screen Captures & Proctoring Compliance';
$string['policy_14_content'] = '<p>Unauthorized audio or video recording of live lectures, virtual classroom sessions, private consultation hours, or proctored assessments without the explicit written consent of the presiding instructor is strictly prohibited.</p>
<p>Taking screen captures or video recordings of other participating students or instructors and publishing them on public platforms or private chat groups constitutes an invasion of privacy and a severe breach of the university code of conduct.</p>';

$string['policy_15_title'] = '15. LMS System Security, Threat Mitigation & Infrastructure Safeguards';
$string['policy_15_content'] = '<p>The security and availability of the LMS are critical to university operations. Users must not attempt to circumvent system firewalls, tamper with URL structures, exploit security vulnerabilities, or inject malicious code, scripts, or cross-site payloads into any portal interface.</p>
<p>Automated scraping, batch crawling, unauthorized API access, or excessive programmatic queries that degrade system performance or destabilize database responsiveness are prohibited and automatically blocked by institutional web application firewalls.</p>';

$string['policy_16_title'] = '16. Prohibited Cyber Activities & Misuse of System Privileges';
$string['policy_16_content'] = '<p>Engaging in malicious cyber activities—including denial-of-service (DoS) attacks, unauthorized network sniffing, brute-force password cracking, SQL injection attempts, session hijacking, or distributing malware via LMS attachment portals—will trigger immediate automated account suspension and law enforcement notification.</p>
<p>Users granted elevated or administrative roles must never abuse privileges to alter gradebooks, tamper with attendance registers, or bypass institutional financial clearance checks.</p>';

$string['policy_17_title'] = '17. Technical Prerequisites, Supported Devices & Browser Compliance';
$string['policy_17_content'] = '<p>Users are responsible for ensuring their personal computing hardware, operating systems, and web browsers meet the baseline technical specifications required for stable LMS access. Recommended browsers include the latest versions of Google Chrome, Mozilla Firefox, Microsoft Edge, and Safari.</p>
<p>Students must maintain reliable internet connectivity and verify audio/video peripheral functionality prior to scheduled examinations or live academic sessions. Incompatible legacy browsers or unauthorized third-party browser extensions that interfere with JavaScript execution are not supported.</p>';

$string['policy_18_title'] = '18. Fair, Responsible & Proportionate Bandwidth / Compute Consumption';
$string['policy_18_content'] = '<p>University computational resources, server memory, cloud storage, and network bandwidth are shared institutional assets. Users are required to consume system resources responsibly and avoid unnecessary simultaneous high-bandwidth streaming or mass bulk file downloads during peak instructional hours.</p>
<p>Course instructors should optimize media files, images, and document sizes prior to uploading to facilitate rapid mobile accessibility for all students across varying network conditions.</p>';

$string['policy_19_title'] = '19. Mandatory Incident Reporting & Vulnerability Disclosure Protocol';
$string['policy_19_content'] = '<p>Users who discover any technical defect, data leakage, unauthorized access avenue, or security vulnerability within the LMS are obligated to report the matter immediately and confidentially to the University IT Security Office at <strong>moodle@wub.edu.bd</strong>.</p>
<p>Users must not test, demonstrate, or exploit security vulnerabilities against fellow users or publicize unpatched flaws on external social media channels. Responsible and ethical disclosure is vital to maintaining a secure e-learning environment.</p>';

$string['policy_20_title'] = '20. Policy Enforcement, Disciplinary Governance & Non-Compliance Sanctions';
$string['policy_20_content'] = '<p>Compliance with these 20 University E-Learning Policies is mandatory for all students, faculty members, and administrative personnel. Failure to abide by these provisions constitutes misconduct and will result in progressive disciplinary action proportional to the severity of the violation.</p>
<p>Potential sanctions include formal written reprimands, grade penalties, temporary or permanent LMS account revocation, course failure, academic suspension, expulsion from the university, and referral to civil or criminal law enforcement authorities for statutory cyber offenses.</p>';

$string['privacy:metadata'] = 'The WUB Terms & Policy plugin stores role-specific policy acceptance records in the database (table mdl_local_wub_policy_accept) including user ID, device token, accepted role, policy version, timestamp, IP address, and user agent for compliance and audit verification.';
