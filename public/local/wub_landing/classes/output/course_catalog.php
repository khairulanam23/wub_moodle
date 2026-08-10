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
 * Renderable class for the WUB course catalog.
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
 * Course catalog renderable.
 *
 * Fetches and formats course data for the catalog template, respecting
 * Moodle's course visibility and access rules.
 */
class course_catalog implements renderable, templatable {

    /** @var string Search query. */
    private string $search;

    /** @var int Category filter ID (0 = all). */
    private int $categoryid;

    /** @var int Current page number (0-indexed). */
    private int $page;

    /** @var int Courses per page. */
    private int $perpage;

    /**
     * Constructor.
     *
     * @param string $search Search query string.
     * @param int $categoryid Category filter ID.
     * @param int $page Current page (0-indexed).
     * @param int $perpage Courses per page.
     */
    public function __construct(string $search = '', int $categoryid = 0, int $page = 0, int $perpage = 12) {
        $this->search = trim($search);
        $this->categoryid = $categoryid;
        $this->page = $page;
        $this->perpage = $perpage;
    }

    /**
     * Export data for Mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Template data.
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();

        // Page heading.
        if (!empty($this->search)) {
            $data->pageheading = get_string('searchresultsfor', 'local_wub_landing', s($this->search));
        } else if ($this->categoryid > 0) {
            try {
                $cat = core_course_category::get($this->categoryid);
                $data->pageheading = get_string('coursesin', 'local_wub_landing', $cat->get_formatted_name());
            } catch (\Exception $e) {
                $data->pageheading = get_string('allcourses', 'local_wub_landing');
                $this->categoryid = 0;
            }
        } else {
            $data->pageheading = get_string('allcourses', 'local_wub_landing');
        }

        // Search form data.
        $data->searchvalue = s($this->search);
        $data->searchaction = (new moodle_url('/local/wub_landing/catalog.php'))->out(false);

        // Categories for filter dropdown.
        $data->categories = $this->get_categories_for_filter();
        $data->selectedcategoryid = $this->categoryid;

        // Fetch courses.
        $courses = [];
        $totalcount = 0;

        if (!empty($this->search)) {
            // Search mode.
            $searchoptions = [
                'search' => $this->search,
            ];
            $options = [
                'offset' => $this->page * $this->perpage,
                'limit' => $this->perpage,
                'sort' => ['fullname' => 1],
                'coursecontacts' => true,
                'summary' => true,
            ];
            $courses = core_course_category::search_courses($searchoptions, $options);
            $totalcount = core_course_category::search_courses_count($searchoptions);
        } else {
            // Browse mode (by category or all).
            $category = core_course_category::get($this->categoryid);
            $options = [
                'recursive' => true,
                'offset' => $this->page * $this->perpage,
                'limit' => $this->perpage,
                'sort' => ['fullname' => 1],
                'coursecontacts' => true,
                'summary' => true,
            ];
            $courses = $category->get_courses($options);
            $totalcount = $category->get_courses_count(['recursive' => true]);
        }

        // Format courses for template.
        $data->courses = [];
        foreach ($courses as $course) {
            $data->courses[] = $this->format_course_for_template($course, $output);
        }

        $data->hascourses = !empty($data->courses);
        $data->nocoursesfound = get_string('nocoursesfound', 'local_wub_landing');

        // Pagination.
        $data->pagination = $this->get_pagination_data($totalcount);

        // String labels.
        $data->str_searchcourses = get_string('searchcourses', 'local_wub_landing');
        $data->str_allcategories = get_string('allcategories', 'local_wub_landing');
        $data->str_category = get_string('category', 'local_wub_landing');
        $data->str_search = get_string('search', 'local_wub_landing');
        $data->str_clearfilters = get_string('clearfilters', 'local_wub_landing');
        $data->str_coursecatalog = get_string('coursecatalog', 'local_wub_landing');
        $data->str_viewdetails = get_string('viewdetails', 'local_wub_landing');

        // Landing page link.
        $data->landingurl = (new moodle_url('/local/wub_landing/index.php'))->out(false);

        // Active filter state.
        $data->hasactivefilters = !empty($this->search) || $this->categoryid > 0;
        $data->clearfiltersurl = (new moodle_url('/local/wub_landing/catalog.php'))->out(false);

        return $data;
    }

    /**
     * Format a single course for the template.
     *
     * @param core_course_list_element $course The course list element.
     * @param renderer_base $output The renderer.
     * @return stdClass Formatted course data.
     */
    private function format_course_for_template(core_course_list_element $course, renderer_base $output): stdClass {
        $coursedata = new stdClass();
        $coursedata->id = $course->id;
        $coursedata->fullname = $course->get_formatted_fullname();
        $coursedata->shortname = $course->get_formatted_shortname();

        // Category name.
        try {
            $cat = core_course_category::get($course->category, MUST_EXIST, true);
            $coursedata->categoryname = $cat->get_formatted_name();
        } catch (\Exception $e) {
            $coursedata->categoryname = '';
        }

        // Course summary (truncated for cards).
        if ($course->has_summary()) {
            $summary = strip_tags($course->summary);
            if (strlen($summary) > 150) {
                $coursedata->summary = substr($summary, 0, 147) . '...';
            } else {
                $coursedata->summary = $summary;
            }
            $coursedata->hassummary = true;
        } else {
            $coursedata->hassummary = false;
            $coursedata->summary = '';
        }

        // Course image.
        $coursedata->courseimageurl = $this->get_course_image_url($course);
        $coursedata->hascourseimage = !empty($coursedata->courseimageurl);

        // Course contacts (teachers/instructors).
        $contacts = $course->get_course_contacts();
        $coursedata->instructors = [];
        foreach ($contacts as $contact) {
            $coursedata->instructors[] = [
                'name' => $contact['username'],
                'role' => $contact['rolename'],
            ];
        }
        $coursedata->hasinstructors = !empty($coursedata->instructors);

        // Details URL.
        $coursedata->detailsurl = (new moodle_url('/local/wub_landing/course.php', ['id' => $course->id]))->out(false);

        // View details label.
        $coursedata->str_viewdetails = get_string('viewdetails', 'local_wub_landing');

        return $coursedata;
    }

