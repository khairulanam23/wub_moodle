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
 * WUB Landing page entry point.
 *
 * This is the main landing page for the WUB eLearning portal.
 * It is publicly accessible — guests do not need to log in.
 *
 * @package    local_wub_landing
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// Check if plugin is enabled.
$enabled = get_config('local_wub_landing', 'enabled');
if ($enabled !== false && !$enabled) {
    redirect(new moodle_url('/'));
}

// Determine authentication state without forcing login.
$isauthenticated = isloggedin() && !isguestuser();

// Page setup.
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/wub_landing/index.php'));
$PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('pluginname', 'local_wub_landing'));
$PAGE->set_heading(get_string('pluginname', 'local_wub_landing'));

require_once($CFG->dirroot . '/local/header/lib.php');
require_once($CFG->dirroot . '/local/footer/lib.php');

// Prepare renderable.
$user = $isauthenticated ? $USER : null;
$renderable = new \local_wub_landing\output\landing_page($isauthenticated, $user);

// Render output — header() must be called first to initialize the full renderer.
echo $OUTPUT->header();

// Render custom header.
if (function_exists('local_header_render')) {
    echo local_header_render($OUTPUT);
}

$templatedata = $renderable->export_for_template($OUTPUT);

if ($isauthenticated) {
    echo $OUTPUT->render_from_template('local_wub_landing/landing_page_auth', $templatedata);
} else {
    echo $OUTPUT->render_from_template('local_wub_landing/landing_page', $templatedata);
}

// Render custom footer.
if (function_exists('local_footer_render')) {
    echo local_footer_render($OUTPUT);
}

echo $OUTPUT->footer();