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

namespace local_bulk_enrolment\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;

/**
 * Output renderer for local_bulk_enrolment.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the main bulk enrolment workspace.
     *
     * @param array|\stdClass $data
     * @return string HTML output
     */
    public function render_enrolment_page($data): string {
        return $this->render_from_template('local_bulk_enrolment/enrolment_page', $data);
    }

    /**
     * Render the pre-enrolment validation matrix.
     *
     * @param array|\stdClass $data
     * @return string HTML output
     */
    public function render_preview_matrix($data): string {
        return $this->render_from_template('local_bulk_enrolment/preview_matrix', $data);
    }

    /**
     * Render the post-execution audit report.
     *
     * @param array|\stdClass $data
     * @return string HTML output
     */
    public function render_result_report($data): string {
        return $this->render_from_template('local_bulk_enrolment/result_report', $data);
    }

    /**
     * Render the UMS integration and diagnostics console.
     *
     * @param array|\stdClass $data
     * @return string HTML output
     */
    public function render_ums_diagnostics($data): string {
        return $this->render_from_template('local_bulk_enrolment/ums_diagnostics', $data);
    }

    /**
     * Render the bulk unenrolment workspace.
     *
     * @param array|\stdClass $data
     * @return string HTML output
     */
    public function render_unenrolment_page($data): string {
        return $this->render_from_template('local_bulk_enrolment/unenrolment_page', $data);
    }

    /**
     * Render the student synchronization and identity management page.
     *
     * @param array|\stdClass $data
     * @return string HTML output
     */
    public function render_sync_page($data): string {
        return $this->render_from_template('local_bulk_enrolment/sync_page', $data);
    }
}
