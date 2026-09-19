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
 * External function: UMS batches for one or more programs (API #3).
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
 * Returns real UMS batches (id + title) for the selected programs.
 */
class get_batches extends external_api {
    use ums_error_trait;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programids' => new external_multiple_structure(new external_value(PARAM_ALPHANUMEXT, 'UMS program id')),
            'refresh' => new external_value(PARAM_BOOL, 'Bypass the batches cache', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Execute.
     *
     * @param array $programids
     * @param bool $refresh
     * @return array
     */
    public static function execute(array $programids, bool $refresh = false): array {
        $params = self::validate_parameters(self::execute_parameters(), ['programids' => $programids, 'refresh' => $refresh]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/bulk_enrolment:view', $context);
        try {
            $byprogram = (new roster_service())->list_batches($params['programids'], $params['refresh']);
            $flat = [];
            foreach ($byprogram as $pid => $batches) {
                foreach ($batches as $b) {
                    $b['program_id'] = (string)$pid;
                    $flat[] = $b;
                }
            }
            return ['ok' => true, 'error_code' => '', 'error_message' => '', 'batches' => $flat];
        } catch (ums_exception $e) {
            return self::failure($e) + ['batches' => []];
        }
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(self::envelope_fields() + [
            'batches' => new external_multiple_structure(new external_single_structure([
                'program_id' => new external_value(PARAM_RAW, 'UMS program id'),
                'id' => new external_value(PARAM_RAW, 'UMS batch database id (not accepted by the roster API)'),
                'title' => new external_value(PARAM_TEXT, 'Batch title (identifier used by the roster API)'),
                'shift' => new external_value(PARAM_TEXT, 'Shift'),
                'program_type' => new external_value(PARAM_TEXT, 'Program type'),
                'total_students' => new external_value(PARAM_INT, 'Students reported by UMS'),
                'is_active' => new external_value(PARAM_BOOL, 'Active flag'),
                'is_open' => new external_value(PARAM_BOOL, 'Open flag'),
            ])),
        ]);
    }
}
