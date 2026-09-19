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
 * UMS authentication / authorisation failure (HTTP 401 / 403). Never retried.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\exception;

/**
 * Authentication (401) or authorisation (403) failure.
 */
class ums_authentication_exception extends ums_exception {
    /**
     * Constructor.
     *
     * @param int $httpcode 401 or 403.
     * @param string $debuginfo Debug info.
     */
    public function __construct(int $httpcode = 401, string $debuginfo = '') {
        $code = ($httpcode === 403) ? 'UMS_FORBIDDEN' : 'UMS_AUTH';
        parent::__construct('error_auth_failed', (string)$httpcode, $debuginfo, $code, $httpcode);
    }
}
