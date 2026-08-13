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

namespace local_mass_enroll\local\hooks;

/**
 * Hook implementation for local_mass_enroll.
 *
 * @package    local_mass_enroll
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extend_bulk_user_actions {
    /**
     * Callback method for core_user\hook\extend_bulk_user_actions
     *
     * @param \core_user\hook\extend_bulk_user_actions $hook
     */
    public static function callback(\core_user\hook\extend_bulk_user_actions $hook): void {
        // No action injected into Site Administration Bulk user actions
    }
}
