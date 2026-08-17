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
     * Allows course enrolment via a simple text code.
     *
     * @package   local_mass_enroll
     * @copyright 2021 World University of Bangladesh (CIS)
     * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */

    define('AJAX_SCRIPT', true);
    require_once(dirname(__FILE__) . '/../../config.php');
    require_once($CFG->dirroot . '/local/mass_enroll/classes/enrolhelper.php');

    require_login();
    $context = \context_system::instance();
    if (!is_siteadmin() && !has_capability('local/mass_enroll:config', $context) && !has_capability('local/mass_enroll:enrol', $context) && !has_capability('moodle/user:create', $context)) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied']);
        exit();
    }

    $PAGE->set_url(new moodle_url('/local/mass_enroll/api.php'));

    // CSRF protection: validate sesskey on POST requests if sesskey is provided.
    $sesskey = optional_param('sesskey', '', PARAM_RAW);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($sesskey)) {
        confirm_sesskey($sesskey);
    }

    $enrol_helper = new enrolhelper();
    $output = [];

    if (isset($_POST['category_id']) && !empty($_POST['category_id']) && ($_SERVER['REQUEST_METHOD'] == 'POST')){
        $output = $enrol_helper->get_courses($_POST);
    }

    if (isset($_POST['program_id']) && !empty($_POST['program_id']) && ($_SERVER['REQUEST_METHOD'] == 'POST')){
        $output = $enrol_helper->get_batches($_POST['program_id']);
    }

    if (isset($_POST['batch']) && !empty($_POST['program']) && ($_SERVER['REQUEST_METHOD'] == 'POST')){
        $output = $enrol_helper->get_students($_POST);
    }

    if (isset($_POST['action']) && $_POST['action'] === 'create_selected_users' && ($_SERVER['REQUEST_METHOD'] == 'POST')) {
        $output = $enrol_helper->create_selected_ums_users($_POST);
    } else if (isset($_POST['sync_program']) && !empty($_POST['sync_program']) && ($_SERVER['REQUEST_METHOD'] == 'POST')) {
        $output = $enrol_helper->get_ums_students_comparison($_POST);
    } else if (isset($_POST['user']) && !empty($_POST['user']) && ($_SERVER['REQUEST_METHOD'] == 'POST')) {
        if (isset($_POST['action']) && $_POST['action'] === 'sync') {
            $output = $enrol_helper->get_synchronization_data($_POST);
        } else {
            $output = $enrol_helper->create_selected_ums_users($_POST);
        }
    }

    if (isset($_POST['record_program_id']) && !empty($_POST['record_program_id']) && ($_SERVER['REQUEST_METHOD'] == 'POST')){
        $output = $enrol_helper->get_student_records($_POST);
    }

    echo json_encode($output);
