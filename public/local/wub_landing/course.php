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
 * Public course details page for WUB Landing.
 *
 * Displays detailed information about a single course. Publicly accessible,
 * but respects Moodle's course visibility rules.
 *
 * @package    local_wub_landing
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// Get the course ID.
$courseid = required_param('id', PARAM_INT);

// Validate the course exists and is visible.
$course = get_course($courseid);

// Check visibility — use Moodle's can_view_course_info to respect access rules.
if (!\core_course_category::can_view_course_info($course)) {
    throw new \moodle_exception('coursenotfound', 'local_wub_landing');
}

// Determine user state.
$isauthenticated = isloggedin() && !isguestuser();
$isenrolled = false;
if ($isauthenticated) {
    $coursecontext = context_course::instance($courseid);
    $isenrolled = is_enrolled($coursecontext);
}

// Page setup.
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/wub_landing/course.php', ['id' => $courseid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title($course->fullname);
$PAGE->set_heading($course->fullname);

// Breadcrumbs.
$PAGE->navbar->add(
    get_string('pluginname', 'local_wub_landing'),
    new moodle_url('/local/wub_landing/index.php')
);
$PAGE->navbar->add(
    get_string('coursecatalog', 'local_wub_landing'),
    new moodle_url('/local/wub_landing/catalog.php')
);
$PAGE->navbar->add($course->fullname);

// Prepare renderable.
$renderable = new \local_wub_landing\output\course_details($course, $isauthenticated, $isenrolled);

// Render — header() must be called first to initialize the full renderer.
echo $OUTPUT->header();

$templatedata = $renderable->export_for_template($OUTPUT);
echo $OUTPUT->render_from_template('local_wub_landing/course_details', $templatedata);
echo $OUTPUT->footer();
