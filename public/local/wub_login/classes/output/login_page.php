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
 * Renderable class for WUB Custom Login page.
 *
 * @package    local_wub_login
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_wub_login\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use moodle_url;
use stdClass;

/**
 * Login page renderable class.
 */
class login_page implements renderable, templatable {

    /** @var string|null Selected role badge text. */
    private ?string $role;

    /** @var string|null Error message to display. */
    private ?string $error;

    /** @var string Pre-filled username. */
    private string $username;

    /**
     * Constructor.
     *
     * @param string|null $role Role selection (student, teacher, admin)
     * @param string|null $error Error message string
     * @param string $username Username string
     */
    public function __construct(?string $role = null, ?string $error = null, string $username = '') {
        $this->role = $role;
        $this->error = $error;
        $this->username = $username;
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

        // Form post target.
        $data->loginaction = (new moodle_url('/local/wub_login/index.php'))->out(false);

        // Login token for CSRF protection.
        $data->logintoken = \core\session\manager::get_login_token();

        // Error message.
        $data->haserror = !empty($this->error);
        $data->errormessage = $this->error;

        // Role badge.
        $data->hasrole = !empty($this->role);
        if ($data->hasrole) {
            $rolename = get_string($this->role, 'local_wub_login');
            $data->rolebadge = get_string('logginginas', 'local_wub_login', $rolename);
            $data->roleclass = 'wub-role-badge-' . $this->role;
        }

        // Form fields.
        $data->username = s($this->username);
        $data->forgotpasswordurl = (new moodle_url('/login/forgot_password.php'))->out(false);
        $data->landingurl = (new moodle_url('/local/wub_landing/index.php'))->out(false);

        // Strings.
        $data->str_loginheader = get_string('loginheader', 'local_wub_login');
        $data->str_loginsubtitle = get_string('loginsubtitle', 'local_wub_login');
        $data->str_username = get_string('username', 'local_wub_login');
        $data->str_username_placeholder = get_string('username_placeholder', 'local_wub_login');
        $data->str_password = get_string('password', 'local_wub_login');
        $data->str_password_placeholder = get_string('password_placeholder', 'local_wub_login');
        $data->str_rememberusername = get_string('rememberusername', 'local_wub_login');
        $data->str_forgotpassword = get_string('forgotpassword', 'local_wub_login');
        $data->str_loginbtn = get_string('loginbtn', 'local_wub_login');
        $data->str_backtolanding = get_string('backtolanding', 'local_wub_login');

        return $data;
    }
}
