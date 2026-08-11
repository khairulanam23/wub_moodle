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
 * Renderable class for WUB course details.
 *
 * @package    local_wub_landing
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_wub_landing\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use moodle_url;
use stdClass;
use core_course_category;
use core_course_list_element;

/**
 * Course details renderable.
 *
 * Exports detailed course information for the details page template.
 * Only exposes information that is safe for public/guest viewing.
 */
class course_details implements renderable, templatable {

    /** @var stdClass The raw course record. */
    private stdClass $course;

    /** @var bool Whether the current user is authenticated. */
    private bool $isauthenticated;

    /** @var bool Whether the current user is enrolled. */
    private bool $isenrolled;

    /**
     * Constructor.
     *
     * @param stdClass $course The course record.
     * @param bool $isauthenticated Whether the user is logged in.
     * @param bool $isenrolled Whether the user is enrolled in this course.
     */
    public function __construct(stdClass $course, bool $isauthenticated, bool $isenrolled) {
        $this->course = $course;
        $this->isauthenticated = $isauthenticated;
        $this->isenrolled = $isenrolled;
    }

    /**
     * Export data for Mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Template data.
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $CFG;

        $data = new stdClass();

        // Create a list element wrapper for accessing course contacts and overview files.
        $listelement = new core_course_list_element($this->course);

        // Basic course info.
        $data->courseid = $this->course->id;
        $data->fullname = $listelement->get_formatted_fullname();
        $data->shortname = $listelement->get_formatted_shortname();

        // Category.
        try {
            $cat = core_course_category::get($this->course->category, MUST_EXIST, true);
            $data->categoryname = $cat->get_formatted_name();
        } catch (\Exception $e) {
            $data->categoryname = '';
        }
        $data->hascategory = !empty($data->categoryname);

        // Summary (full, formatted for display).
        if ($listelement->has_summary()) {
            $data->summary = format_text(
                $this->course->summary,
                $this->course->summaryformat,
                ['context' => \context_course::instance($this->course->id)]
            );
            $data->hassummary = true;
        } else {
            $data->summary = get_string('nosummary', 'local_wub_landing');
            $data->hassummary = false;
        }

        // Course image.
        $data->courseimageurl = $this->get_course_image_url($listelement);
        $data->hascourseimage = !empty($data->courseimageurl);

        // Course contacts (instructors).
        $contacts = $listelement->get_course_contacts();
        $data->instructors = [];
        foreach ($contacts as $contact) {
            $data->instructors[] = [
                'name' => $contact['username'],
                'role' => $contact['rolename'],
            ];
        }
        $data->hasinstructors = !empty($data->instructors);

        // Action button logic.
        $data->isauthenticated = $this->isauthenticated;
        $data->isenrolled = $this->isenrolled;

        if ($this->isenrolled) {
            // User is enrolled — show "Go to Course".
            $data->actionurl = (new moodle_url('/course/view.php', ['id' => $this->course->id]))->out(false);
            $data->actionlabel = get_string('gotocourse', 'local_wub_landing');
            $data->actionclass = 'wub-btn-gotocourse';
        } else if ($this->isauthenticated) {
            // User is logged in but not enrolled — show "Enrol Me".
            $data->actionurl = (new moodle_url('/enrol/index.php', ['id' => $this->course->id]))->out(false);
            $data->actionlabel = get_string('enrolme', 'local_wub_landing');
            $data->actionclass = 'wub-btn-enrol';
        } else {
            // Guest — show "Login to Access Course".
            $data->actionurl = (new moodle_url('/login/index.php'))->out(false);
            $data->actionlabel = get_string('logintoacccess', 'local_wub_landing');
            $data->actionclass = 'wub-btn-login';
        }

        // Navigation links.
        $data->catalogurl = (new moodle_url('/local/wub_landing/catalog.php'))->out(false);

        // String labels.
        $data->str_backtocatalog = get_string('backtocatalog', 'local_wub_landing');
        $data->str_courseinformation = get_string('courseinformation', 'local_wub_landing');
        $data->str_coursesummary = get_string('coursesummary', 'local_wub_landing');
        $data->str_instructor = get_string('instructor', 'local_wub_landing');
        $data->str_instructors = get_string('instructors', 'local_wub_landing');
        $data->str_coursecategory = get_string('coursecategory', 'local_wub_landing');

        return $data;
    }

    /**
     * Get the course overview image URL.
     *
     * @param core_course_list_element $listelement The course list element.
     * @return string The image URL, or empty string.
     */
    private function get_course_image_url(core_course_list_element $listelement): string {
        global $CFG;

        foreach ($listelement->get_course_overviewfiles() as $file) {
            if ($file->is_valid_image()) {
                return moodle_url::make_file_url(
                    "$CFG->wwwroot/pluginfile.php",
                    '/' . $file->get_contextid() . '/' . $file->get_component() . '/' .
                    $file->get_filearea() . $file->get_filepath() . $file->get_filename(),
                    false
                );
            }
        }

        return 'https://images.unsplash.com/photo-1553877522-43269d4ea984?q=80&w=870&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D';
    }
}
