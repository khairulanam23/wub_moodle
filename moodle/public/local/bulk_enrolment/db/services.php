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
 * External functions (AJAX) for local_bulk_enrolment.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_bulk_enrolment_get_programs' => [
        'classname' => 'local_bulk_enrolment\external\get_programs',
        'description' => 'List UMS academic programs (API #2)',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/bulk_enrolment:view',
    ],
    'local_bulk_enrolment_get_batches' => [
        'classname' => 'local_bulk_enrolment\external\get_batches',
        'description' => 'List UMS batches for programs (API #3)',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/bulk_enrolment:view',
    ],
    'local_bulk_enrolment_get_roster' => [
        'classname' => 'local_bulk_enrolment\external\get_roster',
        'description' => 'Paginated, identity-matched UMS student roster (API #4, enriched by API #1)',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/bulk_enrolment:view',
    ],
    'local_bulk_enrolment_preview_matrix' => [
        'classname' => 'local_bulk_enrolment\external\preview_matrix',
        'description' => 'Pre-enrolment verification matrix (student x course)',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/bulk_enrolment:view',
    ],
    'local_bulk_enrolment_execute_chunk' => [
        'classname' => 'local_bulk_enrolment\external\execute_chunk',
        'description' => 'Enrol a bounded chunk of resolved users into one course via enrol_manual',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/bulk_enrolment:view,enrol/manual:enrol',
    ],
];
