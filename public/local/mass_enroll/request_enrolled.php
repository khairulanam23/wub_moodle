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

    if(!is_siteadmin()){
        redirect('/');
        exit();
    }

    $PAGE->set_url(new moodle_url('/local/mass_enroll/request_enrolled.php'));

    $enrol_helper = new enrolhelper();
    $output = $ums = [];
    if (isset($_POST)){
        $output = $enrol_helper->verify_enrollment($_POST);
        $ums = $output['ums'];
    }

    $all_program = $enrol_helper->setID($_SESSION['programs']);

?>


<?php if (count($output['moodle']) > 0): ?>
<?php foreach ($output['moodle'] as $course_id => $data):?>
<table class="table table-sm">
    <thead>
        <tr>
            <th colspan="7" style="text-align: center;font-size: 22px;background: #eee;border-top: 3px solid #ddd;">
                <?=($data[0]['course']->fullname);?>
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
            <th>Status</th>
            <th width="14%" style="text-align: right;">Output</th>
        </tr>
    </thead>
    <tbody id="course_<?=$course_id;?>">
        <?php $i = 0;?>
        <?php foreach ($data as $k => $res):?>
            <?php
                $student = [];
                $email = $res['user']->email;
                if (array_key_exists($email,$ums)){
                    $student = $ums[$email];
                }
            ?>
            <?php
                $ums_status = 'Not_Sync';
                if ($student){
                    switch ($student->student_status){
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
            <tr class="<?= ($ums_status == 'Deactivate') ? 'bg-danger' : '' ;?>">
                <td>
                    <input type="checkbox" name="student[<?=$res['course']->id;?>][<?=$res['user']->id;?>]"
                        <?php if(($res['status'] == 'already exist') || in_array($ums_status,['Suspended','Inactive','Dismissed','Dropped'])): ?>
                            disabled
                        <?php elseif($ums_status == 'Not_Sync'):?>

                        <?php elseif($res['status'] == 'enrollable'):?>
                            checked
                        <?php endif;?>
                    />
                </td>
                <td><?=$res['user']->firstname. " " . $res['user']->lastname;?></td>
                <td><?=$res['user']->email;?></td>
                <td><?=$all_program[$student->program_id]->short_title;?></td>
                <td><?=$student->batch_id;?></td>
                <td>
                    <?=$ums_status;?>
                </td>
                <td style="text-align: right;"><?=$res['status'];?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endforeach; ?>
<script>
    function sAll(elemEnt,eachAll='input:checkbox'){
        var status = $(elemEnt).is(":checked");
        $(eachAll).each(function(){
            const d = $(this).attr('disabled');
            if (d != 'disabled'){
                $(this).prop('checked',status);
            }
        });
    }
</script>
<?php endif ?>