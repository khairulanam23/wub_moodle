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
 * External function: paginated, identity-matched UMS roster for program/batch selections (API #4 + #1).
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_bulk_enrolment\exception\ums_exception;
use local_bulk_enrolment\service\roster_service;

/**
 * Roster query.
 */
class get_roster extends external_api {
    use ums_error_trait;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'selections' => new external_multiple_structure(new external_single_structure([
                'program_id' => new external_value(PARAM_ALPHANUMEXT, 'UMS program id'),
                'batch_titles' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Batch title'), 'Batch titles; empty = whole program', VALUE_DEFAULT, []),
            ])),
            'search' => new external_value(PARAM_TEXT, 'Free-text search', VALUE_DEFAULT, ''),
            'batch' => new external_value(PARAM_TEXT, 'Filter by batch title', VALUE_DEFAULT, ''),
            'match' => new external_value(PARAM_ALPHAEXT, 'Filter by match status: MATCHED | NOT_FOUND | AMBIGUOUS', VALUE_DEFAULT, ''),
            'program' => new external_value(PARAM_ALPHANUMEXT, 'Filter by program id', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, '1-based page', VALUE_DEFAULT, 1),
            'perpage' => new external_value(PARAM_INT, 'Rows per page (5-100)', VALUE_DEFAULT, roster_service::DEFAULT_PER_PAGE),
            'refresh' => new external_value(PARAM_BOOL, 'Bypass the roster cache', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Execute.
     *
     * @param array $selections
     * @param string $search
     * @param string $batch
     * @param string $match
     * @param string $program
     * @param int $page
     * @param int $perpage
     * @param bool $refresh
     * @return array
     */
    public static function execute(array $selections, string $search = '', string $batch = '', string $match = '', string $program = '',
            int $page = 1, int $perpage = roster_service::DEFAULT_PER_PAGE, bool $refresh = false): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'selections' => $selections, 'search' => $search, 'batch' => $batch, 'match' => $match, 'program' => $program,
            'page' => $page, 'perpage' => $perpage, 'refresh' => $refresh,
        ]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/bulk_enrolment:view', $context);
        $emptyresult = ['total' => 0, 'total_unfiltered' => 0, 'page' => 1, 'pages' => 1, 'perpage' => $params['perpage'], 'rows' => [],
            'selectable_keys' => [], 'facets' => ['batches' => [], 'matched' => 0, 'not_found' => 0, 'ambiguous' => 0], 'sources' => [], 'warnings' => []];
        if (empty($params['selections'])) {
            return ['ok' => true, 'error_code' => '', 'error_message' => ''] + $emptyresult;
        }
        try {
            $result = (new roster_service())->query($params['selections'],
                ['search' => $params['search'], 'batch' => $params['batch'], 'match' => $params['match'], 'program' => $params['program']],
                $params['page'], $params['perpage'], $params['refresh']);
            return ['ok' => true, 'error_code' => '', 'error_message' => ''] + $result;
        } catch (ums_exception $e) {
            return self::failure($e) + $emptyresult;
        }
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(self::envelope_fields() + [
            'total' => new external_value(PARAM_INT, 'Rows after filtering'),
            'total_unfiltered' => new external_value(PARAM_INT, 'Rows in the loaded roster'),
            'page' => new external_value(PARAM_INT, 'Current page'),
            'pages' => new external_value(PARAM_INT, 'Page count'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page'),
            'rows' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_RAW, 'Selection key (UMS username)'),
                'username' => new external_value(PARAM_RAW, 'UMS username (10-digit student number)'),
                'reg_id' => new external_value(PARAM_RAW, 'Registration id'),
                'full_name' => new external_value(PARAM_TEXT, 'Full name'),
                'program_id' => new external_value(PARAM_RAW, 'Program id'),
                'program_name' => new external_value(PARAM_TEXT, 'Program name'),
                'program_short' => new external_value(PARAM_TEXT, 'Program short title'),
                'batch' => new external_value(PARAM_TEXT, 'Effective batch title'),
                'mother_batch' => new external_value(PARAM_TEXT, 'Mother batch title'),
                'current_batch' => new external_value(PARAM_TEXT, 'Current batch title'),
                'university_email' => new external_value(PARAM_RAW, 'University e-mail from UMS (visible page only)'),
                'shift' => new external_value(PARAM_TEXT, 'Shift (visible page only)'),
                'courses' => new external_multiple_structure(new external_single_structure([
                    'course_code' => new external_value(PARAM_TEXT, 'Course code'),
                    'title' => new external_value(PARAM_TEXT, 'Course title'),
                    'credit' => new external_value(PARAM_TEXT, 'Credit'),
                    'semester' => new external_value(PARAM_TEXT, 'Semester (when supplied)'),
                ])),
                'course_count' => new external_value(PARAM_INT, 'Number of registered courses'),
                'course_codes' => new external_value(PARAM_TEXT, 'Course codes summary'),
                'match_status' => new external_value(PARAM_ALPHAEXT, 'MATCHED | NOT_FOUND | AMBIGUOUS'),
                'matched_field' => new external_value(PARAM_ALPHA, 'Field that matched'),
                'candidates' => new external_value(PARAM_INT, 'Number of candidate Moodle accounts'),
                'moodle_user_id' => new external_value(PARAM_INT, 'Moodle user id (0 if none)'),
                'moodle_username' => new external_value(PARAM_RAW, 'Moodle username'),
                'moodle_fullname' => new external_value(PARAM_TEXT, 'Moodle full name'),
                'moodle_email' => new external_value(PARAM_RAW, 'Moodle e-mail'),
                'moodle_idnumber' => new external_value(PARAM_RAW, 'Moodle idnumber'),
                'suspended' => new external_value(PARAM_BOOL, 'Moodle account suspended'),
                'selectable' => new external_value(PARAM_BOOL, 'Can be selected for enrolment'),
            ])),
            'selectable_keys' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_RAW, 'Selection key'),
                'reg_id' => new external_value(PARAM_RAW, 'Registration id'),
                'full_name' => new external_value(PARAM_TEXT, 'Full name'),
                'batch' => new external_value(PARAM_TEXT, 'Batch title'),
            ]), 'All selectable students matching the filter (for select-all)'),
            'facets' => new external_single_structure([
                'batches' => new external_multiple_structure(new external_single_structure([
                    'title' => new external_value(PARAM_TEXT, 'Batch title'),
                    'count' => new external_value(PARAM_INT, 'Students'),
                ])),
                'matched' => new external_value(PARAM_INT, 'Matched count'),
                'not_found' => new external_value(PARAM_INT, 'Not found count'),
                'ambiguous' => new external_value(PARAM_INT, 'Ambiguous count'),
            ]),
            'sources' => new external_multiple_structure(new external_single_structure([
                'program_id' => new external_value(PARAM_RAW, 'Program id'),
                'batch_title' => new external_value(PARAM_TEXT, 'Batch title or empty for whole program'),
                'count' => new external_value(PARAM_INT, 'Students returned'),
                'status' => new external_value(PARAM_ALPHA, 'OK | EMPTY'),
            ])),
            'warnings' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Warning')),
        ]);
    }
}
