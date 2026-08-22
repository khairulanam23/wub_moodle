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
 * WUB Policy Page Entry Controller.
 *
 * Manages role-specific terms agreement with 30-day persistence, database storage,
 * and 20 comprehensive university policies.
 *
 * @package    local_wub_policy
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/wub_policy/lib.php');

global $CFG, $PAGE, $OUTPUT, $SESSION, $USER;

// Role parameter & whitelist validation.
$role = optional_param('role', 'student', PARAM_ALPHA);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

// Normalize role.
$role = wub_policy_normalize_role($role);

// If already accepted for this role within 30 days and current version, bypass policy page.
if (wub_policy_is_accepted($role)) {
    $SESSION->wub_intended_role = $role;
    if (!empty($returnurl)) {
        redirect(new moodle_url($returnurl));
    } else {
        redirect(new moodle_url('/local/wub_login/index.php', ['role' => $role]));
    }
}

$error = null;

// Handle form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF session key.
    require_sesskey();

    $agree = optional_param('agree', 0, PARAM_INT);

    if ($agree === 1) {
        // Record policy acceptance with 30-day persistence in database and device cookie.
        wub_policy_record_acceptance($role);

        // Store intended role in session.
        $SESSION->wub_intended_role = $role;

        // Redirect to returnurl or custom login.
        if (!empty($returnurl)) {
            redirect(new moodle_url($returnurl));
        } else {
            redirect(new moodle_url('/local/wub_login/index.php', ['role' => $role]));
        }
    } else {
        $error = get_string('mustagree', 'local_wub_policy');
    }
}

// Setup Page context.
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/wub_policy/index.php', array_filter(['role' => $role, 'returnurl' => $returnurl])));
$PAGE->set_pagelayout('wubportal');
$PAGE->set_title(get_string('pluginname', 'local_wub_policy'));
$PAGE->set_heading(get_string('pluginname', 'local_wub_policy'));

// Render Page.
echo $OUTPUT->header();

$renderable = new \local_wub_policy\output\policy_page($role, $error, $returnurl);
$templatedata = $renderable->export_for_template($OUTPUT);
echo $OUTPUT->render_from_template('local_wub_policy/policy_page', $templatedata);

echo $OUTPUT->footer();
