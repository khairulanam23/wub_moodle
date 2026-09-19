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
 * Upgrade steps for local_bulk_enrolment.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute upgrade logic for local_bulk_enrolment.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_bulk_enrolment_upgrade(int $oldversion): bool {
    global $DB;

    // Migrate configuration keys from local_wub_ums if present.
    $keys = [
        'enabled',
        'base_url',
        'connect_timeout',
        'timeout',
        'ssl_verify',
        'api_username',
        'api_password',
        'api_key',
    ];

    foreach ($keys as $key) {
        $oldval = get_config('local_wub_ums', $key);
        if ($oldval !== false && get_config('local_bulk_enrolment', $key) === false) {
            set_config($key, $oldval, 'local_bulk_enrolment');
        }
    }

    if ($oldversion < 2026091401) {
        // Upgrade savepoint for migrated legacy CSV bulk enrolment, unenrolment, and group services.
        upgrade_plugin_savepoint(true, 2026091401, 'local', 'bulk_enrolment');
    }

    return true;
}
