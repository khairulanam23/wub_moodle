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
 * Institutional Financial Restriction Notice Controller.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $CFG, $USER, $SESSION, $PAGE, $OUTPUT;

$clearanceservice = new \local_wub_auth\service\financial_clearance_service();
$waiverservice = new \local_wub_auth\service\waiver_service();

// Action: Re-check payment status
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'refresh') {
    $userid = (isloggedin() && !isguestuser()) ? (int)$USER->id : (int)($SESSION->wub_restricted_user->id ?? 0);
    if ($userid > 0) {
        $waiverservice->invalidate_user_cache($userid);
        if (isloggedin() && !isguestuser()) {
            $clearance = $clearanceservice->check_clearance($USER, true);
            if ($clearance->is_allowed()) {
                unset($SESSION->wub_restricted_clearance);
                unset($SESSION->wub_restricted_user);
                redirect(new moodle_url('/my/'));
            }
        }
    }
}

// Retrieve details from session or live evaluation
$userinfo = $SESSION->wub_restricted_user ?? null;
$clearancedata = $SESSION->wub_restricted_clearance ?? null;

if (isloggedin() && !isguestuser()) {
    $clearance = $clearanceservice->check_clearance($USER);
    if ($clearance->is_allowed()) {
        redirect(new moodle_url('/my/'));
    }
    $clearancedata = $clearance->to_array();
    $userinfo = (object)[
        'id' => $USER->id,
        'fullname' => fullname($USER),
        'username' => $USER->username,
        'idnumber' => $USER->idnumber,
    ];
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/wub_auth/restricted.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('restricted_title', 'local_wub_auth'));
$PAGE->set_heading(get_string('restricted_heading', 'local_wub_auth'));

$templatedata = [
    'user_fullname' => $userinfo ? $userinfo->fullname : 'Student Account',
    'user_identifier' => $userinfo ? ($userinfo->idnumber ?: $userinfo->username) : '',
    'net_due' => number_format((float)($clearancedata['net_due'] ?? 0.0), 2),
    'raw_due' => !empty($clearancedata['raw_due']) ? number_format((float)$clearancedata['raw_due'], 2) : null,
    'monthly_installment' => !empty($clearancedata['monthly_installment']) ? number_format((float)$clearancedata['monthly_installment'], 2) : null,
    'threshold' => number_format((float)($clearancedata['threshold'] ?? 100.0), 2),
    'logout_url' => (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false),
    'refresh_url' => (new moodle_url('/local/wub_auth/restricted.php', ['action' => 'refresh']))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_wub_auth/restricted_page', $templatedata);
echo $OUTPUT->footer();
