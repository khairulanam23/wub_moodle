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
 * Upgrade steps for local_wub_policy.
 *
 * @package    local_wub_policy
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute local_wub_policy upgrade steps.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_wub_policy_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026081400) {
        // Define table local_wub_policy_accept to be created.
        $table = new xmldb_table('local_wub_policy_accept');

        // Adding fields to table local_wub_policy_accept.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('deviceidentifier', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('role', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('policyversion', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '1.0.0');
        $table->add_field('timeaccepted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userip', XMLDB_TYPE_CHAR, '45', null, null, null, null);
        $table->add_field('useragent', XMLDB_TYPE_CHAR, '255', null, null, null, null);

        // Adding keys to table local_wub_policy_accept.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table local_wub_policy_accept.
        $table->add_index('userid_role_ver', XMLDB_INDEX_NOTUNIQUE, ['userid', 'role', 'policyversion']);
        $table->add_index('device_role_ver', XMLDB_INDEX_NOTUNIQUE, ['deviceidentifier', 'role', 'policyversion']);
        $table->add_index('timeaccepted', XMLDB_INDEX_NOTUNIQUE, ['timeaccepted']);

        // Conditionally launch create table for local_wub_policy_accept.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Policy savepoint reached.
        upgrade_plugin_savepoint(true, 2026081400, 'local', 'wub_policy');
    }

    return true;
}
