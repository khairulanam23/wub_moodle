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
 * Base UMS exception with a stable machine-readable error code.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\exception;

use moodle_exception;

/**
 * Base class for all UMS integration failures.
 */
class ums_exception extends moodle_exception {
    /** @var string Stable error code consumed by the UI (e.g. UMS_AUTH). */
    protected string $umscode = 'UMS_ERROR';

    /** @var int HTTP status code when the failure came from an HTTP response, 0 otherwise. */
    protected int $httpcode = 0;

    /**
     * Constructor.
     *
     * @param string $errorcode Language string key.
     * @param string $a Language string parameter.
     * @param string $debuginfo Debug information (never contains credentials).
     * @param string $umscode Stable machine-readable code.
     * @param int $httpcode HTTP status when applicable.
     */
    public function __construct(string $errorcode, string $a = '', string $debuginfo = '', string $umscode = 'UMS_ERROR', int $httpcode = 0) {
        $this->umscode = $umscode;
        $this->httpcode = $httpcode;
        parent::__construct($errorcode, 'local_bulk_enrolment', '', $a, $debuginfo);
    }

    /**
     * Stable machine-readable error code.
     *
     * @return string
     */
    public function get_error_code(): string {
        return $this->umscode;
    }

    /**
     * HTTP status code (0 when not an HTTP failure).
     *
     * @return int
     */
    public function get_http_code(): int {
        return $this->httpcode;
    }
}
