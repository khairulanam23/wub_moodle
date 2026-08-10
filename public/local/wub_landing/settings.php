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
 * Admin settings for local_wub_landing.
 *
 * @package    local_wub_landing
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_wub_landing', get_string('pluginname', 'local_wub_landing'));

    // Enable/disable plugin.
    $settings->add(new admin_setting_configcheckbox(
        'local_wub_landing/enabled',
        get_string('settings_enable', 'local_wub_landing'),
        get_string('settings_enable_desc', 'local_wub_landing'),
        1
    ));

    // Landing page title.
    $settings->add(new admin_setting_configtext(
        'local_wub_landing/title',
        get_string('settings_title', 'local_wub_landing'),
        get_string('settings_title_desc', 'local_wub_landing'),
        'Welcome to WUB'
    ));

    // Landing page subtitle.
    $settings->add(new admin_setting_configtext(
        'local_wub_landing/subtitle',
        get_string('settings_subtitle', 'local_wub_landing'),
        get_string('settings_subtitle_desc', 'local_wub_landing'),
        'Click below to access the portal as a student, teacher, or administrative personnel'
    ));

    // Student button enabled.
    $settings->add(new admin_setting_configcheckbox(
        'local_wub_landing/student_enabled',
        get_string('settings_student_enabled', 'local_wub_landing'),
        get_string('settings_student_enabled_desc', 'local_wub_landing'),
        1
    ));

    // Teacher button enabled.
    $settings->add(new admin_setting_configcheckbox(
        'local_wub_landing/teacher_enabled',
        get_string('settings_teacher_enabled', 'local_wub_landing'),
        get_string('settings_teacher_enabled_desc', 'local_wub_landing'),
        1
    ));

    // Administration button enabled.
    $settings->add(new admin_setting_configcheckbox(
        'local_wub_landing/admin_enabled',
        get_string('settings_admin_enabled', 'local_wub_landing'),
        get_string('settings_admin_enabled_desc', 'local_wub_landing'),
        1
    ));

    // Course catalog enabled.
    $settings->add(new admin_setting_configcheckbox(
        'local_wub_landing/catalog_enabled',
        get_string('settings_catalog_enabled', 'local_wub_landing'),
        get_string('settings_catalog_enabled_desc', 'local_wub_landing'),
        1
    ));

    // Contact Us URL.
    $settings->add(new admin_setting_configtext(
        'local_wub_landing/contactus_url',
        get_string('settings_contactus_url', 'local_wub_landing'),
        get_string('settings_contactus_url_desc', 'local_wub_landing'),
        ''
    ));

    // How-to Guides URL.
    $settings->add(new admin_setting_configtext(
        'local_wub_landing/howtoguides_url',
        get_string('settings_howtoguides_url', 'local_wub_landing'),
        get_string('settings_howtoguides_url_desc', 'local_wub_landing'),
        ''
    ));

    // Courses per page.
    $settings->add(new admin_setting_configtext(
        'local_wub_landing/courses_per_page',
        get_string('settings_courses_per_page', 'local_wub_landing'),
        get_string('settings_courses_per_page_desc', 'local_wub_landing'),
        12,
        PARAM_INT
    ));

    // Hero image filename.
    $settings->add(new admin_setting_configtext(
        'local_wub_landing/heroimage',
        get_string('settings_heroimage', 'local_wub_landing'),
        get_string('settings_heroimage_desc', 'local_wub_landing'),
        'wubImage.jpg'
    ));

    $ADMIN->add('localplugins', $settings);
}
