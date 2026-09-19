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
 * Institutional Portal How-To Guides Controller.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $CFG, $PAGE, $OUTPUT;

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/wub_auth/guides.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('guides_title', 'local_wub_auth'));
$PAGE->set_heading(get_string('guides_heading', 'local_wub_auth'));

$templatedata = [
    'landing_url' => (new moodle_url('/local/wub_auth/landing.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_wub_auth/guides_page', $templatedata);
echo $OUTPUT->footer();
