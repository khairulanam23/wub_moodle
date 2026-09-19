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
 * UMS answered but the answer is unusable: 4xx, malformed JSON or unexpected schema.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\exception;

/**
 * Bad response (HTTP 4xx other than auth, invalid JSON, schema mismatch).
 */
class ums_response_exception extends ums_exception {
    /**
     * Constructor.
     *
     * @param string $detail Sanitised detail.
     * @param bool $isjsonerror True when JSON decoding failed.
     * @param int $httpcode HTTP status for 4xx failures.
     * @param bool $isschema True when the JSON was valid but the structure unexpected.
     */
    public function __construct(string $detail, bool $isjsonerror = false, int $httpcode = 0, bool $isschema = false) {
        if ($isjsonerror) {
            parent::__construct('error_invalid_json', '', $detail, 'UMS_BAD_JSON', $httpcode);
        } else if ($isschema) {
            parent::__construct('error_unexpected_schema', $detail, $detail, 'UMS_SCHEMA', $httpcode);
        } else if ($httpcode >= 400) {
            parent::__construct('error_http_client', (string)$httpcode, $detail, 'UMS_HTTP_4XX', $httpcode);
        } else {
            parent::__construct('error_unexpected_response', $detail, $detail, 'UMS_SCHEMA', $httpcode);
        }
    }
}
