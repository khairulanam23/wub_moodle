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

use moodle_exception;

/**
 * Service to dynamically resolve assignable roles without hardcoding role IDs.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class role_resolver {

    /**
     * Resolve the default student role in Moodle.
     *
     * Queries by student archetype, falling back to shortname 'student'.
     *
     * @return int Role ID
     * @throws moodle_exception
     */
    /**
     * Alias for get_default_student_role_id.
     *
     * @return int
     */
    public function resolve_student_role(): int {
        return $this->get_default_student_role_id();
    }

    public function get_default_student_role_id(): int {
        global $DB;

        // 1. Try archetype = 'student'.
        $role = $DB->get_record('role', ['archetype' => 'student'], 'id', IGNORE_MULTIPLE);
        if ($role) {
            return (int)$role->id;
        }

        // 2. Try shortname = 'student'.
        $role = $DB->get_record('role', ['shortname' => 'student'], 'id');
        if ($role) {
            return (int)$role->id;
        }

        // 3. Fallback to default student role from core config if defined.
        $defrole = get_config('moodle', 'defaultuserroleid');
        if (!empty($defrole)) {
            return (int)$defrole;
        }

        throw new moodle_exception('cannotfindstudentrole', 'error');
    }

    /**
     * Validate whether a role ID exists and is assignable.
     *
     * @param int $roleid
     * @return bool
     */
    public function role_exists(int $roleid): bool {
        global $DB;
        if ($roleid <= 0) {
            return false;
        }
        return $DB->record_exists('role', ['id' => $roleid]);
    }
}
