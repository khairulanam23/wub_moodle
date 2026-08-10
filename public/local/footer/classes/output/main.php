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
 * Renderable class for WUB Custom Footer (Compact single-line footer).
 *
 * @package    local_footer
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_footer\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Footer renderable class.
 */
class main implements renderable, templatable {

    /**
     * Export data for Mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Data for Mustache.
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();

        $data->year = date('Y');
        $data->companyurl = 'http://computinginfoservices.com/';
        $data->moodleurl = 'https://moodle.org';

        return $data;
    }
}
