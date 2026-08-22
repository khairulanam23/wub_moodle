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

namespace quizaccess_proctoring\repository;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use moodle_recordset;

/**
 * Repository for Proctoring Log and File storage operations.
 *
 * Encapsulates all direct database queries for {quizaccess_proctoring_logs},
 * {quizaccess_proctoring_face_images}, and {quizaccess_proctoring_fm_warnings}.
 *
 * @package    quizaccess_proctoring
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log_repository {

    /**
     * Retrieve a proctoring log record by ID.
     *
     * @param int $logId
     * @return stdClass|null
     */
    public function get_log(int $logId): ?stdClass {
        global $DB;
        $record = $DB->get_record('quizaccess_proctoring_logs', ['id' => $logId]);
        return $record ?: null;
    }

    /**
     * Retrieve multiple log records by array of IDs.
     *
     * @param array $logIds
     * @return array
     */
    public function get_logs_by_ids(array $logIds): array {
        global $DB;
        if (empty($logIds)) {
            return [];
        }
        return $DB->get_records_list('quizaccess_proctoring_logs', 'id', $logIds);
    }

    /**
     * Retrieve recordset of logs for a course.
     *
     * @param int $courseId
     * @return moodle_recordset
     */
    public function get_logs_by_course(int $courseId): moodle_recordset {
        global $DB;
        return $DB->get_recordset('quizaccess_proctoring_logs', ['courseid' => $courseId]);
    }

    /**
     * Retrieve recordset of logs for a quiz (module ID).
     *
     * @param int $quizId
     * @return moodle_recordset
     */
    public function get_logs_by_quiz(int $quizId): moodle_recordset {
        global $DB;
        return $DB->get_recordset('quizaccess_proctoring_logs', ['quizid' => $quizId]);
    }

    /**
     * Retrieve recordset of logs for a specific student in a quiz.
     *
     * @param int $courseId
     * @param int $cmId
     * @param int $studentId
     * @return moodle_recordset
     */
    public function get_student_quiz_logs(int $courseId, int $cmId, int $studentId): moodle_recordset {
        global $DB;

        $sql = "SELECT e.id as reportid, e.userid as studentid, e.webcampicture as webcampicture,
                       e.status as status, e.awsscore as awsscore, e.awsflag as awsflag,
                       e.timemodified as timemodified,
                       u.firstname as firstname, u.lastname as lastname, u.email as email
                FROM {quizaccess_proctoring_logs} e
                JOIN {user} u ON u.id = e.userid
                WHERE e.courseid = :courseid
                  AND e.quizid = :cmid
                  AND e.userid = :studentid
                  AND e.deletionprogress = 0";

        $params = [
            'courseid' => $courseId,
            'cmid' => $cmId,
            'studentid' => $studentId,
        ];

        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * Insert a new proctoring log record.
     *
     * @param stdClass $logData
     * @return int Inserted ID
     */
    public function insert_log(stdClass $logData): int {
        global $DB;
        return (int)$DB->insert_record('quizaccess_proctoring_logs', $logData, true);
    }

    /**
     * Insert a cropped face image record.
     *
     * @param stdClass $faceData
     * @return int Inserted ID
     */
    public function insert_face_image(stdClass $faceData): int {
        global $DB;
        return (int)$DB->insert_record('quizaccess_proctoring_face_images', $faceData, true);
    }

    /**
     * Update deletion progress flag scoped to a specific quiz.
     *
     * @param int $quizId Course module ID
     * @param int $flag
     * @return bool
     */
    public function set_deletion_progress_by_quiz(int $quizId, int $flag = 1): bool {
        global $DB;
        return $DB->set_field('quizaccess_proctoring_logs', 'deletionprogress', $flag, ['quizid' => $quizId]);
    }

    /**
     * Update deletion progress flag scoped to a specific course.
     *
     * @param int $courseId
     * @param int $flag
     * @return bool
     */
    public function set_deletion_progress_by_course(int $courseId, int $flag = 1): bool {
        global $DB;
        return $DB->set_field('quizaccess_proctoring_logs', 'deletionprogress', $flag, ['courseid' => $courseId]);
    }

    /**
     * Update deletion progress globally across all logs (Admin only).
     *
     * @param int $flag
     * @return bool
     */
    public function set_deletion_progress_global(int $flag = 1): bool {
        global $DB;
        return $DB->set_field('quizaccess_proctoring_logs', 'deletionprogress', $flag);
    }

    /**
     * Delete proctoring logs, warnings, and physical file records atomically.
     *
     * @param array $logIds
     * @return int Number of deleted logs
     */
    public function delete_logs_and_files(array $logIds): int {
        global $DB;
        if (empty($logIds)) {
            return 0;
        }

        $transaction = $DB->start_delegated_transaction();
        $deletedCount = 0;

        $logs = $this->get_logs_by_ids($logIds);
        foreach ($logs as $row) {
            $id = (int)$row->id;
            $fileurl = (string)$row->webcampicture;
            $patharray = explode("/", $fileurl);
            $filename = end($patharray);

            // Delete associated warnings
            $DB->delete_records('quizaccess_proctoring_fm_warnings', ['reportid' => $id]);
            // Delete associated face images if table exists
            if ($DB->get_manager()->table_exists('quizaccess_proctoring_face_images')) {
                $DB->delete_records('quizaccess_proctoring_face_images', ['parentid' => $id]);
            }
            // Delete log entry
            $DB->delete_records('quizaccess_proctoring_logs', ['id' => $id]);

            // Delete stored file from Moodle File Storage
            if (!empty($filename)) {
                $this->delete_stored_file_by_name($filename);
            }
            $deletedCount++;
        }

        $transaction->allow_commit();
        return $deletedCount;
    }

    /**
     * Delete stored file from Moodle File Storage by filename.
     *
     * @param string $filename
     * @param string $filearea
     * @param string $component
     * @return void
     */
    public function delete_stored_file_by_name(string $filename, string $filearea = 'picture', string $component = 'quizaccess_proctoring'): void {
        global $DB;
        $select = "component = :component AND filearea = :filearea AND filename = :filename";
        $params = [
            'component' => $component,
            'filearea' => $filearea,
            'filename' => $filename,
        ];
        $usersfiles = $DB->get_records_select('files', $select, $params);
        $fs = get_file_storage();

        foreach ($usersfiles as $filerow) {
            $file = $fs->get_file(
                $filerow->contextid,
                $filerow->component,
                $filerow->filearea,
                $filerow->itemid,
                $filerow->filepath,
                $filerow->filename
            );
            if ($file) {
                $file->delete();
            }
        }
    }
}
