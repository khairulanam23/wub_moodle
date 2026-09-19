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
 * Data export endpoint for WUB Bulk Enrolment.
 *
 * Provides downloadable sample templates and execution reports.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/bulk_enrolment:view', $context);

$action = optional_param('action', 'template', PARAM_ALPHA);
$type = optional_param('type', 'enrol', PARAM_ALPHA);

$exportService = new \local_bulk_enrolment\service\export_service();

if ($action === 'template') {
    if ($type === 'unenrol') {
        $headers = ['SL', 'student', 'course'];
        $rows = [
            ['student' => '0326735386', 'course' => 'CSE101'],
            ['student' => '0326735387', 'course' => 'CSE101'],
        ];
        $exportService->export_csv('sample_bulk_unenrolment_template', $headers, $rows);
    } else {
        $headers = ['SL', 'username', 'course', 'role', 'group'];
        $rows = [
            ['username' => '0326735386', 'course' => 'CSE101', 'role' => 'student', 'group' => 'Section A'],
            ['username' => '0326735387', 'course' => 'CSE101', 'role' => 'student', 'group' => 'Section A'],
        ];
        $exportService->export_csv('sample_bulk_enrolment_template', $headers, $rows);
    }
}

redirect(new moodle_url('/local/bulk_enrolment/index.php'));
