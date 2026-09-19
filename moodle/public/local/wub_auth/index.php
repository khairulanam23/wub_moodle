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
 * Administrative Diagnostics Dashboard.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $CFG, $USER, $DB, $PAGE, $OUTPUT;

require_login();
$context = context_system::instance();
require_capability('local/wub_auth:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/wub_auth/index.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('diagnostics_title', 'local_wub_auth'));
$PAGE->set_heading(get_string('diagnostics_title', 'local_wub_auth'));

$apiclient = new \local_wub_auth\api_client();
$policyservice = new \local_wub_auth\service\policy_service();
$resolver = new \local_wub_auth\service\identity_resolver($apiclient);

$notice = null;
$noticetype = 'info';
$probemessage = null;
$probetype = 'info';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = optional_param('action', '', PARAM_ALPHA);

    if ($action === 'probe_ums') {
        $probe = $apiclient->probe_connection();
        if ($probe['success']) {
            $probemessage = get_string('diagnostics_probe_success', 'local_wub_auth', $probe['programs_count']);
            $probetype = 'success';
        } else {
            $probemessage = get_string('diagnostics_probe_failed', 'local_wub_auth', $probe['message']);
            $probetype = 'danger';
        }
    } else if ($action === 'purge_cache') {
        try {
            $cache1 = cache::make('local_wub_auth', 'financial_clearance');
            $cache1->purge();
            $cache2 = cache::make('local_wub_auth', 'policy_acceptance');
            $cache2->purge();
            $notice = get_string('diagnostics_cache_purged', 'local_wub_auth');
            $noticetype = 'success';
        } catch (\Exception $e) {
            $notice = 'Cache purge failed: ' . $e->getMessage();
            $noticetype = 'danger';
        }
    }
}

// Identity Resolver Test
$testid = optional_param('test_id', '', PARAM_RAW_TRIMMED);
$testidresult = null;

if (!empty($testid)) {
    $res = $resolver->resolve($testid);
    $testidresult = [
        'status' => $res->get_status(),
        'found' => $res->is_found(),
        'user_name' => $res->get_user() ? fullname($res->get_user()) : '',
        'user_id' => $res->get_user() ? $res->get_user()->id : '',
        'user_username' => $res->get_user() ? $res->get_user()->username : '',
        'match_type' => $res->get_match_type(),
        'err_msg' => $res->get_error_message(),
    ];
}

// Metrics
$now = time();
$activewaivers = $DB->count_records_select('wub_auth_waivers', 'status = 1 AND timeend >= :now', ['now' => $now]);
$totalwaivers = $DB->count_records('wub_auth_waivers');
$policyacceptcount = $DB->count_records('wub_auth_policy_accept');

$templatedata = [
    'notice_message' => $notice,
    'notice_type' => $noticetype,
    'probe_result' => !empty($probemessage),
    'probe_message' => $probemessage,
    'probe_type' => $probetype,
    'ums_connected' => $apiclient->is_configured(),
    'ums_endpoint' => $apiclient->get_base_url(),
    'active_waivers_count' => $activewaivers,
    'total_waivers_count' => $totalwaivers,
    'policy_version' => $policyservice->get_version(),
    'policy_accept_count' => $policyacceptcount,
    'test_id_input' => s($testid),
    'test_id_result' => $testidresult,
    'form_action' => (new moodle_url('/local/wub_auth/index.php'))->out(false),
    'waivers_url' => (new moodle_url('/local/wub_auth/waivers.php'))->out(false),
    'settings_url' => (new moodle_url('/admin/settings.php', ['section' => 'local_wub_auth']))->out(false),
    'sesskey' => sesskey(),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_wub_auth/diagnostics_page', $templatedata);
echo $OUTPUT->footer();
