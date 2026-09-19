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
 * Student Financial Waiver Management Dashboard.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $CFG, $USER, $DB, $PAGE, $OUTPUT;

require_login();
$context = context_system::instance();
require_capability('local/wub_auth:managewaivers', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/wub_auth/waivers.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('waivers_title', 'local_wub_auth'));
$PAGE->set_heading(get_string('waivers_title', 'local_wub_auth'));

$waiverservice = new \local_wub_auth\service\waiver_service();
$resolver = new \local_wub_auth\service\identity_resolver();

$query = optional_param('q', '', PARAM_RAW_TRIMMED);
$action = optional_param('action', '', PARAM_ALPHA);
$notice = null;
$noticetype = 'info';

// Handle Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $targetuserid = required_param('userid', PARAM_INT);

    if ($action === 'grant') {
        $expirydate = required_param('expiry_date', PARAM_RAW_TRIMMED);
        $reason = optional_param('reason', '', PARAM_RAW_TRIMMED);

        $timestamp = strtotime($expirydate . ' 23:59:59');
        if (!$timestamp || $timestamp <= time()) {
            $notice = 'Expiry date must be a valid future date.';
            $noticetype = 'danger';
        } else {
            try {
                $targetuser = $DB->get_record('user', ['id' => $targetuserid, 'deleted' => 0], '*', MUST_EXIST);
                $waiverservice->grant_waiver($targetuserid, $timestamp, (int)$USER->id, $reason);
                $notice = get_string('waiver_granted_success', 'local_wub_auth', [
                    'name' => fullname($targetuser),
                    'expiry' => date('Y-m-d', $timestamp),
                ]);
                $noticetype = 'success';
            } catch (\Exception $e) {
                $notice = $e->getMessage();
                $noticetype = 'danger';
            }
        }
    } else if ($action === 'revoke') {
        try {
            $targetuser = $DB->get_record('user', ['id' => $targetuserid, 'deleted' => 0], '*', MUST_EXIST);
            $waiverservice->revoke_waiver($targetuserid, (int)$USER->id);
            $notice = get_string('waiver_revoked_success', 'local_wub_auth', [
                'name' => fullname($targetuser),
            ]);
            $noticetype = 'warning';
        } catch (\Exception $e) {
            $notice = $e->getMessage();
            $noticetype = 'danger';
        }
    }
}

// Student Selection
$selectedstudent = null;
if (!empty($query)) {
    $idresult = $resolver->resolve($query);
    if ($idresult->is_found()) {
        $student = $idresult->get_user();
        $activewaiver = $waiverservice->get_active_waiver((int)$student->id);

        $selectedstudent = [
            'id' => $student->id,
            'name' => fullname($student),
            'username' => $student->username,
            'email' => $student->email,
            'idnumber' => $student->idnumber,
            'department' => $student->department ?: 'N/A',
            'has_active_waiver' => ($activewaiver && $activewaiver->is_valid()),
            'active_waiver_expiry' => ($activewaiver ? userdate($activewaiver->get_timeend(), get_string('strftimedatetime', 'langconfig')) : ''),
            'current_reason' => ($activewaiver ? $activewaiver->get_reason() : ''),
        ];
    } else {
        $notice = get_string('waiver_student_not_found', 'local_wub_auth');
        $noticetype = 'warning';
    }
}

$recentwaivers = $waiverservice->get_recent_waivers(30);

$templatedata = [
    'notice_message' => $notice,
    'notice_type' => $noticetype,
    'search_query' => s($query),
    'search_action' => (new moodle_url('/local/wub_auth/waivers.php'))->out(false),
    'grant_action' => (new moodle_url('/local/wub_auth/waivers.php'))->out(false),
    'diagnostics_url' => (new moodle_url('/local/wub_auth/index.php'))->out(false),
    'sesskey' => sesskey(),
    'selected_student' => $selectedstudent,
    'default_expiry_date' => date('Y-m-d', strtotime('+30 days')),
    'min_expiry_date' => date('Y-m-d', strtotime('+1 day')),
    'recent_waivers' => $recentwaivers,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_wub_auth/waivers_page', $templatedata);
echo $OUTPUT->footer();
