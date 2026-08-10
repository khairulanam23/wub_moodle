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
 * Privacy API implementation for local_wub_landing.
 *
 * This plugin does not store any personal data. The only session
 * variable used (wub_intended_role) is transient, immediately consumed
 * after authentication, and is not persisted to the database.
 *
 * @package    local_wub_landing
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_wub_landing\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider — null provider.
 *
 * This plugin does not store any personal data in the database.
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * Get the language string identifier with a description of why this plugin
     * stores no data.
     *
     * @return string The language string identifier.
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
