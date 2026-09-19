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

namespace local_wub_auth\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use context_system;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem for local_wub_auth.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describe personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('wub_auth_waivers', [
            'userid' => 'privacy:metadata:waivers:userid',
            'status' => 'privacy:metadata:waivers:status',
            'timeend' => 'privacy:metadata:waivers:timeend',
            'grantedby' => 'privacy:metadata:waivers:grantedby',
            'reason' => 'privacy:metadata:waivers:reason',
        ], 'privacy:metadata:waivers');

        $collection->add_database_table('wub_auth_policy_accept', [
            'userid' => 'privacy:metadata:policy_accept:userid',
            'role' => 'privacy:metadata:policy_accept:role',
            'policyversion' => 'privacy:metadata:policy_accept:policyversion',
            'timeaccepted' => 'privacy:metadata:policy_accept:timeaccepted',
        ], 'privacy:metadata:policy_accept');

        $collection->add_database_table('wub_auth_audit', [
            'userid' => 'privacy:metadata:audit:userid',
            'action' => 'privacy:metadata:audit:action',
            'status' => 'privacy:metadata:audit:status',
            'timecreated' => 'privacy:metadata:audit:timecreated',
        ], 'privacy:metadata:audit');

        return $collection;
    }

    /**
     * Get contexts that contain user information for the specified user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    /**
     * Export all user data for the specified approved_contextlist.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        $userid = (int)$user->id;
        $context = context_system::instance();

        // Export Waivers
        $waivers = $DB->get_records('wub_auth_waivers', ['userid' => $userid]);
        if (!empty($waivers)) {
            $data = [];
            foreach ($waivers as $w) {
                $data[] = [
                    'status' => $w->status ? 'Active' : 'Revoked',
                    'timestart' => transform::datetime($w->timestart),
                    'timeend' => transform::datetime($w->timeend),
                    'reason' => $w->reason,
                ];
            }
            writer::with_context($context)->export_data([get_string('pluginname', 'local_wub_auth'), 'Waivers'], (object)['waivers' => $data]);
        }

        // Export Policy Acceptances
        $policies = $DB->get_records('wub_auth_policy_accept', ['userid' => $userid]);
        if (!empty($policies)) {
            $data = [];
            foreach ($policies as $p) {
                $data[] = [
                    'role' => $p->role,
                    'version' => $p->policyversion,
                    'timeaccepted' => transform::datetime($p->timeaccepted),
                ];
            }
            writer::with_context($context)->export_data([get_string('pluginname', 'local_wub_auth'), 'PolicyAcceptances'], (object)['policies' => $data]);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof context_system) {
            $DB->delete_records('wub_auth_waivers');
            $DB->delete_records('wub_auth_policy_accept');
            $DB->delete_records('wub_auth_audit');
        }
    }

    /**
     * Delete all user data for the specified user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        $DB->delete_records('wub_auth_waivers', ['userid' => $userid]);
        $DB->delete_records('wub_auth_policy_accept', ['userid' => $userid]);
        $DB->delete_records('wub_auth_audit', ['userid' => $userid]);
    }
}
