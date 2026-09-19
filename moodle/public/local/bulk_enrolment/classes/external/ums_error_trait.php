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
 * Shared error envelope for UMS-backed external functions.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\external;

use core_external\external_single_structure;
use core_external\external_value;
use local_bulk_enrolment\exception\ums_exception;

/**
 * Converts ums_exception into a structured, user-safe envelope.
 */
trait ums_error_trait {
    /**
     * Envelope fields shared by all responses.
     *
     * @return array
     */
    protected static function envelope_fields(): array {
        return [
            'ok' => new external_value(PARAM_BOOL, 'Whether the UMS/Moodle operation succeeded'),
            'error_code' => new external_value(PARAM_ALPHANUMEXT, 'Stable error code when ok=false, empty otherwise'),
            'error_message' => new external_value(PARAM_TEXT, 'User-safe error message when ok=false'),
        ];
    }

    /**
     * Build the failure envelope for a UMS exception.
     *
     * @param ums_exception $e
     * @return array
     */
    protected static function failure(ums_exception $e): array {
        $code = $e->get_error_code();
        $key = 'uierr_' . strtolower($code);
        $message = get_string_manager()->string_exists($key, 'local_bulk_enrolment')
            ? get_string($key, 'local_bulk_enrolment') : get_string('uierr_ums_error', 'local_bulk_enrolment');
        $detail = trim((string)$e->a);
        if ($detail !== '' && in_array($code, ['INVALID_BATCH', 'INVALID_PROGRAM', 'UMS_HTTP_4XX', 'UMS_HTTP_5XX', 'UMS_TIMEOUT'], true)) {
            $message .= ' (' . s($detail) . ')';
        }
        return ['ok' => false, 'error_code' => $code, 'error_message' => $message];
    }
}
