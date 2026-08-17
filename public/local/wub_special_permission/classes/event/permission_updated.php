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

namespace local_wub_special_permission\event;

defined('MOODLE_INTERNAL') || die();

use core\event\base;

/**
 * Event logged when an administrator grants, updates, or revokes a student special login permission.
 *
 * @package    local_wub_special_permission
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission_updated extends base {

    /**
     * Init event properties.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Get event human-readable name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('pluginname', 'local_wub_special_permission') . ' Event';
    }

    /**
     * Get event description for audit logs.
     *
     * @return string
     */
    public function get_description() {
        $studentId = $this->relateduserid;
        $adminId = $this->userid;
        $newExpiry = $this->other['expiry_date'] ?? 'Revoked';
        $action = $this->other['action'] ?? 'updated';

        return "The user with id '$adminId' $action special login permission for student with id '$studentId'. New expiry: '$newExpiry'.";
    }

    /**
     * Get URL related to event.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/wub_special_permission/index.php', ['search' => $this->relateduserid]);
    }
}
