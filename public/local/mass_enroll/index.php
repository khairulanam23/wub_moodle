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
 * Allows course enrolment via a simple text code.
 *
 * @package   local_mass_enroll
 * @copyright 2021 World University of Bangladesh (CIS)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
$PAGE->set_url(new moodle_url('/local/mass_enroll/index.php'));
$PAGE->set_context(\context_system::instance());
$PAGE->set_title("Student Bulk Enrollment");
$PAGE->navbar->add(get_string("pluginname","local_mass_enroll"),"/local/mass_enroll/index.php");

$data = (object)[
    'testdata' => "this is sent main page data testing for server lang",
    "steps" => []
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mass_enroll/index', $data);
echo $OUTPUT->footer();
