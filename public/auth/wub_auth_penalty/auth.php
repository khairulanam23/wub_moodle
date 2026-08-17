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
 * WUB Auth Penalty Authentication Plugin.
 *
 * Moodle core authentication plugin wrapper. Delegates modular API calls,
 * due calculations, and permission checks to local_wub_auth_penalty services.
 *
 * @package    auth_wub_auth_penalty
 * @copyright  2021 World University of Bangladesh (CIS)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/authlib.php');
if (file_exists($CFG->dirroot . '/local/wub_auth_penalty/lib.php')) {
    require_once($CFG->dirroot . '/local/wub_auth_penalty/lib.php');
}

class auth_plugin_wub_auth_penalty extends auth_plugin_base {

    /**
     * Authenticated student Moodle user record.
     * @var stdClass|null
     */
    private $student;

    /**
     * Constructor initializing auth plugin type and configuration.
     */
    public function __construct() {
        $this->authtype = "wub_auth_penalty";
        $this->config = get_config('auth_wub_auth_penalty');
    }

    /**
     * Intercept login page submission to check user authentication and financial due status.
     *
     * @return bool|void
     */
    public function loginpage_hook() {
        global $frm, $SESSION;

        if (empty($frm)) {
            $frm = data_submitted();
        }
        if (empty($frm) || empty($frm->username)) {
            return true;
        }

        $username = trim($frm->username);
        $password = $frm->password ?? '';

        if ($this->user_login($username, $password) == false) {
            redirect('/login/index.php?msg=0');
        }

        if ($this->student && !empty($this->student->id)) {
            $status = wub_auth_penalty_check_student_due_status((int)$this->student->id);
            if (!empty($status) && isset($status['allowed']) && $status['allowed'] === false) {
                $SESSION->loginerrormsg = get_string('login_due_restriction_message', 'auth_wub_auth_penalty');
                redirect(new moodle_url('/login/index.php', ['msg' => 1]));
            }
        }
    }

    /**
     * Authenticate student user credentials against Moodle database or Student Portal API.
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function user_login($username, $password) {
        $user = wub_auth_penalty_user_login($username, $password);
        if (!$user) {
            return false;
        }

        $this->student = $user;
        return true;
    }
}
