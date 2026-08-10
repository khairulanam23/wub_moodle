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
 * Renderable class for WUB Custom Header (Transparent Navbar with Logo).
 *
 * @package    local_header
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_header\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use moodle_url;
use stdClass;

/**
 * Header renderable class.
 */
class main implements renderable, templatable {

    /**
     * Export data for Mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Data for Mustache.
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER, $CFG;

        $data = new stdClass();

        $isauthenticated = isloggedin() && !isguestuser();
        $data->isauthenticated = $isauthenticated;

        // Logo click redirection target:
        // If user is logged in -> redirect to home page ('/').
        // If user is NOT logged in -> redirect to /local/wub_landing/index.php.
        if ($isauthenticated) {
            $data->logolinkurl = (new moodle_url('/'))->out(false);
        } else {
            $data->logolinkurl = (new moodle_url('/local/wub_landing/index.php'))->out(false);
        }

        // WUB Logo URL pointing to /local/wub_landing/pix/wub-logo.png.
        $data->logourl = (new moodle_url('/local/wub_landing/pix/wub-logo.png'))->out(false);

        return $data;
    }
}
