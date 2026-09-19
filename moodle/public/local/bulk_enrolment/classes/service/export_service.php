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

namespace local_bulk_enrolment\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Service for generating downloadable audit and enrolment reports.
 *
 * Replaces the legacy bundled PhpSpreadsheet (822 files) with Moodle's native
 * dataformat and streaming export facilities.
 *
 * CRITICAL ARCHITECTURAL CONFORMANCE:
 * - Zero third-party library dependencies.
 * - Streams CSV/Excel exports cleanly.
 * - Never includes secret credentials in generated files.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_service {

    /**
     * Generate and stream a CSV file download to the browser.
     *
     * @param string $filename Base filename (without extension)
     * @param array $headers Column headers
     * @param array $rows Array of data rows (each an array of values)
     * @return void Exits script after streaming
     */
    public function export_csv(string $filename, array $headers, array $rows): void {
        // Clean filename.
        $cleanName = clean_filename($filename . '_' . date('Ymd_His') . '.csv');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $cleanName . '"');
        header('Cache-Control: max-age=0, no-cache, must-revalidate');
        header('Pragma: public');

        $out = fopen('php://output', 'w');

        // Output UTF-8 BOM for Excel compatibility.
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Write header row.
        $cleanHeaders = array_map([self::class, 'sanitize_csv_field'], $headers);
        fputcsv($out, $cleanHeaders);

        // Write data rows.
        $sl = 1;
        foreach ($rows as $row) {
            $formatted = array_merge([$sl++], array_values((array)$row));
            $cleanRow = array_map([self::class, 'sanitize_csv_field'], $formatted);
            fputcsv($out, $cleanRow);
        }

        fclose($out);
        exit(0);
    }

    /**
     * Prevent CSV formula / DDE injection in spreadsheet readers.
     *
     * Prepends an apostrophe if a cell starts with =, +, -, @, tab, or carriage return.
     *
     * @param mixed $val
     * @return mixed
     */
    public static function sanitize_csv_field(mixed $val): mixed {
        if (is_string($val) && $val !== '') {
            if (in_array($val[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'" . $val;
            }
        }
        return $val;
    }

    /**
     * Format a bulk enrolment execution report into exportable rows.
     *
     * @param array $report Report array from enrolment_dispatcher or csv_enrolment_service
     * @return array ['headers' => array, 'rows' => array]
     */
    public function format_execution_report(array $report): array {
        $headers = ['SL', 'Course ID', 'Course Name', 'Enrolled', 'Already Enrolled', 'Failed', 'Groups Assigned'];
        $rows = [];

        $courses = $report['courses'] ?? [];
        foreach ($courses as $c) {
            $rows[] = [
                'courseid' => $c['courseid'] ?? '',
                'course_name' => $c['course_name'] ?? '',
                'enrolled' => $c['enrolled'] ?? 0,
                'already_enrolled' => $c['already_enrolled'] ?? 0,
                'failed' => $c['failed'] ?? 0,
                'groups_assigned' => $c['groups_assigned'] ?? 0,
            ];
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }
}
