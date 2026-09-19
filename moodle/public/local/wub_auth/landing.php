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
 * WUB Institutional Landing Page Controller.
 *
 * Primary entry point for unauthenticated visitors. Matches institutional
 * visual reference with role selection (Student, Teacher, Administration),
 * Course Catalog access, and support links.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $CFG, $USER, $PAGE, $OUTPUT;

// 1. If already authenticated as a genuine user, redirect to dashboard.
if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/my/'));
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/wub_auth/landing.php'));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string('landing_title', 'local_wub_auth'));
$PAGE->set_heading(get_string('landing_welcome', 'local_wub_auth'));

// Preserve any returnurl parameter so the user returns to their requested resource post-login.
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$extraparams = [];
if (!empty($returnurl)) {
    $extraparams['returnurl'] = $returnurl;
}

$bgimgurl = (new moodle_url('/local/wub_auth/pix/wub_campus_bg.jpg'))->out(false);
$towerimgurl = (new moodle_url('/local/wub_auth/pix/card_tower.jpg'))->out(false);

$templatedata = [
    'bg_img_url' => $bgimgurl,
    'tower_img_url' => $towerimgurl,
    'student_url' => (new moodle_url('/local/wub_auth/policy.php', array_merge(['role' => 'student'], $extraparams)))->out(false),
    'teacher_url' => (new moodle_url('/local/wub_auth/policy.php', array_merge(['role' => 'teacher'], $extraparams)))->out(false),
    'admin_url' => (new moodle_url('/local/wub_auth/policy.php', array_merge(['role' => 'admin'], $extraparams)))->out(false),
    'catalog_url' => (new moodle_url('/course/index.php'))->out(false),
    'contact_url' => 'https://wub.edu.bd/contact',
    'guides_url' => (new moodle_url('/local/wub_auth/guides.php'))->out(false),
    'footer_html' => get_string('landing_footer_text', 'local_wub_auth'),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_wub_auth/landing_page', $templatedata);
echo $OUTPUT->footer();
