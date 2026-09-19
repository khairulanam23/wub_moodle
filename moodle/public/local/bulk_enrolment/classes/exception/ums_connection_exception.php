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
 * Transport-level failure: DNS, TCP, TLS, timeout or repeated 5xx.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\exception;

/**
 * Network / server-side failure after retries.
 */
class ums_connection_exception extends ums_exception {
    /**
     * Constructor.
     *
     * @param string $detail Sanitised detail.
     * @param bool $istimeout Whether it was a timeout.
     * @param int $timeoutsec Timeout in seconds.
     * @param int $httpcode HTTP status for 5xx failures, 0 for transport errors.
     */
    public function __construct(string $detail, bool $istimeout = false, int $timeoutsec = 0, int $httpcode = 0) {
        if ($istimeout) {
            parent::__construct('error_connection_timeout', (string)$timeoutsec, $detail, 'UMS_TIMEOUT');
        } else if ($httpcode >= 500) {
            parent::__construct('error_server_error', (string)$httpcode, $detail, 'UMS_HTTP_5XX', $httpcode);
        } else {
            parent::__construct('error_connection_failed', $detail, $detail, 'UMS_CONNECTION');
        }
    }
}
