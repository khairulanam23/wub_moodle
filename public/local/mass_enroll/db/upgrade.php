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
 * Upgrade script for local_mass_enroll.
 *
 * @package    local_mass_enroll
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Function to upgrade local_mass_enroll.
 *
 * @param int $oldversion the version we are upgrading from.
 * @return bool result
 */
function xmldb_local_mass_enroll_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026081401) {
        $table = new xmldb_table('enrol_ums_user');

        if ($dbman->table_exists($table)) {
            // Alter batch_id to char(255).
            $field = new xmldb_field('batch_id', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'user_id');
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_type($table, $field);
            }

            // Alter program_id to char(255).
            $field = new xmldb_field('program_id', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'batch_id');
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_type($table, $field);
            }

            // Alter department_id to char(255).
            $field = new xmldb_field('department_id', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'program_id');
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_type($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026081401, 'local', 'mass_enroll');
    }

    return true;
}
