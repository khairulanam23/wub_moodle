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

namespace local_wub_auth\hook;

use context_system;
use moodle_url;
use navigation_node;
use pix_icon;

defined('MOODLE_INTERNAL') || die();

/**
 * Primary navigation hook callback for role-based navbar customization.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation_hook {

    /**
     * Extend or override primary navigation nodes based on user roles and capabilities.
     *
     * @param \core\hook\navigation\primary_extend $hook
     * @return void
     */
    public static function extend_primary_navigation(\core\hook\navigation\primary_extend $hook): void {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        $primaryview = $hook->get_primaryview();

        // Clear all existing default nodes so we have deterministic role-based control.
        $keys = $primaryview->get_children_key_list();
        foreach ($keys as $key) {
            $item = $primaryview->get($key);
            if ($item) {
                $item->remove();
            }
        }

        $syscontext = context_system::instance();
        $isadmin = is_siteadmin($USER) || has_capability('moodle/site:config', $syscontext);

        if ($isadmin) {
            // Admin Navbar Items:
            // 1. Dashboard -> /my/
            $primaryview->add(
                get_string('myhome'),
                new moodle_url('/my/'),
                navigation_node::TYPE_SETTING,
                null,
                'myhome',
                new pix_icon('i/dashboard', '')
            );

            // 2. Site administration -> /admin/search.php
            $primaryview->add(
                get_string('administrationsite'),
                new moodle_url('/admin/search.php'),
                navigation_node::TYPE_SITE_ADMIN,
                null,
                'siteadminnode',
                new pix_icon('i/navigationitem', '')
            );

            // 3. Course Management -> /course/management.php
            $primaryview->add(
                get_string('nav_coursemanagement', 'local_wub_auth'),
                new moodle_url('/course/management.php'),
                navigation_node::TYPE_CUSTOM,
                null,
                'coursemanagement',
                new pix_icon('i/course', '')
            );

            // 4. User Accounts -> /admin/user.php
            $primaryview->add(
                get_string('nav_useraccounts', 'local_wub_auth'),
                new moodle_url('/admin/user.php'),
                navigation_node::TYPE_CUSTOM,
                null,
                'useraccounts',
                new pix_icon('i/user', '')
            );

            // 5. Bulk Enrolment -> /local/bulk_enrolment/
            $primaryview->add(
                get_string('nav_bulkenrolment', 'local_wub_auth'),
                new moodle_url('/local/bulk_enrolment/'),
                navigation_node::TYPE_CUSTOM,
                null,
                'bulkenrolment',
                new pix_icon('i/enrolusers', '')
            );
        } else {
            // Teacher and Student Navbar Items:
            // 1. Dashboard -> /my/
            $primaryview->add(
                get_string('myhome'),
                new moodle_url('/my/'),
                navigation_node::TYPE_SETTING,
                null,
                'myhome',
                new pix_icon('i/dashboard', '')
            );

            // 2. My Courses -> /my/courses.php
            $primaryview->add(
                get_string('mycourses'),
                new moodle_url('/my/courses.php'),
                navigation_node::TYPE_ROOTNODE,
                null,
                'mycourses',
                new pix_icon('i/course', '')
            );
        }
    }
}
