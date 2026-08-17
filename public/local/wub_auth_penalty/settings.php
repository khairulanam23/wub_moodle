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
 * Admin settings configuration for local_wub_auth_penalty.
 *
 * @package    local_wub_auth_penalty
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new admin_settingpage(
        'local_wub_auth_penalty',
        get_string('pluginname', 'local_wub_auth_penalty')
    );

    // UMS API Base URL
    $settings->add(new admin_setting_configtext(
        'local_wub_auth_penalty/api_url',
        get_string('setting_api_url', 'local_wub_auth_penalty'),
        get_string('setting_api_url_desc', 'local_wub_auth_penalty'),
        'https://api.e-dhrubo.com/',
        PARAM_URL
    ));

    // API Digest/Basic Username
    $settings->add(new admin_setting_configtext(
        'local_wub_auth_penalty/api_username',
        get_string('setting_api_username', 'local_wub_auth_penalty'),
        get_string('setting_api_username_desc', 'local_wub_auth_penalty'),
        '',
        PARAM_RAW
    ));

    // API Digest/Basic Password
    $settings->add(new admin_setting_configpasswordunmask(
        'local_wub_auth_penalty/api_password',
        get_string('setting_api_password', 'local_wub_auth_penalty'),
        get_string('setting_api_password_desc', 'local_wub_auth_penalty'),
        ''
    ));

    // X-API-KEY
    $settings->add(new admin_setting_configpasswordunmask(
        'local_wub_auth_penalty/api_x_api_key',
        get_string('setting_api_x_api_key', 'local_wub_auth_penalty'),
        get_string('setting_api_x_api_key_desc', 'local_wub_auth_penalty'),
        ''
    ));

    // Allowable Due Threshold
    $settings->add(new admin_setting_configtext(
        'local_wub_auth_penalty/due_threshold',
        get_string('setting_due_threshold', 'local_wub_auth_penalty'),
        get_string('setting_due_threshold_desc', 'local_wub_auth_penalty'),
        '100.00',
        PARAM_FLOAT
    ));

    $ADMIN->add('localplugins', $settings);
}
