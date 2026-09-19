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
 * REST API entry point for ExamController Production Integration.
 *
 * @package    local_examcontroller
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('NO_UPGRADE_CHECK', true);

require_once(__DIR__ . '/../../../../config.php');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawBody = file_get_contents('php://input');
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Extract headers
$headers = getallheaders();
if (!$headers) {
    $headers = [];
    foreach ($_SERVER as $k => $v) {
        if (str_starts_with($k, 'HTTP_')) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
            $headers[$name] = $v;
        }
    }
}

// Endpoint routing
$endpoint = $_GET['endpoint'] ?? '';
if (empty($endpoint)) {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $prefix = '/local/examcontroller/api/v1/';
    if (str_contains($uri, $prefix)) {
        $endpoint = trim(substr($uri, strpos($uri, $prefix) + strlen($prefix)), '/');
    }
}

// Path used for HMAC canonical signature
$canonicalPath = '/local/examcontroller/api/v1/index.php';

// Normalize query parameters deterministically for HMAC canonical string
$queryParams = $_GET;
if (!empty($endpoint) && !isset($queryParams['endpoint'])) {
    $queryParams['endpoint'] = $endpoint;
}
$canonicalQuery = \local_examcontroller\service\signature_service::normalize_query($queryParams);

// Allow unauthenticated health check
if ($endpoint === 'health') {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'status' => 'healthy',
        'service' => 'wub_moodle_examcontroller_api',
        'server_time' => date('c'),
        'timestamp' => time(),
    ], JSON_UNESCAPED_SLASHES);
    exit(0);
}

// 1. Verify cryptographic HMAC signature
$verify = \local_examcontroller\service\signature_service::verify_request($method, $canonicalPath, $rawBody, $headers, $canonicalQuery);
if (!$verify['valid']) {
    http_response_code($verify['code'] ?? 401);
    echo json_encode([
        'success' => false,
        'error_code' => 'UNAUTHORIZED',
        'message' => $verify['error'],
        'server_time' => date('c'),
    ], JSON_UNESCAPED_SLASHES);
    exit(0);
}

// 2. Dispatch routes
$identityService = new \local_examcontroller\service\identity_service();

try {
    if ($method === 'POST' && ($endpoint === 'identity/verify' || $endpoint === 'auth/verify')) {
        $data = json_decode($rawBody, true) ?: [];
        $identifier = $data['identifier'] ?? $data['login'] ?? '';
        $password = $data['password'] ?? '';
        $expectedRole = $data['role'] ?? $data['expectedRole'] ?? null;

        $res = $identityService->verify_credentials($identifier, $password, $expectedRole, $clientIp);
        http_response_code($res['http_status'] ?? 200);
        unset($res['http_status']);
        echo json_encode($res, JSON_UNESCAPED_SLASHES);
        exit(0);
    }

    if ($method === 'GET' && $endpoint === 'teachers') {
        $dept = $_GET['department'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 50)));

        $res = $identityService->get_teachers($dept, $page, $perPage);
        http_response_code(200);
        unset($res['http_status']);
        echo json_encode($res, JSON_UNESCAPED_SLASHES);
        exit(0);
    }

    $academicService = new \local_examcontroller\service\academic_service();

    if ($method === 'GET' && ($endpoint === 'academic/overview' || $endpoint === 'overview')) {
        $res = $academicService->get_overview();
        http_response_code(200);
        unset($res['http_status']);
        echo json_encode($res, JSON_UNESCAPED_SLASHES);
        exit(0);
    }

    if ($method === 'GET' && ($endpoint === 'academic/courses' || $endpoint === 'courses')) {
        $semester = $_GET['semester'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(200, max(1, (int)($_GET['per_page'] ?? 50)));

        $res = $academicService->get_courses($semester, $page, $perPage);
        http_response_code(200);
        unset($res['http_status']);
        echo json_encode($res, JSON_UNESCAPED_SLASHES);
        exit(0);
    }

    if ($method === 'GET' && ($endpoint === 'academic/sections' || $endpoint === 'sections')) {
        $semester = $_GET['semester'] ?? null;
        $courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(200, max(1, (int)($_GET['per_page'] ?? 50)));

        $res = $academicService->get_sections($semester, $courseId, $page, $perPage);
        http_response_code(200);
        unset($res['http_status']);
        echo json_encode($res, JSON_UNESCAPED_SLASHES);
        exit(0);
    }

    if ($method === 'GET' && ($endpoint === 'academic/teacher-assignments' || $endpoint === 'teacher-assignments' || $endpoint === 'teacher/assignments' || $endpoint === 'teachers/assignments')) {
        $semester = $_GET['semester'] ?? null;
        $teacherId = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(200, max(1, (int)($_GET['per_page'] ?? 100)));

        $res = $academicService->get_teacher_assignments($semester, $teacherId, $page, $perPage);
        http_response_code(200);
        unset($res['http_status']);
        echo json_encode($res, JSON_UNESCAPED_SLASHES);
        exit(0);
    }

    if ($method === 'GET' && ($endpoint === 'academic/student-enrolments' || $endpoint === 'student-enrolments' || $endpoint === 'student/enrolments' || $endpoint === 'students/enrolments')) {
        $semester = $_GET['semester'] ?? null;
        $courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
        $groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(500, max(1, (int)($_GET['per_page'] ?? 200)));

        $res = $academicService->get_student_enrolments($semester, $courseId, $groupId, $page, $perPage);
        http_response_code(200);
        unset($res['http_status']);
        echo json_encode($res, JSON_UNESCAPED_SLASHES);
        exit(0);
    }

    // Unmatched endpoint
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error_code' => 'ENDPOINT_NOT_FOUND',
        'message' => "The requested endpoint '{$endpoint}' was not found.",
    ], JSON_UNESCAPED_SLASHES);
    exit(0);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error_code' => 'INTERNAL_SERVER_ERROR',
        'message' => 'An unexpected server error occurred: ' . $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES);
    exit(0);
}
