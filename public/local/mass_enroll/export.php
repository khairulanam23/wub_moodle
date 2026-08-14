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
 * Secure export download handler for local_mass_enroll.
 *
 * @package    local_mass_enroll
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/mass_enroll:enrol', $context);

global $USER;

$fs = get_file_storage();
$stored_file = $fs->get_file($context->id, 'local_mass_enroll', 'export', (int)$USER->id, '/', 'enrolment_records_' . $USER->id . '.xlsx');

if (!$stored_file || $stored_file->is_directory()) {
    throw new \moodle_exception('filenotfound', 'error');
}

// Serve the stored file safely.
send_stored_file($stored_file, 0, 0, true, ['filename' => 'enrolment_records.xlsx']);
