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
    $PAGE->set_url(new moodle_url('/local/mass_enroll/sync.php'));
    $PAGE->set_context($context);

    if (!is_siteadmin() && !has_capability('local/mass_enroll:config', $context) && !has_capability('moodle/user:create', $context)) {
        redirect(new moodle_url('/'));
        exit();
    }

    global $DB;
    $enrol_helper = new enrolhelper();
    $PAGE->set_title("Synchronization Data");
    $PAGE->set_pagelayout('standard');
    $PAGE->navbar->add(get_string("enrolled_sync","local_mass_enroll"),"/local/mass_enroll/sync.php");
    $PAGE->set_heading('');

    $PAGE->requires->jquery();

    $PAGE->requires->css(new moodle_url($CFG->wwwroot . '/local/mass_enroll/css/bulk_enrollment.css'), true);
    $PAGE->requires->css(new moodle_url($CFG->wwwroot . '/local/mass_enroll/css/ladda.min.css'), true);
    $PAGE->requires->css(new moodle_url($CFG->wwwroot . '/local/mass_enroll/css/sweetalert2.min.css'), true);
    $PAGE->requires->css(new moodle_url($CFG->wwwroot . '/local/mass_enroll/css/select2.min.css'), true);

    $PAGE->requires->js(new moodle_url($CFG->wwwroot . '/local/mass_enroll/js/select2.min.js'), true);
    $PAGE->requires->js(new moodle_url($CFG->wwwroot . '/local/mass_enroll/js/spin.min.js'), true);
    $PAGE->requires->js(new moodle_url($CFG->wwwroot . '/local/mass_enroll/js/ladda.min.js'), true);
    $PAGE->requires->js(new moodle_url($CFG->wwwroot . '/local/mass_enroll/js/sweetalert2.min.js'), true);

    $PAGE->requires->js_call_amd('local_mass_enroll/sync_view', 'init');

    $programs = $enrol_helper->get_all_programs();
    $context_data = (object)[
        "programs" => $programs,
        "sesskey" => sesskey(),
    ];

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_mass_enroll/sync', $context_data);
    echo $OUTPUT->footer();