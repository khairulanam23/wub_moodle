<?php
// This file is part of Moodle - https://moodle.org/
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
 * Adds admin settings for local_mass_enroll.
 *
 * @package    local_mass_enroll
 * @copyright  2021 World University of Bangladesh (CIS)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('localsettingmassenroll', get_string('pluginname', 'local_mass_enroll'));

    $settings->add(new admin_setting_heading(
        'local_mass_enroll_api',
        get_string('bulk_enrollment', 'local_mass_enroll'),
        get_string('bulk_enrollment_desc', 'local_mass_enroll')
    ));

    $settings->add(new admin_setting_configtext(
        'local_mass_enroll/api_url',
        get_string('api_url', 'local_mass_enroll'),
        get_string('api_url_help', 'local_mass_enroll'),
        'https://api.e-dhrubo.com/students/details'
    ));

    $settings->add(new admin_setting_configtext(
        'local_mass_enroll/api_username',
        get_string('api_username', 'local_mass_enroll'),
        get_string('api_username_help', 'local_mass_enroll'),
        'admin'
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_mass_enroll/api_password',
        get_string('api_password', 'local_mass_enroll'),
        get_string('api_password_help', 'local_mass_enroll'),
        '1234'
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_mass_enroll/api_x_api_key',
        get_string('api_x_api_key', 'local_mass_enroll'),
        get_string('api_x_api_key_help', 'local_mass_enroll'),
        '1234567890'
    ));

    $settings->add(new admin_setting_heading(
        'local_mass_enroll_others_api',
        get_string('other_api_info', 'local_mass_enroll'),
        get_string('other_api_info_desc', 'local_mass_enroll')
    ));

    $settings->add(new admin_setting_configtext(
        'local_mass_enroll/api_url_programs',
        get_string('api_url_programs', 'local_mass_enroll'),
        get_string('api_url_programs_help', 'local_mass_enroll'),
        'https://api.e-dhrubo.com/students/programs'
    ));

    $settings->add(new admin_setting_configtext(
        'local_mass_enroll/api_url_batch',
        get_string('api_url_batch', 'local_mass_enroll'),
        get_string('api_url_batch_help', 'local_mass_enroll'),
        'https://api.e-dhrubo.com/students/batches/'
    ));

    $settings->add(new admin_setting_configtext(
        'local_mass_enroll/api_ums_sync',
        get_string('api_ums_sync', 'local_mass_enroll'),
        get_string('api_ums_sync_help', 'local_mass_enroll'),
        'https://api.e-dhrubo.com/students/details'
    ));

    $settings->add(new admin_setting_configtext(
        'local_mass_enroll/api_ums_courses',
        get_string('api_ums_courses', 'local_mass_enroll'),
        get_string('api_ums_courses_help', 'local_mass_enroll'),
        'https://api.e-dhrubo.com/students/enroll_student_list_program_batch_wise'
    ));

    $settings->add(new admin_setting_configtext(
        'local_mass_enroll/api_student_payment_info',
        get_string('api_student_payment_info', 'local_mass_enroll'),
        get_string('api_student_payment_info_help', 'local_mass_enroll'),
        'https://api.e-dhrubo.com/students/student_payment_info/'
    ));

    $ADMIN->add('localplugins', $settings);

    $ADMIN->add('courses', new admin_externalpage(
        'local_mass_enroll_page',
        get_string('pluginname', 'local_mass_enroll'),
        new moodle_url('/local/mass_enroll/enrolled.php')
    ));
}