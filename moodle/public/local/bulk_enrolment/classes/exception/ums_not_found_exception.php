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
 * UMS returned HTTP 404: no records for the given identifiers.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\exception;

/**
 * HTTP 404 from UMS. Whether this means "empty" or "invalid identifier" depends on the
 * endpoint and must be decided by the caller (e.g. after validating a batch title).
 */
class ums_not_found_exception extends ums_exception {
    /**
     * Constructor.
     *
     * @param string $endpoint Endpoint path (no query string, no credentials).
     * @param string $detail Sanitised message returned by UMS, if any.
     */
    public function __construct(string $endpoint, string $detail = '') {
        parent::__construct('error_not_found', $endpoint, $detail, 'UMS_NOT_FOUND', 404);
    }
}
