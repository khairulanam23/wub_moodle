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
 * Admin settings for auth_wub_auth_penalty plugin.
 *
 * @package    auth_wub_auth_penalty
 * @copyright  2021 World University of Bangladesh (CIS)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // Base API URL setting
    $settings->add(new admin_setting_configtext(
        'auth_wub_auth_penalty/base_url',
        get_string('base_url', 'auth_wub_auth_penalty'),
        get_string('base_url_desc', 'auth_wub_auth_penalty'),
        'https://api.e-dhrubo.com/',
        PARAM_URL
    ));

    // API Username
    $settings->add(new admin_setting_configtext(
        'auth_wub_auth_penalty/api_username',
        get_string('api_username', 'auth_wub_auth_penalty'),
        get_string('api_username_desc', 'auth_wub_auth_penalty'),
        '',
        PARAM_RAW
    ));

    // API Password
    $settings->add(new admin_setting_configpasswordunmask(
        'auth_wub_auth_penalty/api_password',
        get_string('api_password', 'auth_wub_auth_penalty'),
        get_string('api_password_desc', 'auth_wub_auth_penalty'),
        ''
    ));

    // API X-API-KEY Token
    $settings->add(new admin_setting_configpasswordunmask(
        'auth_wub_auth_penalty/api_x_api_key',
        get_string('api_x_api_key', 'auth_wub_auth_penalty'),
        get_string('api_x_api_key_desc', 'auth_wub_auth_penalty'),
        ''
    ));

    // Minimum Due Threshold
    $settings->add(new admin_setting_configtext(
        'auth_wub_auth_penalty/minimum_due',
        get_string('minimum_due', 'auth_wub_auth_penalty'),
        get_string('minimum_due_desc', 'auth_wub_auth_penalty'),
        '100',
        PARAM_INT
    ));
}
