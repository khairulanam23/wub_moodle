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
 * Academic roster provider for native Moodle Cohorts.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohort_roster_provider implements roster_provider_interface {

    public function is_available(): bool {
        return true;
    }

    public function get_source_id(): string {
        return 'cohort';
    }

    public function get_source_label(): string {
        return get_string('source_cohort', 'local_bulk_enrolment');
    }

    public function get_groups(): array {
        global $DB;

        $cohorts = $DB->get_records('cohort', ['visible' => 1], 'name ASC', 'id, name, idnumber');
        $result = [];
        foreach ($cohorts as $c) {
            $name = format_string($c->name);
            if (!empty($c->idnumber)) {
                $name .= ' [' . $c->idnumber . ']';
            }
            $result[] = (object)[
                'id' => (string)$c->id,
                'name' => $name,
            ];
        }
        return $result;
    }

    public function get_subgroups(string $groupid): array {
        // Moodle cohorts do not have hierarchical sub-batches.
        return [];
    }

    public function get_students(string $groupid, string $subgroupid = ''): array {
        global $DB;

        $cohortid = (int)$groupid;
        if ($cohortid <= 0) {
            return [];
        }

        $sql = "SELECT u.id, u.username, u.firstname, u.lastname, u.email, u.idnumber, u.department, u.institution
                  FROM {user} u
                  JOIN {cohort_members} cm ON cm.userid = u.id
                 WHERE cm.cohortid = :cohortid AND u.deleted = 0 AND u.suspended = 0
              ORDER BY u.lastname ASC, u.firstname ASC";

        $users = $DB->get_records_sql($sql, ['cohortid' => $cohortid]);
        $students = [];
        foreach ($users as $u) {
            $students[] = (object)[
                'id' => (int)$u->id,
                'username' => $u->username,
                'fullname' => fullname($u),
                'email' => $u->email,
                'idnumber' => (string)$u->idnumber,
                'department' => (string)$u->department,
                'institution' => (string)$u->institution,
            ];
        }
        return $students;
    }
}
