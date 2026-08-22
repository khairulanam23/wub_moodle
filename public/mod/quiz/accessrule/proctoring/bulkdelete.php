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
 * Bulk Delete for the quizaccess_proctoring plugin.
 *
 * @package    quizaccess_proctoring
 * @copyright  2024 Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/lib/tablelib.php');
require_once(__DIR__ . '/classes/additional_settings_helper.php');
use quizaccess_proctoring\additional_settings_helper;

require_login();

// Validate parameters strictly.
$cmid = required_param('cmid', PARAM_INT);
$type = required_param('type', PARAM_ALPHA);
$id = required_param('id', PARAM_INT);

require_sesskey();

// Resolve and authorize against the actual TARGET context, preventing IDOR / cross-course deletion.
if ($type === 'quiz') {
    list($targetcourse, $targetcm) = get_course_and_cm_from_cmid($id, 'quiz');
    $targetcontext = context_module::instance($targetcm->id, MUST_EXIST);

    require_login($targetcourse, false, $targetcm);
    require_capability('quizaccess/proctoring:deletecamshots', $targetcontext);

    $helper = new additional_settings_helper();
    $camshotdata = $helper->searchbyquizid($id);

} else if ($type === 'course') {
    $targetcourse = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
    $targetcontext = context_course::instance($targetcourse->id, MUST_EXIST);

    require_login($targetcourse);
    require_capability('quizaccess/proctoring:deletecamshots', $targetcontext);

    $helper = new additional_settings_helper();
    $camshotdata = $helper->searchbycourseid($id);

} else {
    throw new moodle_exception('invalidtype', 'quizaccess_proctoring');
}

if (empty($camshotdata)) {
    throw new moodle_exception('nodata', 'quizaccess_proctoring');
}

$rowids = [];
foreach ($camshotdata as $row) {
    $rowids[] = (int)$row->id;
}

if (!empty($rowids)) {
    $rowidstring = implode(',', $rowids);

    // Atomic transaction for log and file cleanup.
    $transaction = $DB->start_delegated_transaction();
    try {
        $helper->deletelogs($rowidstring);
        $transaction->allow_commit();
    } catch (\Exception $e) {
        $transaction->rollback($e);
        throw $e;
    }
}

// Redirect back to proctoring summary.
$params = ['cmid' => $cmid];
$url = new moodle_url('/mod/quiz/accessrule/proctoring/proctoringsummary.php', $params);
redirect($url, get_string('settings:deleteallsuccess', 'quizaccess_proctoring'), null, \core\output\notification::NOTIFY_SUCCESS);
