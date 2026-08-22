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
 * WUB public portal layout.
 *
 * Renders the WUB marketing chrome (local_header / local_footer) and nothing
 * else -- no Moodle navbar, no theme header-main, no page heading, no theme
 * footer. Pages using this layout must NOT echo the header/footer themselves;
 * that double-render is exactly what this layout exists to prevent.
 *
 * @package   theme_academi
 * @copyright 2026 WUB eLearning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$wubheader = '';
$wubfooter = '';

$headerlib = $CFG->dirroot . '/local/header/lib.php';
if (file_exists($headerlib)) {
    require_once($headerlib);
    if (function_exists('local_header_render')) {
        $wubheader = local_header_render($OUTPUT);
    }
}

$footerlib = $CFG->dirroot . '/local/footer/lib.php';
if (file_exists($footerlib)) {
    require_once($footerlib);
    if (function_exists('local_footer_render')) {
        $wubfooter = local_footer_render($OUTPUT);
    }
}

$templatecontext = [
    'output' => $OUTPUT,
    'sitename' => format_string(
        $SITE->fullname,
        true,
        ['context' => context_course::instance(SITEID), 'escape' => false]
    ),
    'bodyattributes' => $OUTPUT->body_attributes(['wub-portal']),
    'wubheader' => $wubheader,
    'wubfooter' => $wubfooter,
];

echo $OUTPUT->render_from_template('theme_academi/wubportal', $templatecontext);