    /**
     * Get the course overview image URL.
     *
     * @param core_course_list_element $course The course.
     * @return string The image URL, or empty string if no image.
     */
    private function get_course_image_url(core_course_list_element $course): string {
        global $CFG;

        foreach ($course->get_course_overviewfiles() as $file) {
            if ($file->is_valid_image()) {
                return moodle_url::make_file_url(
                    "$CFG->wwwroot/pluginfile.php",
                    '/' . $file->get_contextid() . '/' . $file->get_component() . '/' .
                    $file->get_filearea() . $file->get_filepath() . $file->get_filename(),
                    false
                );
            }
        }

        return '';
    }

    /**
     * Get categories formatted for the filter dropdown.
     *
     * @return array Array of category objects with id, name, and selected flag.
     */
    private function get_categories_for_filter(): array {
        $categories = core_course_category::make_categories_list();
        $result = [];

        foreach ($categories as $id => $name) {
            $result[] = [
                'id' => $id,
                'name' => $name,
                'selected' => ($id == $this->categoryid),
            ];
        }

        return $result;
    }

    /**
     * Generate pagination data.
     *
     * @param int $totalcount Total number of courses.
     * @return stdClass Pagination template data.
     */
    private function get_pagination_data(int $totalcount): stdClass {
        $pagination = new stdClass();
        $totalpages = ceil($totalcount / $this->perpage);

        $pagination->haspagination = ($totalpages > 1);
        $pagination->currentpage = $this->page + 1;
        $pagination->totalpages = $totalpages;
        $pagination->totalcourses = $totalcount;

        if (!$pagination->haspagination) {
            return $pagination;
        }

        // Build page URL parameters.
        $baseparams = [];
        if (!empty($this->search)) {
            $baseparams['search'] = $this->search;
        }
        if ($this->categoryid > 0) {
            $baseparams['categoryid'] = $this->categoryid;
        }

        // Previous page.
        $pagination->hasprev = ($this->page > 0);
        if ($pagination->hasprev) {
            $prevparams = $baseparams;
            $prevparams['page'] = $this->page - 1;
            $pagination->prevurl = (new moodle_url('/local/wub_landing/catalog.php', $prevparams))->out(false);
        }

        // Next page.
        $pagination->hasnext = ($this->page < $totalpages - 1);
        if ($pagination->hasnext) {
            $nextparams = $baseparams;
            $nextparams['page'] = $this->page + 1;
            $pagination->nexturl = (new moodle_url('/local/wub_landing/catalog.php', $nextparams))->out(false);
        }

        // Page numbers (show a window around current page).
        $pagination->pages = [];
        $windowsize = 2;
        $startpage = max(0, $this->page - $windowsize);
        $endpage = min($totalpages - 1, $this->page + $windowsize);

        for ($i = $startpage; $i <= $endpage; $i++) {
            $pageparams = $baseparams;
            $pageparams['page'] = $i;
            $pagination->pages[] = [
                'pagenum' => $i + 1,
                'url' => (new moodle_url('/local/wub_landing/catalog.php', $pageparams))->out(false),
                'active' => ($i == $this->page),
            ];
        }

        return $pagination;
    }
}
