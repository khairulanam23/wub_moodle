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
 * UMS integration is disabled or not configured.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\exception;

/**
 * Configuration problem (disabled, missing credentials, invalid URL).
 */
class ums_configuration_exception extends ums_exception {
    /**
     * Constructor.
     *
     * @param string $errorcode Language string key.
     * @param string $debuginfo Debug info.
     */
    public function __construct(string $errorcode = 'error_config_missing', string $debuginfo = '') {
        parent::__construct($errorcode, '', $debuginfo, 'UMS_NOT_CONFIGURED');
    }
}
