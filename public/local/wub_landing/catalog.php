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
 * Public course catalog page for WUB Landing.
 *
 * This page is publicly accessible without authentication.
 * It displays courses that are visible to the current user (guest).
 *
 * @package    local_wub_landing
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// Check if catalog is enabled.
$catalogenabled = get_config('local_wub_landing', 'catalog_enabled');
if ($catalogenabled !== false && !$catalogenabled) {
    redirect(new moodle_url('/local/wub_landing/index.php'));
}

// Get parameters.
$search = optional_param('search', '', PARAM_TEXT);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

// Get courses per page from settings.
$perpage = (int) get_config('local_wub_landing', 'courses_per_page');
if ($perpage <= 0) {
    $perpage = 12;
}

// Page setup — no require_login() for public access.
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/wub_landing/catalog.php', [
    'search' => $search,
    'categoryid' => $categoryid,
    'page' => $page,
]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('coursecatalog', 'local_wub_landing'));
$PAGE->set_heading(get_string('coursecatalog', 'local_wub_landing'));

// Breadcrumbs.
$PAGE->navbar->add(
    get_string('pluginname', 'local_wub_landing'),
    new moodle_url('/local/wub_landing/index.php')
);
$PAGE->navbar->add(get_string('coursecatalog', 'local_wub_landing'));

// Prepare renderable.
$renderable = new \local_wub_landing\output\course_catalog($search, $categoryid, $page, $perpage);

// Render — header() must be called first to initialize the full renderer.
echo $OUTPUT->header();

$templatedata = $renderable->export_for_template($OUTPUT);
echo $OUTPUT->render_from_template('local_wub_landing/course_catalog', $templatedata);
echo $OUTPUT->footer();