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
 * Admin settings for local_wub_ums plugin.
 *
 * @package    local_wub_ums
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('localsettingwubums', get_string('pluginname', 'local_wub_ums'));

    $settings->add(new admin_setting_heading(
        'local_wub_ums_heading',
        get_string('ums_settings', 'local_wub_ums'),
        get_string('ums_settings_desc', 'local_wub_ums')
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_ums/api_url',
        get_string('api_url', 'local_wub_ums'),
        get_string('api_url_help', 'local_wub_ums'),
        'https://api.e-dhrubo.com/students/details'
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_ums/api_username',
        get_string('api_username', 'local_wub_ums'),
        get_string('api_username_help', 'local_wub_ums'),
        'admin'
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_wub_ums/api_password',
        get_string('api_password', 'local_wub_ums'),
        get_string('api_password_help', 'local_wub_ums'),
        '1234'
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_wub_ums/api_x_api_key',
        get_string('api_x_api_key', 'local_wub_ums'),
        get_string('api_x_api_key_help', 'local_wub_ums'),
        '1234567890'
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_ums/api_url_programs',
        get_string('api_url_programs', 'local_wub_ums'),
        '',
        'https://api.e-dhrubo.com/students/programs'
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_ums/api_url_batch',
        get_string('api_url_batch', 'local_wub_ums'),
        '',
        'https://api.e-dhrubo.com/students/batches/'
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_ums/api_ums_courses',
        get_string('api_ums_courses', 'local_wub_ums'),
        '',
        'https://api.e-dhrubo.com/students/enroll_student_list_program_batch_wise'
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_ums/api_student_payment_info',
        get_string('api_student_payment_info', 'local_wub_ums'),
        '',
        'https://api.e-dhrubo.com/students/student_payment_info/'
    ));

    $ADMIN->add('localplugins', $settings);
}
