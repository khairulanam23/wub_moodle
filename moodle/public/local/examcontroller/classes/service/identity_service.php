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

namespace local_examcontroller\service;

use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/moodlelib.php');

/**
 * Authoritative Moodle Identity Service for ExamController Integration.
 *
 * Provides:
 * 1. Password verification against Moodle core hashing.
 * 2. Role resolution (STUDENT, TEACHER, ADMIN).
 * 3. Token generation with least-privilege scopes.
 * 4. Audit logging into mdl_examcontroller_audit.
 *
 * @package    local_examcontroller
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class identity_service {

    /**
     * Authenticate and resolve identity for ExamController.
     *
     * @param string $identifier Student username / registration ID, or teacher username / email.
     * @param string $password Cleartext password.
     * @param string|null $expectedRole Optional expected role ('STUDENT' or 'TEACHER').
     * @param string $clientIp Client IP address for audit.
     * @return array
     */
    public function verify_credentials(string $identifier, string $password, ?string $expectedRole = null, string $clientIp = '127.0.0.1'): array {
        global $DB;

        $identifier = trim($identifier);
        if (empty($identifier) || empty($password)) {
            $this->log_audit('IDENTITY_VERIFY', $identifier, $clientIp, 'FAILURE', ['error' => 'MISSING_CREDENTIALS']);
            return [
                'success' => false,
                'error_code' => 'MISSING_CREDENTIALS',
                'message' => 'Identifier and password are required.',
                'http_status' => 400,
            ];
        }

        // Resolve user deterministically with ambiguity collision guard
        $resolved = $this->find_user_by_identifier($identifier);

        if (!empty($resolved['ambiguous'])) {
            $this->log_audit('IDENTITY_VERIFY', $identifier, $clientIp, 'REJECTED', ['error' => 'AMBIGUOUS_IDENTITY']);
            return [
                'success' => false,
                'error_code' => 'AMBIGUOUS_IDENTITY',
                'message' => 'Ambiguous identity: multiple user accounts match this identifier.',
                'http_status' => 409,
            ];
        }

        $user = $resolved['user'];

        if (!$user) {
            $this->log_audit('IDENTITY_VERIFY', $identifier, $clientIp, 'FAILURE', ['error' => 'USER_NOT_FOUND']);
            return [
                'success' => false,
                'error_code' => 'INVALID_CREDENTIALS',
                'message' => 'The provided credentials do not match our records.',
                'http_status' => 401,
            ];
        }

        // Check account status
        if (!empty($user->suspended)) {
            $this->log_audit('IDENTITY_VERIFY', $identifier, $clientIp, 'FAILURE', ['error' => 'ACCOUNT_SUSPENDED']);
            return [
                'success' => false,
                'error_code' => 'ACCOUNT_SUSPENDED',
                'message' => 'This account has been deactivated or suspended.',
                'http_status' => 403,
            ];
        }

        // Validate password against Moodle's native hash
        if (!validate_internal_user_password($user, $password)) {
            // Also test UMS fallback if local_wub_auth is present and active
            $umsValid = false;
            if (class_exists('\local_wub_auth\service\ums_authenticator')) {
                $umsAuth = new \local_wub_auth\service\ums_authenticator();
                if ($umsAuth->is_enabled()) {
                    $umsRes = $umsAuth->authenticate($user->username, $password);
                    if ($umsRes['success']) {
                        $umsValid = true;
                        // Update local Moodle password hash
                        update_internal_user_password($user, $password);
                    }
                }
            }

            if (!$umsValid) {
                $this->log_audit('IDENTITY_VERIFY', $identifier, $clientIp, 'FAILURE', ['error' => 'INVALID_PASSWORD']);
                return [
                    'success' => false,
                    'error_code' => 'INVALID_CREDENTIALS',
                    'message' => 'The provided credentials do not match our records.',
                    'http_status' => 401,
                ];
            }
        }

        // Resolve primary role for ExamController
        $resolvedRole = $this->resolve_role($user);

        // Reject accounts without recognized role
        if (!in_array($resolvedRole, ['STUDENT', 'TEACHER', 'ADMIN'], true)) {
            $this->log_audit('IDENTITY_VERIFY', $identifier, $clientIp, 'REJECTED', [
                'error' => 'UNSUPPORTED_ROLE',
                'actual' => $resolvedRole
            ]);
            return [
                'success' => false,
                'error_code' => 'UNSUPPORTED_ROLE',
                'message' => 'User account does not have a recognized student or staff role.',
                'http_status' => 403,
            ];
        }

        // If expected role is provided, verify match
        if ($expectedRole !== null && strtoupper($expectedRole) !== $resolvedRole) {
            $this->log_audit('IDENTITY_VERIFY', $identifier, $clientIp, 'REJECTED', [
                'error' => 'ROLE_MISMATCH',
                'expected' => $expectedRole,
                'actual' => $resolvedRole
            ]);
            return [
                'success' => false,
                'error_code' => 'ROLE_MISMATCH',
                'message' => "Access denied. Account does not have {$expectedRole} role.",
                'http_status' => 403,
            ];
        }

        // Enforce institutional financial clearance for students
        $paymentClearance = null;
        if ($resolvedRole === 'STUDENT' && class_exists('\local_wub_auth\service\financial_clearance_service')) {
            $clearanceService = new \local_wub_auth\service\financial_clearance_service();
            $clearance = $clearanceService->check_clearance($user);
            $paymentClearance = $clearance->to_decision_array();

            if (!$clearance->is_allowed()) {
                $this->log_audit('IDENTITY_VERIFY', $identifier, $clientIp, 'REJECTED', [
                    'error' => 'PAYMENT_RESTRICTION',
                    'clearance' => $paymentClearance,
                ]);
                return [
                    'success' => false,
                    'error_code' => 'PAYMENT_RESTRICTION',
                    'message' => $clearance->get_reason(),
                    'data' => $paymentClearance,
                    'http_status' => 403,
                ];
            }
        }

        // Generate scoped integration token
        $scopes = $resolvedRole === 'STUDENT' ? ['student:exam_take'] : ($resolvedRole === 'TEACHER' ? ['teacher:invigilate'] : ['admin:manage']);
        $token = $this->generate_token($user, $resolvedRole, $scopes);

        $this->log_audit('IDENTITY_VERIFY', $identifier, $clientIp, 'SUCCESS', [
            'moodle_user_id' => $user->id,
            'role' => $resolvedRole,
        ]);

        // Resolve academic context (courses, sections, teacher roles)
        $academic = $this->get_user_academic_context($user, $resolvedRole);

        return [
            'success' => true,
            'data' => [
                'moodleUserId' => (int)$user->id,
                'universityId' => (string)$user->username,
                'registrationNo' => (string)($user->idnumber ?: $user->username),
                'fullName' => fullname($user),
                'email' => (string)$user->email,
                'department' => (string)($user->department ?? ''),
                'role' => $resolvedRole,
                'status' => 'active',
                'token' => $token,
                'scopes' => $scopes,
                'courses' => $academic['courses'] ?? [],
                'sections' => $academic['sections'] ?? [],
                'isHod' => $academic['isHod'] ?? false,
                'paymentClearance' => $paymentClearance,
                'expiresAt' => date('c', time() + (int)get_config('local_examcontroller', 'token_ttl')),
            ],
            'http_status' => 200,
        ];
    }

    /**
     * Resolve user's primary role for ExamController.
     *
     * @param stdClass $user
     * @return string 'STUDENT', 'TEACHER', or 'ADMIN'
     */
    public function resolve_role(stdClass $user): string {
        global $DB;

        // Check if administrator
        if (is_siteadmin($user->id)) {
            return 'ADMIN';
        }

        // Check if teacher (has editingteacher or teacher role in course context)
        $isTeacher = $DB->record_exists_sql("
            SELECT ra.id
            FROM {role_assignments} ra
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} ctx ON ctx.id = ra.contextid
            WHERE ra.userid = :userid
              AND r.shortname IN ('editingteacher', 'teacher')
              AND ctx.contextlevel = 50
        ", ['userid' => $user->id]);

        if ($isTeacher) {
            return 'TEACHER';
        }

        // Also check if teacher format ID (W000xxx) or employee ID
        if (str_starts_with((string)($user->idnumber ?? ''), 'W00') || !preg_match('/^[0-9]{9,10}$/', (string)$user->username)) {
            // Check if designated as teacher in department
            if (!empty($user->department) && !empty($user->email) && str_ends_with($user->email, '@wub.edu.bd') && !str_contains($user->email, '@student.wub.edu.bd')) {
                return 'TEACHER';
            }
        }

        // Student validation: matches valid 9 or 10-digit student ID or student email domain
        if (preg_match('/^[0-9]{9,10}$/', (string)$user->username) || str_ends_with((string)($user->email ?? ''), '@student.wub.edu.bd')) {
            return 'STUDENT';
        }

        // Check if enrolled as student in any course context
        $isStudent = $DB->record_exists_sql("
            SELECT ra.id
            FROM {role_assignments} ra
            JOIN {role} r ON r.id = ra.roleid
            WHERE ra.userid = :userid
              AND r.shortname = 'student'
        ", ['userid' => $user->id]);

        if ($isStudent) {
            return 'STUDENT';
        }

        return 'UNKNOWN';
    }

    /**
     * Resolve authoritative academic courses and sections for user.
     *
     * @param stdClass $user
     * @param string $role
     * @return array ['courses' => array, 'sections' => array, 'isHod' => bool]
     */
    public function get_user_academic_context(stdClass $user, string $role): array {
        global $DB, $CFG;

        if ($role === 'ADMIN') {
            return [
                'courses' => [],
                'sections' => [],
                'isHod' => false,
                'isSiteAdmin' => true,
            ];
        }

        if ($role === 'TEACHER') {
            // Check authoritative HOD status from structured Moodle role assignments
            $isHod = $this->is_user_hod($user);

            // Query courses where user is enrolled as teacher
            $courses = $DB->get_records_sql("
                SELECT DISTINCT c.id, c.shortname, c.fullname, c.startdate
                FROM {course} c
                JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                JOIN {role_assignments} ra ON ra.contextid = ctx.id
                JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('editingteacher', 'teacher')
                WHERE ra.userid = :uid AND c.id > 1 AND c.startdate > 0
                ORDER BY c.id ASC
            ", ['uid' => $user->id]);

            $courseList = [];
            $sectionList = [];

            foreach ($courses as $c) {
                $courseList[] = [
                    'moodleCourseId' => (int)$c->id,
                    'courseCode' => (string)$c->shortname,
                    'courseName' => (string)$c->fullname,
                    'semester' => 'Fall 2026',
                ];

                if ($isHod) {
                    // HOD sees all groups in this course
                    $groups = $DB->get_records('groups', ['courseid' => $c->id], 'name ASC');
                    foreach ($groups as $g) {
                        $sectionList[] = [
                            'moodleGroupId' => (int)$g->id,
                            'moodleCourseId' => (int)$c->id,
                            'courseCode' => (string)$c->shortname,
                            'courseName' => (string)$c->fullname,
                            'sectionName' => (string)$g->name,
                            'semester' => 'Fall 2026',
                            'role' => 'HOD',
                        ];
                    }
                } else {
                    // Regular teacher: only groups where teacher is in groups_members
                    $groups = $DB->get_records_sql("
                        SELECT g.id, g.name
                        FROM {groups} g
                        JOIN {groups_members} gm ON gm.groupid = g.id
                        WHERE g.courseid = :cid AND gm.userid = :uid
                        ORDER BY g.name ASC
                    ", ['cid' => $c->id, 'uid' => $user->id]);

                    foreach ($groups as $g) {
                        $sectionList[] = [
                            'moodleGroupId' => (int)$g->id,
                            'moodleCourseId' => (int)$c->id,
                            'courseCode' => (string)$c->shortname,
                            'courseName' => (string)$c->fullname,
                            'sectionName' => (string)$g->name,
                            'semester' => 'Fall 2026',
                            'role' => 'COURSE_TEACHER',
                        ];
                    }
                }
            }

            return [
                'courses' => $courseList,
                'sections' => $sectionList,
                'isHod' => $isHod,
            ];
        }

        // Student role
        $courses = $DB->get_records_sql("
            SELECT DISTINCT c.id, c.shortname, c.fullname, c.startdate
            FROM {course} c
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            JOIN {role_assignments} ra ON ra.contextid = ctx.id
            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
            WHERE ra.userid = :uid AND c.id > 1 AND c.startdate > 0
            ORDER BY c.id ASC
        ", ['uid' => $user->id]);

        $courseList = [];
        $sectionList = [];

        foreach ($courses as $c) {
            $courseList[] = [
                'moodleCourseId' => (int)$c->id,
                'courseCode' => (string)$c->shortname,
                'courseName' => (string)$c->fullname,
                'semester' => 'Fall 2026',
            ];

            // Get groups where student is assigned
            $groups = $DB->get_records_sql("
                SELECT g.id, g.name
                FROM {groups} g
                JOIN {groups_members} gm ON gm.groupid = g.id
                WHERE g.courseid = :cid AND gm.userid = :uid
                ORDER BY g.name ASC
            ", ['cid' => $c->id, 'uid' => $user->id]);

            foreach ($groups as $g) {
                $sectionList[] = [
                    'moodleGroupId' => (int)$g->id,
                    'moodleCourseId' => (int)$c->id,
                    'courseCode' => (string)$c->shortname,
                    'courseName' => (string)$c->fullname,
                    'sectionName' => (string)$g->name,
                    'semester' => 'Fall 2026',
                ];
            }
        }

        return [
            'courses' => $courseList,
            'sections' => $sectionList,
            'isHod' => false,
        ];
    }

    /**
     * Generate signed cryptographic identity token with scopes.
     *
     * @param stdClass $user
     * @param string $role
     * @param array $scopes
     * @return string
     */
    public function generate_token(stdClass $user, string $role, array $scopes): string {
        $now = time();
        $ttl = (int)get_config('local_examcontroller', 'token_ttl');
        if ($ttl <= 0) $ttl = 300;

        $claims = [
            'iss' => 'wub_moodle',
            'aud' => 'wub_examcontroller',
            'sub' => (int)$user->id,
            'role' => $role,
            'scopes' => $scopes,
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(16)),
            'user' => [
                'moodleUserId' => (int)$user->id,
                'universityId' => (string)$user->username,
                'registrationNo' => (string)($user->idnumber ?: $user->username),
                'email' => (string)$user->email,
                'fullName' => fullname($user),
                'department' => (string)($user->department ?? ''),
            ],
        ];

        $payload = rtrim(strtr(base64_encode((string)json_encode($claims)), '+/', '-_'), '=');
        $secret = signature_service::get_secret();
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $secret, true)), '+/', '-_'), '=');

        return $payload . '.' . $signature;
    }

    /**
     * Query verified teachers directory.
     *
     * @param string|null $department
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function get_teachers(?string $department = null, int $page = 1, int $perPage = 50): array {
        global $DB;

        $params = [];
        $where = "deleted = 0 AND idnumber LIKE 'W00%'";
        if (!empty($department)) {
            $where .= " AND department = :dept";
            $params['dept'] = $department;
        }

        $total = $DB->count_records_select('user', $where, $params);
        $offset = max(0, ($page - 1) * $perPage);
        $records = $DB->get_records_select('user', $where, $params, 'id ASC', 'id, username, idnumber, firstname, lastname, email, department', $offset, $perPage);

        $items = [];
        foreach ($records as $r) {
            $items[] = [
                'moodleUserId' => (int)$r->id,
                'teacherId' => (string)$r->idnumber,
                'username' => (string)$r->username,
                'fullName' => fullname($r),
                'email' => (string)$r->email,
                'department' => (string)$r->department,
                'role' => 'TEACHER',
            ];
        }

        return [
            'success' => true,
            'data' => $items,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
            ],
            'http_status' => 200,
        ];
    }

    /**
     * Resolve a user by raw identifier deterministically, supporting standard WUB formats:
     * - Canonical 10-digit numeric student ID (e.g. 0826771905, 0326745565)
     * - Unpadded 9-digit student ID for leading-zero cohorts (e.g. 826771905 -> 0826771905)
     * - Canonical 9-digit numeric student ID (e.g. 152646820)
     * - Institutional registration ID with/without whitespace (e.g. WUB08/26/77/1905, WUB 08/26/77/1905)
     * - Institutional email (e.g. 0826771905@student.wub.edu.bd, 826771905@student.wub.edu.bd)
     * - Teacher staff username, email, or employee ID (e.g. jannatul.naeem, W000918)
     *
     * Enforces ambiguity guard: if multiple distinct users match, rejects to prevent collision.
     *
     * @param string $identifier
     * @return array ['user' => ?stdClass, 'ambiguous' => bool]
     */
    public function find_user_by_identifier(string $identifier): array {
        global $DB;

        $raw = trim($identifier);
        if ($raw === '') {
            return ['user' => null, 'ambiguous' => false];
        }

        $lower = strtolower($raw);
        $candidates = [$lower];

        // 1. Email prefix probe
        if (str_contains($lower, '@')) {
            $prefix = explode('@', $lower)[0];
            $candidates[] = $prefix;
        }

        // 2. Normalized registration ID (strip spaces)
        $cleanReg = strtolower(preg_replace('/\s+/', '', $raw));
        $candidates[] = $cleanReg;

        // 3. Numeric digit extraction and zero-padding normalization
        $digits = preg_replace('/[^0-9]/', '', $raw);
        if (!empty($digits)) {
            $candidates[] = $digits;
            $candidates[] = $digits . '@student.wub.edu.bd';

            if (strlen($digits) === 9) {
                // If 9 digits, probe 10-digit padded variant (e.g. 826771905 -> 0826771905)
                $padded10 = '0' . $digits;
                $candidates[] = $padded10;
                $candidates[] = $padded10 . '@student.wub.edu.bd';
            } elseif (strlen($digits) === 10 && str_starts_with($digits, '0')) {
                // If 10 digits with leading zero, probe 9-digit unpadded variant
                $unpadded9 = substr($digits, 1);
                $candidates[] = $unpadded9;
                $candidates[] = $unpadded9 . '@student.wub.edu.bd';
            }
        }

        $candidates = array_values(array_unique(array_filter($candidates)));

        // Query mdl_user for any match across username, idnumber, or email
        list($insql1, $params1) = $DB->get_in_or_equal($candidates, SQL_PARAMS_NAMED, 'un');
        list($insql2, $params2) = $DB->get_in_or_equal($candidates, SQL_PARAMS_NAMED, 'id');
        list($insql3, $params3) = $DB->get_in_or_equal($candidates, SQL_PARAMS_NAMED, 'em');
        $params = array_merge($params1, $params2, $params3);

        $records = $DB->get_records_select(
            'user',
            "deleted = 0 AND (LOWER(username) $insql1 OR LOWER(REPLACE(idnumber, ' ', '')) $insql2 OR LOWER(email) $insql3)",
            $params
        );

        if (empty($records)) {
            return ['user' => null, 'ambiguous' => false];
        }

        if (count($records) === 1) {
            return ['user' => reset($records), 'ambiguous' => false];
        }

        // Multiple distinct accounts matched.
        // Check if exactly one is an exact match on username or idnumber
        $exactMatches = [];
        foreach ($records as $u) {
            if (strtolower($u->username) === $lower || strtolower(str_replace(' ', '', (string)$u->idnumber)) === $cleanReg || strtolower($u->email) === $lower) {
                $exactMatches[$u->id] = $u;
            }
        }

        if (count($exactMatches) === 1) {
            return ['user' => reset($exactMatches), 'ambiguous' => false];
        }

        // Ambiguous match across multiple distinct accounts
        return ['user' => null, 'ambiguous' => true];
    }

    /**
     * Check if user is authoritative Head of Department (HOD).
     *
     * Resolves HOD status from structured Moodle role assignments:
     * User has 'manager', 'coursecreator', or 'hod' role assigned in a Course Category context (contextlevel 40).
     *
     * Does NOT rely on profile description text or scratch JSON.
     *
     * @param stdClass $user
     * @return bool
     */
    public function is_user_hod(stdClass $user): bool {
        global $DB;

        return $DB->record_exists_sql("
            SELECT ra.id
            FROM {role_assignments} ra
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} ctx ON ctx.id = ra.contextid
            WHERE ra.userid = :userid
              AND ctx.contextlevel = 40
              AND r.shortname IN ('manager', 'coursecreator', 'hod')
        ", ['userid' => $user->id]);
    }

    /**
     * Record audit log event.
     */
    protected function log_audit(string $action, string $actor, string $clientIp, string $status, array $details = []): void {
        global $DB;
        try {
            $rec = new stdClass();
            $rec->timecreated = time();
            $rec->action = $action;
            $rec->actor_identifier = substr($actor, 0, 128);
            $rec->client_ip = substr($clientIp, 0, 45);
            $rec->status = $status;
            $rec->details = json_encode($details);
            $DB->insert_record('examcontroller_audit', $rec);
        } catch (\Throwable $e) {
            // Do not break execution on audit logging failure
        }
    }
}
