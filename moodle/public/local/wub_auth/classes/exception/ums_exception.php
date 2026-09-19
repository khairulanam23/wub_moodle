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

namespace local_wub_auth\exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Base UMS exception.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ums_exception extends wub_auth_exception {
    /** @var int HTTP response code or 0 */
    protected int $httpcode;

    /**
     * Constructor.
     *
     * @param string $errorcode
     * @param string $debuginfo
     * @param int $httpcode
     */
    public function __construct(string $errorcode = 'error_ums_unavailable', string $debuginfo = '', int $httpcode = 0) {
        $this->httpcode = $httpcode;
        parent::__construct($errorcode, 'local_wub_auth', '', null, $debuginfo);
    }

    /**
     * Get HTTP code.
     *
     * @return int
     */
    public function get_http_code(): int {
        return $this->httpcode;
    }
}
