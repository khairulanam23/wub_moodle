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
 * local_mass_enroll plugin library.
 *
 * @package    local_mass_enroll
 * @copyright  2021 World University of Bangladesh (CIS)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend navigation menu for local_mass_enroll.
 *
 * @param global_navigation $nav
 */
function local_mass_enroll_extend_navigation(global_navigation $nav) {
    if (is_siteadmin()) {
        $node = $nav->add(get_string('pluginname', 'local_mass_enroll'), new moodle_url('/local/mass_enroll/enrolled.php'), navigation_node::TYPE_CUSTOM);
        $node->add(get_string('enrolled_navbar', 'local_mass_enroll'), new moodle_url('/local/mass_enroll/enrolled.php'));
        $node->add(get_string('enrolled_sync', 'local_mass_enroll'), new moodle_url('/local/mass_enroll/sync.php'));
        $node->add(get_string('records_navbar', 'local_mass_enroll'), new moodle_url('/local/mass_enroll/records.php'));
    }
}