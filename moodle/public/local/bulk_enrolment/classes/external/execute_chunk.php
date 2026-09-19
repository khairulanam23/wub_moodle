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
 * External function: execute one bounded enrolment chunk.
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
use local_bulk_enrolment\service\enrolment_dispatcher;

/**
 * Chunk execution.
 */
class execute_chunk extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Target Moodle course id'),
            'userids' => new external_multiple_structure(new external_value(PARAM_INT, 'Moodle user id'), 'At most ' . enrolment_dispatcher::MAX_CHUNK . ' users'),
            'roleid' => new external_value(PARAM_INT, 'Role id (0 = student role)', VALUE_DEFAULT, 0),
            'timestart' => new external_value(PARAM_INT, 'Enrolment start timestamp (0 = now)', VALUE_DEFAULT, 0),
            'timeend' => new external_value(PARAM_INT, 'Enrolment end timestamp (0 = unlimited)', VALUE_DEFAULT, 0),
            'reactivatesuspended' => new external_value(PARAM_BOOL, 'Reactivate suspended enrolments', VALUE_DEFAULT, false),
            'groupname' => new external_value(PARAM_TEXT, 'Course group to add the students to (empty = none)', VALUE_DEFAULT, ''),
            'allowcreategroup' => new external_value(PARAM_BOOL, 'Create the group when it does not exist (requires moodle/course:managegroups)', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $courseid
     * @param array $userids
     * @param int $roleid
     * @param int $timestart
     * @param int $timeend
     * @param bool $reactivatesuspended
     * @param string $groupname
     * @param bool $allowcreategroup
     * @return array
     */
    public static function execute(int $courseid, array $userids, int $roleid = 0, int $timestart = 0, int $timeend = 0,
            bool $reactivatesuspended = false, string $groupname = '', bool $allowcreategroup = false): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid, 'userids' => $userids, 'roleid' => $roleid, 'timestart' => $timestart, 'timeend' => $timeend,
            'reactivatesuspended' => $reactivatesuspended, 'groupname' => $groupname, 'allowcreategroup' => $allowcreategroup,
        ]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/bulk_enrolment:view', $context);
        require_sesskey();
        return (new enrolment_dispatcher())->enrol_chunk($params['courseid'], $params['userids'], $params['roleid'], $params['timestart'],
            $params['timeend'], $params['reactivatesuspended'], ['groupname' => $params['groupname'], 'allowcreate' => $params['allowcreategroup']]);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'total' => new external_value(PARAM_INT, 'Users in chunk'),
            'enrolled' => new external_value(PARAM_INT, 'Newly enrolled'),
            'already_enrolled' => new external_value(PARAM_INT, 'Already enrolled (no change)'),
            'reactivated' => new external_value(PARAM_INT, 'Reactivated'),
            'failed' => new external_value(PARAM_INT, 'Failed'),
            'grouped' => new external_value(PARAM_INT, 'Added to the group'),
            'groupid' => new external_value(PARAM_INT, 'Group id used (0 = none)'),
            'group_error' => new external_value(PARAM_TEXT, 'Group resolution error, if any'),
            'results' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'User id'),
                'status' => new external_value(PARAM_ALPHAEXT, 'ENROLLED | ALREADY_ENROLLED | REACTIVATED | ENROLMENT_SUSPENDED | IDENTITY_NOT_FOUND'),
                'message' => new external_value(PARAM_TEXT, 'Explanation'),
                'group' => new external_value(PARAM_TEXT, 'Group outcome'),
            ])),
        ]);
    }
}
