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
 * Delete Images for the quizaccess_proctoring plugin.
 *
 * @package    quizaccess_proctoring
 * @copyright  2024 Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

require_once(__DIR__ . '/../../../../config.php');

// No guest autologin.
require_login();
require_sesskey();

// Get URL parameters.
$systemcontext = context_system::instance();
$contextid = optional_param('context', $systemcontext->id, PARAM_INT);

// Check permissions and resolve context.
list($context, $course, $cm) = get_context_info_array($contextid);

require_login($course, false, $cm);
require_capability('quizaccess/proctoring:deletecamshots', $context);

// Updating the proctoring logs strictly scoped to the authorized context via log_repository.
$logrepo = new \quizaccess_proctoring\repository\log_repository();
if (!empty($cm) && !empty($cm->id)) {
    $logrepo->set_deletion_progress_by_quiz((int)$cm->id, 1);
} else if (!empty($course) && !empty($course->id) && (int)$course->id !== SITEID) {
    $logrepo->set_deletion_progress_by_course((int)$course->id, 1);
} else if ($context->contextlevel == CONTEXT_SYSTEM && is_siteadmin()) {
    $logrepo->set_deletion_progress_global(1);
} else {
    throw new moodle_exception('nopermission', 'error');
}

// Redirect to the settings page.
$url = new moodle_url('/admin/settings.php', ['section' => 'modsettingsquizcatproctoring']);

// Redirect to the settings page with a success message.
redirect($url, get_string('settings:deleteallsuccess', 'quizaccess_proctoring'), null, \core\output\notification::NOTIFY_SUCCESS);
