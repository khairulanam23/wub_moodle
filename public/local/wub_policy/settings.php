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
 * Admin settings for local_wub_policy.
 *
 * @package    local_wub_policy
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_wub_policy', get_string('pluginname', 'local_wub_policy'));

    // Policy Version.
    $settings->add(new admin_setting_configtext(
        'local_wub_policy/policyversion',
        get_string('setting_policyversion', 'local_wub_policy'),
        get_string('setting_policyversion_desc', 'local_wub_policy'),
        '1.0.0',
        PARAM_TEXT
    ));

    // Expiry Duration in Days (Default 30 days).
    $settings->add(new admin_setting_configtext(
        'local_wub_policy/policyexpiry_days',
        get_string('setting_policyexpiry_days', 'local_wub_policy'),
        get_string('setting_policyexpiry_days_desc', 'local_wub_policy'),
        '30',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
