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
 * Renderable class for the WUB landing page.
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

/**
 * Landing page renderable.
 *
 * Exports data for the landing page Mustache template. Handles both
 * guest (unauthenticated) and authenticated states.
 */
class landing_page implements renderable, templatable {

    /** @var bool Whether the current user is authenticated. */
    private bool $isauthenticated;

    /** @var stdClass|null The current user object, or null for guests. */
    private ?stdClass $user;

    /**
     * Constructor.
     *
     * @param bool $isauthenticated Whether the user is logged in.
     * @param stdClass|null $user The current user object.
     */
    public function __construct(bool $isauthenticated, ?stdClass $user = null) {
        $this->isauthenticated = $isauthenticated;
        $this->user = $user;
    }

    /**
     * Export data for Mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Template data.
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();

        // Plugin settings.
        $title = get_config('local_wub_landing', 'title') ?: get_string('welcometitle', 'local_wub_landing');
        $subtitle = get_config('local_wub_landing', 'subtitle') ?: get_string('welcomesubtitle', 'local_wub_landing');

        $data->title = $title;
        $data->subtitle = $subtitle;
        $data->welcometitle = $title;
        $data->welcomesubtitle = $subtitle;

        // Hero image URL.
        $heroimage = get_config('local_wub_landing', 'heroimage');
        $heroimagefile = !empty($heroimage) ? $heroimage : 'wubImage.jpg';
        $data->heroimageurl = (new moodle_url('/local/wub_landing/pix/' . $heroimagefile))->out(false);

        // Authentication state.
        $data->isauthenticated = $this->isauthenticated;

        if ($this->isauthenticated && $this->user) {
            $data->userfullname = fullname($this->user);
            $data->dashboardurl = (new moodle_url('/my/'))->out(false);
            $data->logouturl = (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false);
            $data->loggedinas = get_string('loggedinas', 'local_wub_landing', fullname($this->user));
            $data->browsecoursesbelow = get_string('browsecoursesbelow', 'local_wub_landing');
        }

        // Role entry points (only for guest view, but also show for authenticated).
        $studentenabled = get_config('local_wub_landing', 'student_enabled');
        $teacherenabled = get_config('local_wub_landing', 'teacher_enabled');
        $adminenabled = get_config('local_wub_landing', 'admin_enabled');

        $data->studentenabled = ($studentenabled === false || $studentenabled);
        $data->teacherenabled = ($teacherenabled === false || $teacherenabled);
        $data->adminenabled = ($adminenabled === false || $adminenabled);

        if ($data->studentenabled) {
            $data->studenturl = (new moodle_url('/local/wub_landing/auth.php', ['role' => 'student']))->out(false);
        }
        if ($data->teacherenabled) {
            $data->teacherurl = (new moodle_url('/local/wub_landing/auth.php', ['role' => 'teacher']))->out(false);
        }
        if ($data->adminenabled) {
            $data->adminurl = (new moodle_url('/local/wub_landing/auth.php', ['role' => 'admin']))->out(false);
        }

        // Course catalog.
        $catalogenabled = get_config('local_wub_landing', 'catalog_enabled');
        $data->catalogenabled = ($catalogenabled === false || $catalogenabled);
        if ($data->catalogenabled) {
            $data->catalogurl = (new moodle_url('/local/wub_landing/catalog.php'))->out(false);
        }

        // Footer links.
        $contacturl = get_config('local_wub_landing', 'contactus_url');
        $howtourl = get_config('local_wub_landing', 'howtoguides_url');
        $data->hascontactus = !empty($contacturl);
        $data->contactusurl = $contacturl ?: '#';
        $data->hashowtoguides = !empty($howtourl);
        $data->howtoguidesurl = $howtourl ?: '#';
        $data->hasfooterlinks = $data->hascontactus || $data->hashowtoguides;

        // String labels.
        $data->str_student = get_string('student', 'local_wub_landing');
        $data->str_teacher = get_string('teacher', 'local_wub_landing');
        $data->str_administration = get_string('administration', 'local_wub_landing');
        $data->str_coursecatalog = get_string('coursecatalog', 'local_wub_landing');
        $data->str_coursecataloginfo = get_string('coursecataloginfo', 'local_wub_landing');
        $data->str_catalogprompt = get_string('catalogprompt', 'local_wub_landing');
        $data->str_contactus = get_string('contactus', 'local_wub_landing');
        $data->str_howtoguides = get_string('howtoguides', 'local_wub_landing');
        $data->str_gotodashboard = get_string('gotodashboard', 'local_wub_landing');
        $data->str_logout = get_string('logout', 'local_wub_landing');

        return $data;
    }
}
