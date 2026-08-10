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
 * Renderable class for WUB Policy page.
 *
 * @package    local_wub_policy
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_wub_policy\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use moodle_url;
use stdClass;

/**
 * Policy page renderable class.
 */
class policy_page implements renderable, templatable {

    /** @var string Target role (student, teacher, admin). */
    private string $role;

    /** @var string|null Error message if any. */
    private ?string $error;

    /** @var string Return URL after acceptance. */
    private string $returnurl;

    /**
     * Constructor.
     *
     * @param string $role Role name
     * @param string|null $error Error message
     * @param string $returnurl Return URL
     */
    public function __construct(string $role, ?string $error = null, string $returnurl = '') {
        $this->role = $role;
        $this->error = $error;
        $this->returnurl = $returnurl;
    }

    /**
     * Export data for Mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Data for Mustache.
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();

        // Image URL.
        $data->heroimageurl = (new moodle_url('/local/wub_landing/pix/wubImage.jpg'))->out(false);

        // Role & Action URLs.
        $data->role = s($this->role);
        $data->returnurl = s($this->returnurl);
        $data->formaction = (new moodle_url('/local/wub_policy/index.php'))->out(false);
        $data->sesskey = sesskey();

        // Error message.
        $data->haserror = !empty($this->error);
        $data->errormessage = $this->error;

        // Role title & policy HTML content.
        $policykey = 'policy' . $this->role;
        $rolekey = 'role_' . $this->role;

        $data->roletitle = get_string($rolekey, 'local_wub_policy');
        $data->policyhtml = get_string($policykey, 'local_wub_policy');

        // Cancel / Landing URL.
        $data->cancelurl = (new moodle_url('/local/wub_landing/index.php'))->out(false);

        // Language Strings.
        $data->str_policyheader = get_string('policyheader', 'local_wub_policy');
        $data->str_policysubtitle = get_string('policysubtitle', 'local_wub_policy');
        $data->str_agreecheckbox = get_string('agreecheckbox', 'local_wub_policy');
        $data->str_continuebtn = get_string('continuebtn', 'local_wub_policy');
        $data->str_cancelbtn = get_string('cancelbtn', 'local_wub_policy');

        return $data;
    }
}
