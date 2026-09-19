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
 * Administration settings for local_wub_auth.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_wub_auth',
        get_string('pluginname', 'local_wub_auth')
    );

    $ADMIN->add('localplugins', $settings);

    // ----------------------------------------------------------------------
    // Group 1: UMS Connection & Credentials
    // ----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'local_wub_auth/hdr_ums',
        get_string('setting_group_ums', 'local_wub_auth'),
        get_string('setting_group_ums_desc', 'local_wub_auth')
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_auth/base_url',
        get_string('setting_base_url', 'local_wub_auth'),
        get_string('setting_base_url_desc', 'local_wub_auth'),
        'https://api.e-dhrubo.com',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_auth/api_username',
        get_string('setting_api_username', 'local_wub_auth'),
        get_string('setting_api_username_desc', 'local_wub_auth'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_wub_auth/api_password',
        get_string('setting_api_password', 'local_wub_auth'),
        get_string('setting_api_password_desc', 'local_wub_auth'),
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_wub_auth/api_key',
        get_string('setting_api_key', 'local_wub_auth'),
        get_string('setting_api_key_desc', 'local_wub_auth'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_auth/timeout',
        get_string('setting_timeout', 'local_wub_auth'),
        get_string('setting_timeout_desc', 'local_wub_auth'),
        15,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_auth/connect_timeout',
        get_string('setting_connect_timeout', 'local_wub_auth'),
        get_string('setting_connect_timeout_desc', 'local_wub_auth'),
        5,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_wub_auth/ssl_verify',
        get_string('setting_ssl_verify', 'local_wub_auth'),
        get_string('setting_ssl_verify_desc', 'local_wub_auth'),
        1
    ));

    // ----------------------------------------------------------------------
    // Group 2: Authentication & Fallback
    // ----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'local_wub_auth/hdr_auth',
        get_string('setting_group_auth', 'local_wub_auth'),
        get_string('setting_group_auth_desc', 'local_wub_auth')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_wub_auth/enable_ums_fallback',
        get_string('setting_enable_ums_fallback', 'local_wub_auth'),
        get_string('setting_enable_ums_fallback_desc', 'local_wub_auth'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_wub_auth/sync_password_on_ums_login',
        get_string('setting_sync_password_on_ums_login', 'local_wub_auth'),
        get_string('setting_sync_password_on_ums_login_desc', 'local_wub_auth'),
        1
    ));

    // ----------------------------------------------------------------------
    // Group 3: Financial Access Control
    // ----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'local_wub_auth/hdr_financial',
        get_string('setting_group_financial', 'local_wub_auth'),
        get_string('setting_group_financial_desc', 'local_wub_auth')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_wub_auth/financial_control_enabled',
        get_string('setting_financial_control_enabled', 'local_wub_auth'),
        get_string('setting_financial_control_enabled_desc', 'local_wub_auth'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_auth/due_threshold',
        get_string('setting_due_threshold', 'local_wub_auth'),
        get_string('setting_due_threshold_desc', 'local_wub_auth'),
        100.0,
        PARAM_FLOAT
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_auth/buffer_deduction',
        get_string('setting_buffer_deduction', 'local_wub_auth'),
        get_string('setting_buffer_deduction_desc', 'local_wub_auth'),
        100.0,
        PARAM_FLOAT
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_auth/installment_day_cutoff',
        get_string('setting_installment_day_cutoff', 'local_wub_auth'),
        get_string('setting_installment_day_cutoff_desc', 'local_wub_auth'),
        15,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_auth/exempt_programs',
        get_string('setting_exempt_programs', 'local_wub_auth'),
        get_string('setting_exempt_programs_desc', 'local_wub_auth'),
        '324, 351, 359, 360, 363, 352, 361, 362, 313',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_auth/exempt_date_cutoff',
        get_string('setting_exempt_date_cutoff', 'local_wub_auth'),
        get_string('setting_exempt_date_cutoff_desc', 'local_wub_auth'),
        '09-10',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_auth/exempt_departments',
        get_string('setting_exempt_departments', 'local_wub_auth'),
        get_string('setting_exempt_departments_desc', 'local_wub_auth'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_auth/exempt_department_date_cutoff',
        get_string('setting_exempt_department_date_cutoff', 'local_wub_auth'),
        get_string('setting_exempt_department_date_cutoff_desc', 'local_wub_auth'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configselect(
        'local_wub_auth/outage_policy',
        get_string('setting_outage_policy', 'local_wub_auth'),
        get_string('setting_outage_policy_desc', 'local_wub_auth'),
        'restrict',
        [
            'restrict' => get_string('setting_outage_policy_restrict', 'local_wub_auth'),
            'allow' => get_string('setting_outage_policy_allow', 'local_wub_auth'),
        ]
    ));

    // ----------------------------------------------------------------------
    // Group 4: Policy Acknowledgement
    // ----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'local_wub_auth/hdr_policy',
        get_string('setting_group_policy', 'local_wub_auth'),
        get_string('setting_group_policy_desc', 'local_wub_auth')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_wub_auth/policy_enabled',
        get_string('setting_policy_enabled', 'local_wub_auth'),
        get_string('setting_policy_enabled_desc', 'local_wub_auth'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_auth/policy_version',
        get_string('setting_policy_version', 'local_wub_auth'),
        get_string('setting_policy_version_desc', 'local_wub_auth'),
        '1.0.0',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_auth/policy_expiry_days',
        get_string('setting_policy_expiry_days', 'local_wub_auth'),
        get_string('setting_policy_expiry_days_desc', 'local_wub_auth'),
        30,
        PARAM_INT
    ));

    // ----------------------------------------------------------------------
    // Group 5: Integration Boundary
    // ----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'local_wub_auth/hdr_integration',
        get_string('setting_group_integration', 'local_wub_auth'),
        get_string('setting_group_integration_desc', 'local_wub_auth')
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_wub_auth/integration_secret',
        get_string('setting_integration_secret', 'local_wub_auth'),
        get_string('setting_integration_secret_desc', 'local_wub_auth'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_wub_auth/token_ttl',
        get_string('setting_token_ttl', 'local_wub_auth'),
        get_string('setting_token_ttl_desc', 'local_wub_auth'),
        300,
        PARAM_INT
    ));

    // Administrative External Page: Student Financial Waivers & Special Permissions.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_wub_auth_waivers',
        get_string('waivers_title', 'local_wub_auth'),
        new moodle_url('/local/wub_auth/waivers.php'),
        'local/wub_auth:managewaivers'
    ));
}

