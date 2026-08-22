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
     * Allows course enrolment via a simple text code.
     *
     * @package   local_mass_enroll
     * @copyright 2021 World University of Bangladesh (CIS)
     * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */

    require_once(dirname(__FILE__) . '/../../config.php');
    require_once($CFG->dirroot . '/local/mass_enroll/classes/enrolhelper.php');

    require_login();
    $context = context_system::instance();
    require_capability('local/mass_enroll:enrol', $context);

    $PAGE->set_url(new moodle_url('/local/mass_enroll/request_enrolled.php'));

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidrequest', 'error');
    }
    require_sesskey();

    $enrol_helper = new enrolhelper();
    $output = [];
    $ums = [];
    if (!empty($_POST)) {
        $verify_res = $enrol_helper->verify_enrollment($_POST);
        if (is_array($verify_res)) {
            $output = $verify_res;
            $ums = $verify_res['ums'] ?? [];
        }
    }

    $programs_session = $_SESSION['programs'] ?? [];
    $all_program = $enrol_helper->setID(is_array($programs_session) ? $programs_session : []);

    global $DB;
    // Extract all user IDs across all courses to prefetch DB records and eliminate N+1 queries.
    $all_user_ids = [];
    if (!empty($output['moodle']) && is_array($output['moodle'])) {
        foreach ($output['moodle'] as $course_data) {
            if (is_array($course_data)) {
                foreach ($course_data as $row) {
                    $u_id = isset($row['user']) ? (is_array($row['user']) ? ($row['user']['id'] ?? 0) : ($row['user']->id ?? 0)) : 0;
                    if ($u_id > 0) {
                        $all_user_ids[$u_id] = $u_id;
                    }
                }
            }
        }
    }
    $db_students = [];
    if (!empty($all_user_ids)) {
        $db_records = $DB->get_records_list('enrol_ums_user', 'user_id', array_values($all_user_ids));
        foreach ($db_records as $rec) {
            $db_students[$rec->user_id] = (array)$rec;
        }
    }
?>


<?php if (!empty($output['moodle']) && is_array($output['moodle']) && count($output['moodle']) > 0): ?>
<?php foreach ($output['moodle'] as $course_id => $data):?>
<table class="table table-sm">
    <thead>
        <tr>
            <th colspan="6" style="text-align: center;font-size: 22px;background: #eee;border-top: 3px solid #ddd;">
                <?= isset($data[0]['course']) ? (is_array($data[0]['course']) ? ($data[0]['course']['fullname'] ?? '') : ($data[0]['course']->fullname ?? '')) : ''; ?>
            </th>
        </tr>
        <tr>
            <th width="3%">
                <input type="checkbox" onclick="sAll(this,'#course_<?=$course_id;?> input:checkbox');" checked/>
            </th>
            <th>Name</th>
            <th>Email</th>
            <th>Program</th>
            <th>Batch</th>
            <th width="14%" style="text-align: right;">Output</th>
        </tr>
    </thead>
    <tbody id="course_<?=$course_id;?>">
        <?php $i = 0;?>
        <?php foreach ($data as $k => $res):?>
            <?php
                $student = [];
                $email = isset($res['user']) ? (is_array($res['user']) ? ($res['user']['email'] ?? '') : ($res['user']->email ?? '')) : '';
                if (!empty($email) && array_key_exists($email, $ums)){
                    $student = $ums[$email];
                }

                $student = (array) $student;
                $user_id = isset($res['user']) ? (is_array($res['user']) ? ($res['user']['id'] ?? 0) : ($res['user']->id ?? 0)) : 0;
                if ($user_id > 0 && (empty($student['program_id']) || empty($student['batch_id']))) {
                    if (isset($db_students[$user_id])) {
                        $db_student = $db_students[$user_id];
                        if (empty($student['program_id'])) {
                            $student['program_id'] = $db_student['program_id'] ?? '';
                        }
                        if (empty($student['batch_id'])) {
                            $student['batch_id'] = $db_student['batch_id'] ?? '';
                        }
                    }
                }
            ?>
            <?php
                $ums_status = 'Not_Sync';
                if (!empty($student) && (is_array($student) ? isset($student['student_status']) : isset($student->student_status))){
                    $student_status = is_array($student) ? ($student['student_status'] ?? null) : ($student->student_status ?? null);
                    switch ($student_status){
                        case 0:
                            $ums_status = 'Active';
                            break;
                        case 4:
                            $ums_status = 'Graduated';
                            break;
                        case 5:
                            $ums_status = 'Suspended';
                            break;
                        case 6:
                            $ums_status = 'Inactive';
                            break;
                        case 7:
                            $ums_status = 'Dismissed';
                            break;
                        case 8:
                            $ums_status = 'Dropped';
                            break;
                        default:
                            $ums_status = 'Undefined';
                    }
                }
            ?>
            <?php
                $ums_deactivate = ($ums_status == 'Deactivate');
                $course_id = isset($res['course']) ? (is_array($res['course']) ? ($res['course']['id'] ?? '') : ($res['course']->id ?? '')) : '';
                $user_id = isset($res['user']) ? (is_array($res['user']) ? ($res['user']['id'] ?? '') : ($res['user']->id ?? '')) : '';
                $status = isset($res['status']) ? $res['status'] : (isset($res->status) ? $res->status : '');
                $firstname = isset($res['user']) ? (is_array($res['user']) ? ($res['user']['firstname'] ?? '') : ($res['user']->firstname ?? '')) : '';
                $lastname = isset($res['user']) ? (is_array($res['user']) ? ($res['user']['lastname'] ?? '') : ($res['user']->lastname ?? '')) : '';
                $user_email = isset($res['user']) ? (is_array($res['user']) ? ($res['user']['email'] ?? '') : ($res['user']->email ?? '')) : '';
                $student_program_id = !empty($student) ? (is_array($student) ? ($student['program_id'] ?? '') : ($student->program_id ?? '')) : '';
                $program_title = '';
                if (!empty($student_program_id)) {
                    if (isset($all_program[$student_program_id])) {
                        $prog = $all_program[$student_program_id];
                        $program_title = is_array($prog) ? ($prog['short_title'] ?? '') : ($prog->short_title ?? '');
                    }
                    if (empty($program_title)) {
                        $program_title = $student_program_id;
                    }
                }
                $student_batch_id = !empty($student) ? (is_array($student) ? ($student['batch_id'] ?? '') : ($student->batch_id ?? '')) : '';
            ?>
            <tr class="<?= $ums_deactivate ? 'bg-danger' : '' ;?>">
                <td>
                    <input type="checkbox" name="student[<?= (int)$course_id;?>][<?= (int)$user_id;?>]"
                        <?php if(($status == 'already exist') || in_array($ums_status,['Suspended','Inactive','Dismissed','Dropped'])): ?>
                            disabled
                        <?php elseif($ums_status == 'Not_Sync'):?>

                        <?php elseif($status == 'enrollable'):?>
                            checked
                        <?php endif;?>
                    />
                </td>
                <td><?= s($firstname . " " . $lastname);?></td>
                <td><?= s($user_email);?></td>
                <td><?= s($program_title);?></td>
                <td><?= s($student_batch_id);?></td>
                <td style="text-align: right;"><?= s($status);?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endforeach; ?>
<?php endif ?>