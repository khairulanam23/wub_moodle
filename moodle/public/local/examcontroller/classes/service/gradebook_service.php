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
use grade_item;
use grade_grade;

defined('MOODLE_INTERNAL') || die();

/**
 * Authoritative Gradebook Integration Service for ExamController.
 *
 * Implements native, secure, and idempotent synchronization of finalized
 * ExamController academic marks into Moodle's native gradebook using
 * Moodle core's grade_update() and grade_item APIs.
 *
 * @package    local_examcontroller
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradebook_service {

    /**
     * Retrieve all gradable items for a specific Moodle course.
     * Excludes course totals and category totals to only show actual evaluation items.
     *
     * @param int $courseId Moodle Course ID
     * @return array Standardized API response
     */
    public function get_course_grade_items(int $courseId): array {
        global $DB, $CFG;

        require_once($CFG->libdir . '/gradelib.php');

        $course = $DB->get_record('course', ['id' => $courseId]);
        if (!$course || $course->id <= 1 || empty($course->visible)) {
            return [
                'success' => false,
                'error_code' => 'COURSE_NOT_FOUND',
                'message' => "Moodle course ID {$courseId} not found or inactive.",
                'http_status' => 404,
            ];
        }

        $sql = "
            SELECT gi.id, gi.courseid, gi.categoryid, gi.itemname, gi.itemtype, gi.itemmodule,
                   gi.iteminstance, gi.itemnumber, gi.idnumber, gi.gradetype, gi.grademax,
                   gi.grademin, gi.gradepass, gi.hidden, gi.locked,
                   gc.fullname as category_name
            FROM {grade_items} gi
            LEFT JOIN {grade_categories} gc ON gc.id = gi.categoryid
            WHERE gi.courseid = :cid
              AND gi.itemtype = 'manual'
            ORDER BY gi.sortorder ASC, gi.id ASC
        ";

        $records = $DB->get_records_sql($sql, ['cid' => $courseId]);
        $items = [];

        foreach ($records as $r) {
            $items[] = [
                'id' => (int)$r->id,
                'courseId' => (int)$r->courseid,
                'categoryId' => !empty($r->categoryid) ? (int)$r->categoryid : null,
                'categoryName' => (string)($r->category_name ?? 'General'),
                'itemName' => (string)($r->itemname ?: 'Manual Grade Item'),
                'itemType' => (string)$r->itemtype,
                'itemModule' => (string)($r->itemmodule ?? ''),
                'idNumber' => (string)($r->idnumber ?? ''),
                'gradeMax' => (float)$r->grademax,
                'gradeMin' => (float)$r->grademin,
                'gradePass' => (float)$r->gradepass,
                'hidden' => (bool)$r->hidden,
                'locked' => (bool)$r->locked,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'moodleCourseId' => $courseId,
                'courseCode' => (string)$course->shortname,
                'courseName' => (string)$course->fullname,
                'gradeItems' => $items,
            ],
            'http_status' => 200,
        ];
    }

    /**
     * Publish authoritative examination marks into Moodle native gradebook.
     *
     * @param array $payload Validated JSON payload
     * @return array Standardized API response
     */
    public function publish_grades(array $payload): array {
        global $DB, $CFG;

        require_once($CFG->libdir . '/gradelib.php');

        // 1. Validate top-level payload structure
        $courseId = isset($payload['moodleCourseId']) ? (int)$payload['moodleCourseId'] : 0;
        $examId = trim($payload['examControllerExamId'] ?? '');
        $examTitle = trim($payload['examTitle'] ?? '');
        $totalMarks = isset($payload['totalMarks']) ? (float)$payload['totalMarks'] : 0.0;
        $passingMarks = isset($payload['passingMarks']) ? (float)$payload['passingMarks'] : 0.0;
        $moodleGradeItemId = !empty($payload['moodleGradeItemId']) ? (int)$payload['moodleGradeItemId'] : null;
        $moodleCategoryId = !empty($payload['moodleCategoryId']) ? (int)$payload['moodleCategoryId'] : null;
        $grades = isset($payload['grades']) && is_array($payload['grades']) ? $payload['grades'] : null;

        if ($courseId <= 1) {
            return [
                'success' => false,
                'error_code' => 'INVALID_COURSE_ID',
                'message' => 'A valid positive Moodle course ID (> 1) is required.',
                'http_status' => 400,
            ];
        }

        if (empty($examId)) {
            return [
                'success' => false,
                'error_code' => 'MISSING_EXAM_ID',
                'message' => 'ExamController exam identifier (examControllerExamId) is required.',
                'http_status' => 400,
            ];
        }

        if ($totalMarks <= 0) {
            return [
                'success' => false,
                'error_code' => 'INVALID_TOTAL_MARKS',
                'message' => 'Total marks for examination must be strictly greater than zero.',
                'http_status' => 400,
            ];
        }

        if ($grades === null) {
            return [
                'success' => false,
                'error_code' => 'MISSING_GRADES',
                'message' => 'Grades array must be provided.',
                'http_status' => 400,
            ];
        }

        // Verify Course existence
        $course = $DB->get_record('course', ['id' => $courseId]);
        if (!$course || empty($course->visible)) {
            return [
                'success' => false,
                'error_code' => 'COURSE_NOT_FOUND',
                'message' => "Moodle course ID {$courseId} does not exist or is inactive.",
                'http_status' => 404,
            ];
        }

        // 2. Resolve or provision Moodle Grade Item (strictly manual items)
        $gradeItem = null;
        $idnumber = 'examcontroller_' . $examId;

        if ($moodleGradeItemId !== null) {
            // Explicit mapping requested
            $existing = grade_item::fetch(['id' => $moodleGradeItemId]);
            if (!$existing) {
                return [
                    'success' => false,
                    'error_code' => 'GRADE_ITEM_NOT_FOUND',
                    'message' => "Requested Moodle grade item ID {$moodleGradeItemId} does not exist.",
                    'http_status' => 404,
                ];
            }

            if ((int)$existing->courseid !== $courseId) {
                return [
                    'success' => false,
                    'error_code' => 'GRADE_ITEM_COURSE_MISMATCH',
                    'message' => "Grade item {$moodleGradeItemId} belongs to course {$existing->courseid}, not course {$courseId}.",
                    'http_status' => 400,
                ];
            }

            if ($existing->itemtype !== 'manual') {
                return [
                    'success' => false,
                    'error_code' => 'UNSUPPORTED_GRADE_ITEM_TYPE',
                    'message' => "Grade item {$moodleGradeItemId} has type '{$existing->itemtype}'. ExamController only synchronizes to dedicated manual grade items. Activity-backed items (e.g. quiz, assign) and category/course totals are prohibited.",
                    'http_status' => 422,
                ];
            }

            $gradeItem = $existing;
        } else {
            // Lookup via deterministic idnumber anchor
            $existing = grade_item::fetch(['courseid' => $courseId, 'idnumber' => $idnumber, 'itemtype' => 'manual']);
            if ($existing) {
                $gradeItem = $existing;
            } else {
                // Self-heal and provision dedicated manual grade item
                $targetCategoryId = null;
                if ($moodleCategoryId) {
                    $cat = $DB->get_record('grade_categories', ['id' => $moodleCategoryId, 'courseid' => $courseId]);
                    if ($cat) {
                        $targetCategoryId = (int)$cat->id;
                    }
                }

                $cleanTitle = !empty($examTitle) ? $examTitle : 'Exam ' . $examId;
                $newItem = new grade_item([
                    'courseid' => $courseId,
                    'categoryid' => $targetCategoryId,
                    'itemname' => $cleanTitle,
                    'itemtype' => 'manual',
                    'itemmodule' => null,
                    'iteminstance' => null,
                    'itemnumber' => 0,
                    'idnumber' => $idnumber,
                    'gradetype' => GRADE_TYPE_VALUE,
                    'grademax' => (float)$totalMarks,
                    'grademin' => 0.0,
                    'gradepass' => (float)$passingMarks,
                    'hidden' => 0,
                    'locked' => 0,
                ]);

                $inserted = $newItem->insert('local_examcontroller');
                if (!$inserted) {
                    return [
                        'success' => false,
                        'error_code' => 'GRADE_ITEM_CREATION_FAILED',
                        'message' => 'Unable to create dedicated Moodle grade item for examination.',
                        'http_status' => 500,
                    ];
                }

                $gradeItem = grade_item::fetch(['id' => $newItem->id]);
            }
        }

        if (!$gradeItem) {
            return [
                'success' => false,
                'error_code' => 'UNRESOLVED_GRADE_ITEM',
                'message' => 'Could not resolve or create valid Moodle grade item.',
                'http_status' => 500,
            ];
        }

        if ($gradeItem->is_locked()) {
            return [
                'success' => false,
                'error_code' => 'GRADE_ITEM_LOCKED',
                'message' => "Grade item '{$gradeItem->itemname}' is locked in Moodle against updates.",
                'http_status' => 422,
            ];
        }

        $moodleGradeMax = (float)$gradeItem->grademax;
        if ($moodleGradeMax <= 0) {
            return [
                'success' => false,
                'error_code' => 'INVALID_GRADE_MAX',
                'message' => "Target Moodle grade item has invalid maximum grade ({$moodleGradeMax}).",
                'http_status' => 422,
            ];
        }

        // 3. Validate student memberships & scale scores
        $results = [];
        $validGradesForUpdate = [];
        $gradesUpdated = 0;
        $gradesFailed = 0;

        foreach ($grades as $g) {
            $studentUserId = isset($g['moodleUserId']) ? (int)$g['moodleUserId'] : 0;
            $rawScore = isset($g['score']) && is_numeric($g['score']) ? (float)$g['score'] : null;
            $feedback = isset($g['feedback']) ? trim((string)$g['feedback']) : null;
            $gradedAt = !empty($g['gradedAt'])
                ? (is_numeric($g['gradedAt']) ? (int)$g['gradedAt'] : strtotime($g['gradedAt']))
                : time();

            if ($studentUserId <= 0) {
                $results[] = [
                    'moodleUserId' => $studentUserId,
                    'status' => 'FAILED',
                    'error' => 'Invalid Moodle user ID.',
                ];
                $gradesFailed++;
                continue;
            }

            if ($rawScore === null) {
                $results[] = [
                    'moodleUserId' => $studentUserId,
                    'status' => 'REJECTED',
                    'error' => 'Null or unfinalized score cannot be published.',
                ];
                $gradesFailed++;
                continue;
            }

            if ($rawScore < 0) {
                $results[] = [
                    'moodleUserId' => $studentUserId,
                    'status' => 'REJECTED',
                    'error' => "Score {$rawScore} cannot be negative.",
                ];
                $gradesFailed++;
                continue;
            }

            if ($rawScore > $totalMarks) {
                $results[] = [
                    'moodleUserId' => $studentUserId,
                    'status' => 'REJECTED',
                    'error' => "Score {$rawScore} exceeds ExamController total marks ({$totalMarks}).",
                ];
                $gradesFailed++;
                continue;
            }

            // Deterministic scaling when ExamController totalMarks differs from Moodle grademax
            if (abs($totalMarks - $moodleGradeMax) > 0.0001) {
                $moodleGrade = round(($rawScore / $totalMarks) * $moodleGradeMax, 4);
            } else {
                $moodleGrade = $rawScore;
            }

            if ($moodleGrade < 0 || $moodleGrade > $moodleGradeMax) {
                $results[] = [
                    'moodleUserId' => $studentUserId,
                    'status' => 'REJECTED',
                    'error' => "Calculated Moodle grade {$moodleGrade} is out of bounds (0.00 to {$moodleGradeMax}).",
                ];
                $gradesFailed++;
                continue;
            }

            // Verify active student enrolment in this course
            $isEnrolledStudent = $DB->record_exists_sql("
                SELECT 1
                FROM {user} u
                JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :cid
                JOIN {context} ctx ON ctx.instanceid = :cid2 AND ctx.contextlevel = 50
                JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id
                JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                WHERE u.id = :uid AND u.deleted = 0
            ", ['cid' => $courseId, 'cid2' => $courseId, 'uid' => $studentUserId]);

            if (!$isEnrolledStudent) {
                $results[] = [
                    'moodleUserId' => $studentUserId,
                    'status' => 'REJECTED',
                    'error' => "User ID {$studentUserId} is not an actively enrolled student in course {$courseId}.",
                ];
                $gradesFailed++;
                continue;
            }

            $validGradesForUpdate[$studentUserId] = [
                'userid' => $studentUserId,
                'rawgrade' => $moodleGrade,
                'originalScore' => $rawScore,
                'feedback' => $feedback,
                'feedbackformat' => FORMAT_MOODLE,
                'dategraded' => $gradedAt,
            ];
        }

        // 4. Authoritative Moodle Gradebook update using native manual grade_item API
        if (!empty($validGradesForUpdate)) {
            foreach ($validGradesForUpdate as $uid => $v) {
                $updOk = $gradeItem->update_final_grade(
                    $uid,
                    $v['rawgrade'],
                    'local_examcontroller',
                    $v['feedback'],
                    FORMAT_MOODLE,
                    null,
                    $v['dategraded']
                );

                if ($updOk) {
                    $results[] = [
                        'moodleUserId' => $uid,
                        'status' => 'UPDATED',
                        'grade' => $v['rawgrade'],
                        'originalScore' => $v['originalScore'],
                    ];
                    $gradesUpdated++;
                } else {
                    $results[] = [
                        'moodleUserId' => $uid,
                        'status' => 'FAILED',
                        'error' => 'update_final_grade refused update for student.',
                    ];
                    $gradesFailed++;
                }
            }

            // Trigger authoritative native course grade aggregation recalculation
            grade_regrade_final_grades($courseId);
        }

        return [
            'success' => true,
            'data' => [
                'moodleCourseId' => $courseId,
                'moodleGradeItemId' => (int)$gradeItem->id,
                'gradeItemName' => (string)$gradeItem->itemname,
                'gradeItemType' => (string)$gradeItem->itemtype,
                'gradeItemMax' => (float)$gradeItem->grademax,
                'idNumber' => (string)($gradeItem->idnumber ?? ''),
                'gradesTotal' => count($grades),
                'gradesUpdated' => $gradesUpdated,
                'gradesFailed' => $gradesFailed,
                'results' => $results,
                'serverTime' => date('c'),
            ],
            'http_status' => 200,
        ];
    }

    /**
     * Delete an owned manual grade item from Moodle gradebook via authoritative Moodle API.
     *
     * Invariants enforced:
     * 1. Valid course ID required and course must exist.
     * 2. Either gradeItemId or idNumber/examControllerExamId required.
     * 3. Idempotent: If the grade item does not exist or was already deleted, returns success with deleted: false.
     * 4. Course isolation: Target grade item must belong to the specified course.
     * 5. Strict type safety: Target grade item must have itemtype = 'manual'. Native Moodle activities
     *    (quiz, assign, forum, etc.) and category/course totals are strictly protected against deletion.
     * 6. Namespace safety: Target grade item must have idnumber starting with 'examcontroller_'.
     *    Foreign manual grade items are strictly protected against deletion.
     * 7. Idnumber match: If idNumber or examControllerExamId is supplied, it must match the grade item's idnumber.
     * 8. Lock protection: Locked grade items cannot be deleted.
     * 9. Moodle core API: Deletion is performed via grade_item::delete('local_examcontroller'), which
     *    safely cleans up all grades in a transaction, triggers \core\event\grade_item_deleted event,
     *    and triggers grade aggregation recalculation.
     *
     * @param array $payload Validated JSON payload
     * @return array Standardized API response
     */
    public function delete_grade_item(array $payload): array {
        global $DB, $CFG;

        require_once($CFG->libdir . '/gradelib.php');

        // 1. Extract and validate parameters
        $courseId = isset($payload['courseId']) ? (int)$payload['courseId'] : (isset($payload['moodleCourseId']) ? (int)$payload['moodleCourseId'] : (isset($payload['course_id']) ? (int)$payload['course_id'] : 0));
        $gradeItemId = !empty($payload['gradeItemId']) ? (int)$payload['gradeItemId'] : (!empty($payload['moodleGradeItemId']) ? (int)$payload['moodleGradeItemId'] : (!empty($payload['grade_item_id']) ? (int)$payload['grade_item_id'] : null));
        $idNumber = trim($payload['idNumber'] ?? $payload['idnumber'] ?? '');
        $examId = trim($payload['examControllerExamId'] ?? $payload['examId'] ?? $payload['exam_id'] ?? '');

        if ($courseId <= 1) {
            return [
                'success' => false,
                'error_code' => 'INVALID_COURSE_ID',
                'message' => 'A valid positive Moodle course ID (> 1) is required.',
                'http_status' => 400,
            ];
        }

        if (empty($idNumber) && !empty($examId)) {
            $idNumber = 'examcontroller_' . $examId;
        }

        if (empty($gradeItemId) && empty($idNumber)) {
            return [
                'success' => false,
                'error_code' => 'MISSING_IDENTIFIER',
                'message' => 'Either gradeItemId or idNumber/examControllerExamId is required.',
                'http_status' => 400,
            ];
        }

        // Verify Course existence
        $course = $DB->get_record('course', ['id' => $courseId]);
        if (!$course || empty($course->visible)) {
            return [
                'success' => false,
                'error_code' => 'COURSE_NOT_FOUND',
                'message' => "Moodle course ID {$courseId} does not exist or is inactive.",
                'http_status' => 404,
            ];
        }

        // 2. Resolve target grade item
        $gradeItem = null;

        if ($gradeItemId !== null) {
            $gradeItem = grade_item::fetch(['id' => $gradeItemId]);
            if (!$gradeItem) {
                // Idempotent: Already absent
                return [
                    'success' => true,
                    'deleted' => false,
                    'reason' => 'already_absent',
                    'message' => "Grade item ID {$gradeItemId} is already absent from Moodle.",
                    'data' => [
                        'gradeItemId' => $gradeItemId,
                        'courseId' => $courseId,
                    ],
                    'http_status' => 200,
                ];
            }
        } else {
            // Must have valid examcontroller prefix to look up by idnumber
            if (!str_starts_with($idNumber, 'examcontroller_')) {
                return [
                    'success' => false,
                    'error_code' => 'INVALID_IDNUMBER_PREFIX',
                    'message' => "Grade item idnumber must begin with 'examcontroller_'. Arbitrary grade item lookup is prohibited.",
                    'http_status' => 422,
                ];
            }

            $gradeItem = grade_item::fetch(['courseid' => $courseId, 'idnumber' => $idNumber, 'itemtype' => 'manual']);
            if (!$gradeItem) {
                // Idempotent: Already absent
                return [
                    'success' => true,
                    'deleted' => false,
                    'reason' => 'already_absent',
                    'message' => "Grade item with idnumber '{$idNumber}' is already absent from Moodle course {$courseId}.",
                    'data' => [
                        'courseId' => $courseId,
                        'idNumber' => $idNumber,
                    ],
                    'http_status' => 200,
                ];
            }
        }

        // 3. Safety Verification & Isolation Checks

        // Check 3a: Course isolation
        if ((int)$gradeItem->courseid !== $courseId) {
            return [
                'success' => false,
                'error_code' => 'GRADE_ITEM_COURSE_MISMATCH',
                'message' => "Grade item {$gradeItem->id} belongs to course {$gradeItem->courseid}, not requested course {$courseId}.",
                'http_status' => 400,
            ];
        }

        // Check 3b: Protect native Moodle activities (itemtype must be strictly 'manual')
        if ($gradeItem->itemtype !== 'manual') {
            return [
                'success' => false,
                'error_code' => 'NATIVE_ACTIVITY_PROTECTION',
                'message' => "Grade item {$gradeItem->id} has itemtype '{$gradeItem->itemtype}'. Native Moodle activities (quiz, assign, forum, etc.) and category/course totals can NEVER be deleted by ExamController.",
                'http_status' => 422,
            ];
        }

        // Check 3c: Protect foreign manual items (idnumber must start with 'examcontroller_')
        $actualIdNumber = (string)($gradeItem->idnumber ?? '');
        if (!str_starts_with($actualIdNumber, 'examcontroller_')) {
            return [
                'success' => false,
                'error_code' => 'FOREIGN_MANUAL_ITEM_PROTECTION',
                'message' => "Grade item {$gradeItem->id} idnumber '{$actualIdNumber}' does not belong to the ExamController namespace ('examcontroller_*'). Foreign manual grade items cannot be deleted.",
                'http_status' => 422,
            ];
        }

        // Check 3d: Verify matching idnumber if supplied
        if (!empty($idNumber) && $actualIdNumber !== $idNumber) {
            return [
                'success' => false,
                'error_code' => 'IDNUMBER_MISMATCH',
                'message' => "Grade item {$gradeItem->id} idnumber '{$actualIdNumber}' does not match expected idnumber '{$idNumber}'.",
                'http_status' => 422,
            ];
        }

        // Check 3e: Verify matching examId if supplied
        if (!empty($examId)) {
            $expectedExamIdNumber = 'examcontroller_' . $examId;
            if ($actualIdNumber !== $expectedExamIdNumber) {
                return [
                    'success' => false,
                    'error_code' => 'EXAM_MAPPING_MISMATCH',
                    'message' => "Grade item {$gradeItem->id} belongs to '{$actualIdNumber}', not exam '{$examId}'.",
                    'http_status' => 422,
                ];
            }
        }

        // Check 3f: Lock protection
        if ($gradeItem->is_locked()) {
            return [
                'success' => false,
                'error_code' => 'GRADE_ITEM_LOCKED',
                'message' => "Grade item '{$gradeItem->itemname}' ({$gradeItem->id}) is locked against modification in Moodle.",
                'http_status' => 422,
            ];
        }

        // 4. Execute deletion via authoritative Moodle grade_item API
        $deletedId = (int)$gradeItem->id;
        $deletedIdNumber = $actualIdNumber;
        $deletedItemName = (string)$gradeItem->itemname;

        $deleted = $gradeItem->delete('local_examcontroller');

        if (!$deleted) {
            return [
                'success' => false,
                'error_code' => 'GRADE_ITEM_DELETION_FAILED',
                'message' => "Moodle grade_item::delete() failed to delete grade item {$deletedId}.",
                'http_status' => 500,
            ];
        }

        // Trigger regrading of the course to update course and category totals
        grade_regrade_final_grades($courseId);

        return [
            'success' => true,
            'deleted' => true,
            'message' => "Grade item '{$deletedItemName}' ({$deletedId}) deleted successfully from Moodle course {$courseId}.",
            'data' => [
                'gradeItemId' => $deletedId,
                'courseId' => $courseId,
                'idNumber' => $deletedIdNumber,
                'itemName' => $deletedItemName,
            ],
            'http_status' => 200,
        ];
    }
}

