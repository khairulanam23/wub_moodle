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

use stdClass;

/**
 * Service to resolve and inspect native manual enrolment instances in courses.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instance_resolver {

    /**
     * Locate the manual enrolment instance for a given course.
     *
     * @param int $courseid
     * @return stdClass|null The enrol record or null if not found.
     */
    public function get_manual_instance(int $courseid): ?stdClass {
        global $DB;

        $instances = $DB->get_records('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], 'sortorder ASC', '*', 0, 1);
        if (empty($instances)) {
            return null;
        }

        return reset($instances);
    }

    /**
     * Check if the manual enrolment instance is active in the course.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function is_instance_active(stdClass $instance): bool {
        return ((int)$instance->status === ENROL_INSTANCE_ENABLED);
    }
}
