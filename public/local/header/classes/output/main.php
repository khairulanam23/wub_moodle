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
 * Renderable class for the WUB portal header.
 *
 * Mirrors the two-tier header on https://wub.edu.bd/ : a dark utility bar
 * (contact number + quick links) above a white sticky main bar (logo, portal
 * navigation, call-to-action).
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

    /** @var string Base URL of the public WUB website. */
    protected const WUB_SITE = 'https://wub.edu.bd';

    /**
     * Export data for Mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Data for Mustache.
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $PAGE;

        $data = new stdClass();

        $isauthenticated = isloggedin() && !isguestuser();
        $data->isauthenticated = $isauthenticated;

        // Utility bar: contact number and the quick links carried over from
        // the public site's header-top-bar.
        $data->phonelabel = get_string('phonelabel', 'local_header');
        $data->phone = get_string('phone', 'local_header');
        $data->phoneurl = 'tel:' . preg_replace('/[^0-9+]/', '', get_string('phone', 'local_header'));
        $data->email = get_string('email', 'local_header');
        $data->quicklinks = [
            ['text' => get_string('faculty', 'local_header'), 'url' => self::WUB_SITE . '/main/wub_faculty'],
            ['text' => get_string('career', 'local_header'), 'url' => 'https://jobs.wub.edu.bd/'],
            ['text' => get_string('studentsupport', 'local_header'), 'url' => self::WUB_SITE . '/main/student_support'],
            ['text' => get_string('alumni', 'local_header'), 'url' => self::WUB_SITE . '/alumni/alumni_registration_form'],
        ];

        // Branding. The asset lives in this plugin so local_header no longer
        // depends on local_wub_landing being installed.
        $data->logourl = (new moodle_url('/local/header/pix/wub-logo.png'))->out(false);
        $data->sitename = get_string('brandname', 'local_header');
        $data->logolinkurl = $isauthenticated
            ? (new moodle_url('/'))->out(false)
            : (new moodle_url('/local/wub_landing/index.php'))->out(false);

        // Portal navigation and call to action. Off by default -- the public
        // portal pages carry the logo only, with nothing on the right of the bar.
        $data->shownav = (bool) get_config('local_header', 'shownav');
        if (!$data->shownav) {
            $data->navitems = [];
            $data->hassecondary = false;
            $data->currenturl = '';
            return $data;
        }

        $data->navitems = $this->build_nav($isauthenticated);

        // Call to action.
        if ($isauthenticated) {
            $data->ctatext = get_string('dashboard', 'local_header');
            $data->ctaurl = (new moodle_url('/my/'))->out(false);
            $data->secondarytext = get_string('logout', 'local_header');
            $data->secondaryurl = (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false);
        } else {
            $data->ctatext = get_string('signin', 'local_header');
            $data->ctaurl = (new moodle_url('/local/wub_login/index.php'))->out(false);
        }
        $data->hassecondary = !empty($data->secondaryurl);

        $data->currenturl = $PAGE->url ? $PAGE->url->out_omit_querystring() : '';

        return $data;
    }

    /**
     * Build the portal navigation, honouring the local_wub_landing toggles.
     *
     * @param bool $isauthenticated Whether the current user is a real logged in user.
     * @return array List of nav items for the template.
     */
    protected function build_nav(bool $isauthenticated): array {
        global $PAGE;

        $items = [];

        $items[] = [
            'text' => get_string('home', 'local_header'),
            'url' => $isauthenticated
                ? (new moodle_url('/my/'))->out(false)
                : (new moodle_url('/local/wub_landing/index.php'))->out(false),
        ];

        // The course catalog is optional; local_wub_landing owns that switch.
        if (get_config('local_wub_landing', 'catalog_enabled')) {
            $items[] = [
                'text' => get_string('coursecatalog', 'local_header'),
                'url' => (new moodle_url('/local/wub_landing/catalog.php'))->out(false),
            ];
        }

        // These two are optional URLs configured on local_wub_landing. Only
        // render them when an admin has actually set a destination.
        $howto = get_config('local_wub_landing', 'howtoguides_url');
        if (!empty($howto)) {
            $items[] = ['text' => get_string('howtoguides', 'local_header'), 'url' => $howto];
        }

        $contact = get_config('local_wub_landing', 'contactus_url');
        $items[] = [
            'text' => get_string('contactus', 'local_header'),
            'url' => !empty($contact) ? $contact : self::WUB_SITE . '/contact',
        ];

        // Mark the active item so the header reflects where the user is.
        $current = $PAGE->url ? $PAGE->url->out_omit_querystring() : '';
        foreach ($items as $i => $item) {
            $items[$i]['isactive'] = ($current !== '' && strpos($item['url'], $current) !== false);
        }

        return $items;
    }
}
