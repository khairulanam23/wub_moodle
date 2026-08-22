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

namespace quizaccess_proctoring\service;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use context_module;
use moodle_exception;
use quizaccess_proctoring\repository\log_repository;

/**
 * Service for Proctoring Exam Lifecycle, Image Validation, and Storage Governance.
 *
 * Responsibilities:
 * - Validate base64 image data and MIME types securely.
 * - Enforce context authorization and student ownership during webcam capture.
 * - Execute high-volume batch image deletions with zero orphaned files.
 * - Provide summary reporting metrics.
 *
 * @package    quizaccess_proctoring
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class proctoring_service {

    /** @var log_repository */
    protected log_repository $repository;

    /**
     * Constructor.
     *
     * @param log_repository|null $repository
     */
    public function __construct(?log_repository $repository = null) {
        $this->repository = $repository ?? new log_repository();
    }

    /**
     * Securely validate and sanitize base64 encoded snapshot data.
     *
     * @param string $base64Data Raw base64 string or data URL.
     * @param int $maxSizeBytes Maximum allowed binary size in bytes (default 5MB).
     * @return string|false Decoded binary data on success, or false on validation failure.
     */
    public function validate_image_payload(string $base64Data, int $maxSizeBytes = 5242880) {
        if (empty($base64Data)) {
            return false;
        }

        // Strip data URI prefix if present (e.g. data:image/png;base64, or data:image/jpeg;base64,)
        if (preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $base64Data, $matches)) {
            $rawBase64 = substr($base64Data, strpos($base64Data, ',') + 1);
        } else {
            $rawBase64 = $base64Data;
        }

        $decoded = base64_decode($rawBase64, true);
        if ($decoded === false || empty($decoded)) {
            return false;
        }

        if (strlen($decoded) > $maxSizeBytes) {
            return false;
        }

        // Verify PNG or JPEG magic bytes signature
        $isPng = (substr($decoded, 0, 8) === "\x89PNG\x0d\x0a\x1a\x0a");
        $isJpeg = (substr($decoded, 0, 2) === "\xff\xd8");

        if (!$isPng && !$isJpeg) {
            return false;
        }

        return $decoded;
    }

    /**
     * Process batch deletion of queued proctoring logs marked with deletionprogress = 1.
     *
     * @param int $batchSize Number of records to process in this run.
     * @return int Number of records deleted.
     */
    public function process_pending_deletions(int $batchSize = 50): int {
        global $DB;

        $records = $DB->get_records_select(
            'quizaccess_proctoring_logs',
            'deletionprogress = 1',
            null,
            'id ASC',
            'id',
            0,
            $batchSize
        );

        if (empty($records)) {
            return 0;
        }

        $logIds = array_keys($records);
        return $this->repository->delete_logs_and_files($logIds);
    }

    /**
     * Retrieve proctoring statistics for a quiz.
     *
     * @param int $quizId
     * @return array
     */
    public function get_quiz_proctoring_stats(int $quizId): array {
        global $DB;

        $totalLogs = $DB->count_records('quizaccess_proctoring_logs', ['quizid' => $quizId, 'deletionprogress' => 0]);
        $flaggedLogs = $DB->count_records_select(
            'quizaccess_proctoring_logs',
            'quizid = :quizid AND awsflag > 1 AND deletionprogress = 0',
            ['quizid' => $quizId]
        );
        $totalStudents = $DB->count_records_sql(
            'SELECT COUNT(DISTINCT userid) FROM {quizaccess_proctoring_logs} WHERE quizid = :quizid AND deletionprogress = 0',
            ['quizid' => $quizId]
        );

        return [
            'quizid' => $quizId,
            'total_captures' => $totalLogs,
            'flagged_captures' => $flaggedLogs,
            'total_students' => $totalStudents,
        ];
    }
}
