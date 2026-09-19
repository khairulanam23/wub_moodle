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
 * Administration settings for local_bulk_enrolment.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_bulk_enrolment', get_string('pluginname', 'local_bulk_enrolment'));

    // 1. Bulk Enrolment Navigation Links.
    $settings->add(new admin_setting_heading(
        'local_bulk_enrolment_nav_heading',
        get_string('settings_heading_portal', 'local_bulk_enrolment'),
        get_string('settings_heading_portal_desc', 'local_bulk_enrolment')
    ));

    $portalUrl = new moodle_url('/local/bulk_enrolment/index.php', ['tab' => 'enrol']);
    $diagUrl = new moodle_url('/local/bulk_enrolment/index.php', ['tab' => 'ums']);

    $settings->add(new admin_setting_description(
        'local_bulk_enrolment_links',
        get_string('settings_portal_link', 'local_bulk_enrolment'),
        get_string('settings_portal_link_desc', 'local_bulk_enrolment', $portalUrl->out())
        . '<br>' . get_string('test_connection_link_desc', 'local_bulk_enrolment', $diagUrl->out())
    ));

    // 2. UMS API Connection Parameters.
    $settings->add(new admin_setting_heading(
        'local_bulk_enrolment_connection_heading',
        get_string('settings_heading_connection', 'local_bulk_enrolment'),
        get_string('settings_heading_connection_desc', 'local_bulk_enrolment')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_bulk_enrolment/enabled',
        get_string('enabled', 'local_bulk_enrolment'),
        get_string('enabled_desc', 'local_bulk_enrolment'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_bulk_enrolment/base_url',
        get_string('base_url', 'local_bulk_enrolment'),
        get_string('base_url_desc', 'local_bulk_enrolment'),
        'https://api.e-dhrubo.com',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_bulk_enrolment/connect_timeout',
        get_string('connect_timeout', 'local_bulk_enrolment'),
        get_string('connect_timeout_desc', 'local_bulk_enrolment'),
        10,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_bulk_enrolment/timeout',
        get_string('timeout', 'local_bulk_enrolment'),
        get_string('timeout_desc', 'local_bulk_enrolment'),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_bulk_enrolment/ssl_verify',
        get_string('ssl_verify', 'local_bulk_enrolment'),
        get_string('ssl_verify_desc', 'local_bulk_enrolment'),
        1
    ));

    // 3. Authentication Credentials (Protected unmask controls; empty by default).
    $settings->add(new admin_setting_heading(
        'local_bulk_enrolment_auth_heading',
        get_string('settings_heading_auth', 'local_bulk_enrolment'),
        get_string('settings_heading_auth_desc', 'local_bulk_enrolment')
    ));

    $settings->add(new admin_setting_configtext(
        'local_bulk_enrolment/api_username',
        get_string('api_username', 'local_bulk_enrolment'),
        get_string('api_username_desc', 'local_bulk_enrolment'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_bulk_enrolment/api_password',
        get_string('api_password', 'local_bulk_enrolment'),
        get_string('api_password_desc', 'local_bulk_enrolment'),
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_bulk_enrolment/api_key',
        get_string('api_key', 'local_bulk_enrolment'),
        get_string('api_key_desc', 'local_bulk_enrolment'),
        ''
    ));

    $ADMIN->add('localplugins', $settings);
}
