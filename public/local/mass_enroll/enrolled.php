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

    require_once(dirname(__FILE__) . '/../../config.php');
    require_once($CFG->dirroot . '/local/mass_enroll/lib.php');
    require_once($CFG->dirroot . '/local/mass_enroll/classes/enrolhelper.php');

    require_login();
    $context = \context_system::instance();
    $PAGE->set_url(new moodle_url('/local/mass_enroll/enrolled.php'));
    $PAGE->set_context($context);

    if (!is_siteadmin() && !has_capability('local/mass_enroll:config', $context) && !has_capability('moodle/user:create', $context)) {
        redirect(new moodle_url('/'));
        exit();
    }

    global $DB;
    $enrol_helper = new enrolhelper();
    $PAGE->set_title("Bulk Enrollment");
    $PAGE->set_pagelayout('standard');
    $PAGE->requires->jquery();

    $PAGE->requires->css(new moodle_url($CFG->wwwroot.'/local/mass_enroll/css/bulk_enrollment.css'),true);
    $PAGE->requires->css(new moodle_url($CFG->wwwroot.'/local/mass_enroll/css/ladda.min.css'),true);
    $PAGE->requires->css(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/10.16.3/sweetalert2.min.css'),TRUE);
    $PAGE->requires->css(new moodle_url('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css'),TRUE);

    $PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js'),true);
    $PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/list.js/1.5.0/list.min.js'),true);
    $PAGE->requires->js(new moodle_url($CFG->wwwroot.'/local/mass_enroll/js/spin.min.js'),true);
    $PAGE->requires->js(new moodle_url($CFG->wwwroot.'/local/mass_enroll/js/ladda.min.js'),true);
    $PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/10.16.3/sweetalert2.min.js'),true);


    $PAGE->navbar->add(get_string("enrolled_navbar","local_mass_enroll"), "/local/mass_enroll/enrolled.php");
    $PAGE->set_heading('');

    $courses_category = $enrol_helper->convert_arr($DB->get_records('course_categories'));
    $programs = $enrol_helper->get_all_programs();
    $_SESSION['programs'] = $programs;

    $context_data= (object)[
        "courses_category" => $courses_category,
        "programs" => $programs,
    ];

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_mass_enroll/enrolled', $context_data);
    echo $OUTPUT->footer();