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

namespace local_mass_enroll;

use core\hook\output\before_http_headers;
use core\hook\output\before_standard_head_html_generation;
use moodle_url;

/**
 * Hook callbacks for local_mass_enroll.
 *
 * @package    local_mass_enroll
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Intercept page access for restricted students before HTTP headers are sent.
     *
     * This callback intentionally executes during the core_renderer::header() lifecycle phase
     * BEFORE any HTTP headers or HTML output body have been sent to the browser.
     * This guarantees that session state checks ($SESSION), require_logout(), and redirect()
     * execute cleanly without Mustache template renderer exceptions or uninitialized variable errors.
     *
     * @param before_http_headers $hook
     */
    public static function before_http_headers(before_http_headers $hook): void {
        global $PAGE, $USER, $CFG, $SESSION;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        if ($PAGE && $PAGE->has_set_url()) {
            $path = $PAGE->url->get_path();

            // 1. Intercept Moodle Dashboard access (/my/, /my/index.php, pagetype my-index) for students with dues > 100 BDT
            $is_dashboard = (strpos($path, '/my/') !== false || strpos($path, '/my/index.php') !== false || $path === '/my' || ($PAGE->pagetype ?? '') === 'my-index');
            if ($is_dashboard) {
                if (strpos($path, '/local/mass_enroll/payment_notice.php') === false) {
                    require_once($CFG->dirroot . '/local/mass_enroll/classes/enrolhelper.php');
                    $helper = new \enrolhelper();
                    $check = $helper->check_student_due_status((int)$USER->id);
                    if (!empty($check) && isset($check['allowed']) && $check['allowed'] === false) {
                        require_logout();
                        if (isset($SESSION)) {
                            $SESSION->loginerrormsg = 'Please complete the due payment to log in.';
                        }
                        redirect(new moodle_url('/login/index.php', ['msg' => 1]));
                    }
                }
            }

            // 2. Intercept course enrolment attempts on /enrol/index.php
            if (strpos($path, '/enrol/index.php') !== false) {
                require_once($CFG->dirroot . '/local/mass_enroll/classes/enrolhelper.php');
                $helper = new \enrolhelper();
                $check = $helper->check_student_due_status((int)$USER->id);
                if (!empty($check) && isset($check['allowed']) && $check['allowed'] === false) {
                    require_logout();
                    if (isset($SESSION)) {
                        $SESSION->loginerrormsg = 'Please complete the due payment to log in.';
                    }
                    redirect(new moodle_url('/login/index.php', ['msg' => 1]));
                }
            }
        }
    }

    /**
     * Backward-compatibility wrapper for legacy hook invocations.
     *
     * Delegates safely to before_http_headers logic to prevent null object assignments
     * if called by legacy callers.
     *
     * @param before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(
        before_standard_head_html_generation $hook,
    ): void {
        // Delegate safely without triggering head generation renderer exceptions
        global $PAGE, $USER, $CFG, $SESSION;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        if ($PAGE && $PAGE->has_set_url()) {
            $path = $PAGE->url->get_path();

            $is_dashboard = (strpos($path, '/my/') !== false || strpos($path, '/my/index.php') !== false || $path === '/my' || ($PAGE->pagetype ?? '') === 'my-index');
            if ($is_dashboard || strpos($path, '/enrol/index.php') !== false) {
                if (strpos($path, '/local/mass_enroll/payment_notice.php') === false) {
                    require_once($CFG->dirroot . '/local/mass_enroll/classes/enrolhelper.php');
                    $helper = new \enrolhelper();
                    $check = $helper->check_student_due_status((int)$USER->id);
                    if (!empty($check) && isset($check['allowed']) && $check['allowed'] === false) {
                        require_logout();
                        if (isset($SESSION)) {
                            $SESSION->loginerrormsg = 'Please complete the due payment to log in.';
                        }
                        redirect(new moodle_url('/login/index.php'));
                    }
                }
            }
        }
    }
}
