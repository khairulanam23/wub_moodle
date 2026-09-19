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
 * Settings configuration for local_examcontroller.
 *
 * @package    local_examcontroller
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_examcontroller', get_string('pluginname', 'local_examcontroller'));

    $settings->add(new admin_setting_heading(
        'local_examcontroller_heading',
        get_string('setting_header', 'local_examcontroller'),
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_examcontroller/integration_secret',
        get_string('setting_integration_secret', 'local_examcontroller'),
        get_string('setting_integration_secret_desc', 'local_examcontroller'),
        'wub_examcontroller_shared_secret_2026'
    ));

    $settings->add(new admin_setting_configtext(
        'local_examcontroller/integration_key_id',
        get_string('setting_integration_key_id', 'local_examcontroller'),
        get_string('setting_integration_key_id_desc', 'local_examcontroller'),
        'default',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_examcontroller/token_ttl',
        get_string('setting_token_ttl', 'local_examcontroller'),
        get_string('setting_token_ttl_desc', 'local_examcontroller'),
        '300',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
