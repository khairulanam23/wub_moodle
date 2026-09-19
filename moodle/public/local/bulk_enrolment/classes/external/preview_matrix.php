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
 * External function: pre-enrolment verification matrix.
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
use local_bulk_enrolment\service\preview_matrix_service;

/**
 * Verification matrix for UMS identities x Moodle courses.
 */
class preview_matrix extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(new external_value(PARAM_INT, 'Moodle course id')),
            'students' => new external_multiple_structure(new external_single_structure([
                'username' => new external_value(PARAM_RAW, 'UMS username'),
                'reg_id' => new external_value(PARAM_RAW, 'Registration id', VALUE_DEFAULT, ''),
                'full_name' => new external_value(PARAM_TEXT, 'Full name', VALUE_DEFAULT, ''),
            ])),
            'roleid' => new external_value(PARAM_INT, 'Role id (0 = student role)', VALUE_DEFAULT, 0),
            'reactivatesuspended' => new external_value(PARAM_BOOL, 'Treat suspended enrolments as ready to reactivate', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Execute.
     *
     * @param array $courseids
     * @param array $students
     * @param int $roleid
     * @param bool $reactivatesuspended
     * @return array
     */
    public static function execute(array $courseids, array $students, int $roleid = 0, bool $reactivatesuspended = false): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseids' => $courseids, 'students' => $students, 'roleid' => $roleid, 'reactivatesuspended' => $reactivatesuspended,
        ]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/bulk_enrolment:view', $context);
        return (new preview_matrix_service())->compute($params['courseids'], $params['students'], $params['roleid'], $params['reactivatesuspended']);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'summary' => new external_single_structure([
                'total' => new external_value(PARAM_INT, 'Total rows'),
                'ready' => new external_value(PARAM_INT, 'Ready to enrol'),
                'already_enrolled' => new external_value(PARAM_INT, 'Already enrolled'),
                'issues' => new external_value(PARAM_INT, 'Blocked rows'),
            ]),
            'roleid' => new external_value(PARAM_INT, 'Role that will be assigned'),
            'rows' => new external_multiple_structure(new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'Course id'),
                'coursename' => new external_value(PARAM_TEXT, 'Course full name'),
                'courseshortname' => new external_value(PARAM_TEXT, 'Course short name'),
                'key' => new external_value(PARAM_RAW, 'Selection key'),
                'username' => new external_value(PARAM_RAW, 'UMS username'),
                'reg_id' => new external_value(PARAM_RAW, 'Registration id'),
                'userid' => new external_value(PARAM_INT, 'Moodle user id (0 if unresolved)'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                'idnumber' => new external_value(PARAM_RAW, 'Moodle idnumber'),
                'status' => new external_value(PARAM_ALPHAEXT, 'Status code'),
                'status_label' => new external_value(PARAM_TEXT, 'Status label'),
                'badge_class' => new external_value(PARAM_ALPHAEXT, 'Badge class'),
                'is_enrollable' => new external_value(PARAM_BOOL, 'Will be enrolled on execution'),
                'message' => new external_value(PARAM_TEXT, 'Explanation'),
            ])),
        ]);
    }
}
