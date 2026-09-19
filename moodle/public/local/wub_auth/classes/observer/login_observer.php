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

namespace local_wub_auth\observer;

use local_wub_auth\service\audit_service;
use local_wub_auth\service\policy_service;

defined('MOODLE_INTERNAL') || die();

/**
 * Event Observers for Core Moodle Login/Logout Events.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class login_observer {
    /**
     * Triggered on \core\event\user_loggedin.
     *
     * @param \core\event\user_loggedin $event
     */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        $userid = (int)$event->userid;
        if ($userid > 0 && !isguestuser($userid)) {
            $policyservice = new policy_service();
            $policyservice->bind_device_acceptance($userid);
        }
    }

    /**
     * Triggered on \core\event\user_loggedout.
     *
     * @param \core\event\user_loggedout $event
     */
    public static function user_loggedout(\core\event\user_loggedout $event): void {
        global $SESSION;
        unset($SESSION->wub_intended_role);
        unset($SESSION->wub_return_url);
    }

    /**
     * Triggered on \core\event\user_login_failed.
     *
     * @param \core\event\user_login_failed $event
     */
    public static function user_login_failed(\core\event\user_login_failed $event): void {
        $data = $event->get_data();
        $username = (string)($data['other']['username'] ?? '');

        $audit = new audit_service();
        $audit->log(
            0,
            'LOGIN_FAILED',
            'INVALID_CREDENTIALS',
            ['reason' => $data['other']['reason'] ?? 'core_failed'],
            $username
        );
    }
}
