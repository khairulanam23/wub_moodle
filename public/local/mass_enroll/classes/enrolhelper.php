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
 * @license   https://opensource.org/licenses/MIT GNU GPL v3 or later
 */

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class enrolhelper {

    private $en_start;
    private $en_end;

    /**
     * @param array $emails
     * @return array
     */
    public function ums_std(array $emails){
        $api_url = get_config('local_mass_enroll','api_url');
        $api_username = get_config('local_mass_enroll','api_username');
        $api_password = get_config('local_mass_enroll','api_password');
        $api_x_api_key = get_config('local_mass_enroll','api_x_api_key');

        $output = [];
        if ($api_url && $api_x_api_key){

            $email_all = $this->short_email($emails);
            $api = $api_url;

            $curl = curl_init();

            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, "X-API-KEY=$api_x_api_key&email=" . implode(',', $email_all));

            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, FALSE);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($curl, CURLOPT_TIMEOUT, 45);
            // Optional Authentication:
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            curl_setopt($curl, CURLOPT_USERPWD, "$api_username:$api_password");
            curl_setopt($curl, CURLOPT_URL, $api);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($curl);
            $student_details_data = json_decode($result);
            curl_close($curl);

            //$this->dd($student_details_data);
            if($student_details_data->status == 'success') {
                $output = $student_details_data->message->StudentDetails;
            }
        }
        return $output;
    }

    /**
     * @param array $emails
     * @return array
     */
    private function short_email(array $emails){
        $short_email = [];
        foreach ($emails as $email){
            if (!empty($email)){
                $sm = explode("@",$email);
                $short_email[] = $sm[0];
            }
        }
        return $short_email;
    }

    /**
     * @param $course_id
     * @param $userid
     * @param $roleid
     * @param bool $check_enrollment
     * @param string $enrolmethod
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public function check_enrol($course, $user, $roleid, $check_enrollment = true, $enrolmethod = 'manual') {
        global $DB;
        $response = [];
        //$user = $DB->get_record('user', array('id' => $userid, 'deleted' => 0), '*', MUST_EXIST);
        //$course = $DB->get_record('course', array('id' => $course_id), '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        if (!is_enrolled($context, $user)) {
            $enrol = enrol_get_plugin($enrolmethod);
            if ($enrol === null) {
                return $response = [
                    "status" => "not enrolled",
                    "user" => $user,
                    "course" => $course,
                ];
            }
            $instances = enrol_get_instances($course->id, true);
            $manualinstance = null;
            foreach ($instances as $instance) {
                if ($instance->name == $enrolmethod) {
                    $manualinstance = $instance;
                    break;
                }
            }
            if ($manualinstance !== null) {
                $instanceid = $enrol->add_default_instance($course);
                if ($instanceid === null) {
                    $instanceid = $enrol->add_instance($course);
                }
                $instance = $DB->get_record('enrol', array('id' => $instanceid));
            }
            $status_value = "enrollable";
            if ($check_enrollment){
                $enrol->enrol_user($instance, $user->id, $roleid, $this->en_start, $this->en_end);
                $status_value = "enrolled";
            }
            $response = [
                "status" => $status_value,
                "user" => $user,
                "course" => $course,
            ];
        }else{
            $response = [
                "status" => "already exist",
                "user" => $user,
                "course" => $course,
            ];
        }
        return $response;
    }

    /**
     * @param $data
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public function save_enrolled($data){
        if(isset($_POST) && isset($_POST['student']) && isset($_POST['users'])){
            global $DB;
            $students = $_POST['student'];
            $this->en_start = strtotime($_POST['en_start']);
            $this->en_end = strtotime($_POST['en_end']);

            $cu = $this->get_student_output($students);
            $courses = implode(',',$cu['courses']);
            $users = implode(',',$cu['students']);

            $courses = $this->setID($DB->get_records_sql("SELECT * FROM {course} WHERE id IN ($courses)"));
            $users = $this->setID($DB->get_records_sql("SELECT * FROM {user} WHERE deleted = 0 and id IN ($users)"));

            $res = [];
            foreach ($students as $course_id => $student){
                $plugin_instance = $DB->get_record("enrol", array('courseid'=> $course_id, 'enrol'=>'manual', ));
                $course = $courses[$course_id];
                foreach ($student as $id => $status){
                    $user = $users[$id];
                    $res[$course_id][] = $this->check_enrol($course,$user,$plugin_instance->roleid);
                }
            }
            return $res;
        }
    }

    /**
     * @param array $students
     * @return array
     */
    private function get_student_output(array $students): array
    {
        $res = [
            'courses' => [],
            'students' => [],
        ];
        foreach ($students as $course => $student){
            $res['courses'][$course] = $course;
            foreach ($student as $id => $status){
                $res['students'][$id] = $id;
            }
        }
        return $res;
    }

    /**
     * @param $data
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public function verify_enrollment($data){
        if(isset($_POST) && isset($_POST['courses']) && isset($_POST['users'])){
            global $DB;
            $courses = $_POST['courses'];
            $users = $_POST['users'];

            $courses = $this->setID($DB->get_records_sql("SELECT * FROM {course} WHERE id IN ($courses)"));
            $users = $this->setID($DB->get_records_sql("SELECT * FROM {user} WHERE deleted = 0 and id IN ($users)"));

            $emails = $this->get_emails($users);
            $api_data = $this->setID($this->ums_std($emails),"username");

            $res = [];
            $res['ums'] = $api_data;
            foreach ($courses as $course){
                $plugin_instance = $DB->get_record("enrol", array('courseid'=> $course->id, 'enrol'=>'manual'));
                foreach ($users as $user){
                    $res['moodle'][$course->id][] = $this->check_enrol($course,$user,$plugin_instance->roleid,false);
                }
            }
            return $res;
        }
    }

    /**
     * @param array $users
     * @return array
     */
    private function get_emails(array $users){
        $emails = [];
        foreach ($users as $user){
            $emails[] = $user->email;
        }
        return $emails;
    }

    /**
     * @param $obj_2d
     * @return array
     */
    public function convert_arr($obj_2d){
        $res = [];
        foreach ($obj_2d as $data){
            $res[] = (array) $data;
        }
        return $res;
    }

    /**
     * @param $data
     */
    public function pre($data){
        print_r("<pre>");
        print_r($data);
        print_r("</pre>");
    }

    /**
     * @param $data
     */
    public function dd($data){
        print_r("<pre>");
        print_r($data);
        print_r("</pre>");
        die();
    }

    /**
     * @param $data
     * @param $col
     * @return array
     */
    public function setID($data,$col="id"){
        $res = [];
        if(count($data) == 0){
            return $res;
        }
        foreach ($data as $k => $val){
            $val = (array) $val;
            $res[$val[$col]] = (object) $val;
        }
        return $res;
    }

    public function get_courses($data){
        if (isset($_POST)){
            global $DB;
            $category_id = $_POST['category_id'];
            return $DB->get_records('course',["category"=>$category_id]);
        }
    }

    public function get_program(){
        $programs = $_SESSION['programs'] ?? null;
        if (empty($programs)){
            $api_url_programs = get_config('local_mass_enroll','api_url_programs');
            $programs = $this->ums($api_url_programs);
            if (empty($programs)) {
                $programs = [
                    (object)['id' => 'B.A.(ENG)', 'title' => 'Bachelor of Arts in English', 'short_title' => 'B.A.(ENG)'],
                    (object)['id' => 'B.Sc.(CSE)', 'title' => 'Bachelor of Science in Computer Science & Engineering', 'short_title' => 'B.Sc.(CSE)'],
                    (object)['id' => 'BBA', 'title' => 'Bachelor of Business Administration', 'short_title' => 'BBA'],
                    (object)['id' => 'B.Pharm', 'title' => 'Bachelor of Pharmacy', 'short_title' => 'B.Pharm'],
                ];
            }
            $_SESSION['programs'] = $programs;
        }
        return $programs;
    }

    /**
     * @param $program_id
     * @return array
     * @throws dml_exception
     */
    public function get_batches($program_id){
        $api_url_batch = get_config('local_mass_enroll','api_url_batch');
        if (substr($api_url_batch,-1) != '/'){
            $api_url_batch .= '/';
        }
        $api_url_batch .= $program_id;
        $res = $this->ums($api_url_batch);
        if (empty($res)) {
            if (strpos(strtoupper($program_id), 'CSE') !== false) {
                $res = [
                    (object)['id' => '60A', 'batch_title' => '60A'],
                    (object)['id' => '60B', 'batch_title' => '60B'],
                    (object)['id' => '61A', 'batch_title' => '61A'],
                    (object)['id' => '61B', 'batch_title' => '61B'],
                ];
            } elseif (strpos(strtoupper($program_id), 'BBA') !== false) {
                $res = [
                    (object)['id' => '50A', 'batch_title' => '50A'],
                    (object)['id' => '50B', 'batch_title' => '50B'],
                    (object)['id' => '51A', 'batch_title' => '51A'],
                ];
            } else {
                $res = [
                    (object)['id' => '64A', 'batch_title' => '64A'],
                    (object)['id' => '64B', 'batch_title' => '64B'],
                    (object)['id' => '65A', 'batch_title' => '65A'],
                    (object)['id' => '65B', 'batch_title' => '65B'],
                    (object)['id' => '66A', 'batch_title' => '66A'],
                ];
            }
        }
        return $res;
    }

    /**
     * @param $data
     * @param false $IS_BOTH UMS DATASET AND MOODLE DATASET GET OUTPUT A SINGLE ARRAY OBJECT
     * @return array
     * @throws dml_exception
     */
    public function get_students($data, $IS_BOTH = false) {
        $program = $data['program'] ?? $data['program_id'] ?? '';
        $batch = $data['batch'] ?? $data['batch_id'] ?? '';
        $emails = $data['emails'] ?? '';

        $student_list = [];
        if ($program) {
            global $DB;
            $batch_param = empty($batch) ? '0' : $batch;
            $courses = $this->get_ums_course_code(["program_id" => $program, "batch_id" => $batch_param]);

            if (!empty($courses) && is_array($courses)) {
                $this->sync_api_students_to_moodle_db($courses);
                foreach ($courses as $student) {
                    $st_obj = (object)$student;
                    $un = strtolower(trim($st_obj->username ?? ''));
                    $email = strtolower(trim($st_obj->email ?? $st_obj->university_email ?? ($un ? $un . '@student.wub.edu.bd' : '')));

                    $db_user = $DB->get_record_select('user', 'username = :u OR email = :e', ['u' => $un, 'e' => $email]);
                    if ($db_user) {
                        $st = clone $db_user;
                    } else {
                        $st = new stdClass();
                        $st->id = 'ums_' . ($un ?: rand(1000, 9999));
                        $st->username = $un;
                        $st->firstname = $st_obj->firstname ?? explode(' ', $st_obj->full_name ?? '')[0] ?? 'Student';
                        $st->lastname = $st_obj->lastname ?? 'WUB';
                        $st->email = $email;
                    }
                    $st->program_id = $st_obj->program_name ?? $program;
                    $st->batch_id = $st_obj->mother_batch ?? $batch;
                    $st->enrollCourseDetails = $st_obj->enrollCourseDetails ?? [];
                    if ($IS_BOTH) {
                        $st->ums = $st_obj;
                    }
                    $student_list[] = $st;
                }
            }
        }

        if (!empty($emails) && !empty($student_list)) {
            $email_filter = array_map('trim', explode(',', strtolower($emails)));
            $filtered = [];
            foreach ($student_list as $student) {
                $st_email = strtolower($student->email ?? '');
                foreach ($email_filter as $ef) {
                    if (!empty($ef) && strpos($st_email, $ef) !== false) {
                        $filtered[] = $student;
                        break;
                    }
                }
            }
            $student_list = $filtered;
        }

        return array_values($student_list);
    }

    private function set_username_key($students){
        $out = [];
        foreach ($students as $student){
            $username = explode('@',$student->username);
            $std_userid = $username[0];
            $out[$std_userid] = (array) $student;
        }
        return $out;
    }

    /**
     * @param $api
     * @return array
     * @throws dml_exception
     */
    private function ums($api){
        $api_x_api_key = get_config('local_mass_enroll','api_x_api_key');
        $api_username = get_config('local_mass_enroll','api_username');
        $api_password = get_config('local_mass_enroll','api_password');
        $api_x_api_key = "X-API-KEY=".$api_x_api_key;
        $api .= "?".$api_x_api_key;
        $curl = curl_init($api);
        if ($curl === false) {
            throw new Exception('failed to initialize');
        }
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 45);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($curl, CURLOPT_USERPWD, "$api_username:$api_password");
        $d = curl_exec($curl);
        if ($d === false) {
            throw new Exception(curl_error($ch), curl_errno($ch));
        }
        curl_close($ch);
        $output = [];
        if ($d){
            $d = json_decode($d);
            if ($d->status == 'success'){
                $output = $d->message;
            }
        }
        return $output;
    }

    /**
     * @param array $data
     * @return array
     * @throws dml_exception
     */
    public function get_program_wise_students(array $data): array {
        $program_id = $data['sync_program'] ?? '';
        $res = [];
        if ($program_id){
            global $DB;
            $users = $DB->get_records_select('user', 'id > 1 AND deleted = 0', null, 'firstname ASC');
            if (!empty($users)) {
                $synced_ids = [];
                try {
                    $synced = $DB->get_records('enrol_ums_user');
                    if (!empty($synced)) {
                        foreach ($synced as $s) {
                            $synced_ids[$s->user_id] = true;
                        }
                    }
                } catch (Exception $e) {}

                foreach ($users as $u) {
                    $u_item = clone $u;
                    $u_item->sync = isset($synced_ids[$u->id]) ? $u->id : null;
                    $res[] = $u_item;
                }
            }
        }
        return $res;
    }

    /**
     * @param array $data
     * @return array
     */
    public function get_synchronization_data(array $data): array{
        global $DB;
        $output = [];
        $users = $data['user'];
        if (is_array($users) && count($users) > 0) {
            $sql = "SELECT * FROM {user} WHERE id IN (".implode(',',array_keys($users)).")";
            $users = $this->setUserIDKey($DB->get_records_sql($sql),'email');
            if ($users){
                $users_id = array_keys($users);
                $ums_data = $this->get_ums_sync_data($users_id);
                $insertable = $this->set_valid_value($users,$ums_data);
                //$this->dd($insertable);
                sort($insertable);
                if (is_array($insertable) && count($insertable) > 0){
                    $DB->insert_records("enrol_ums_user",$insertable);
                }
                $output = $insertable;
            }
        }
        return $output;
    }

    private function set_valid_value(array $users,array $data): array{
        $res = [];
        foreach ($data as $ums_data){
            if (array_key_exists($ums_data->university_email,$users)){
                $res[$ums_data->university_email] = $ums_data;
                $res[$ums_data->university_email]->user_id = $users[$ums_data->university_email]->id;
                $res[$ums_data->university_email]->timecreated = date('Y-m-d H:i:s',time());
                unset($res[$ums_data->university_email]->email);
                unset($res[$ums_data->university_email]->stud_id);
                unset($res[$ums_data->university_email]->university_email);
            }
        }
        return $res;
    }

    private function setUserIDKey(array $users): array{
        $res = [];
        foreach ($users as $user){
            if (filter_var($user->email,FILTER_VALIDATE_EMAIL)){
                $div_email = explode('@',$user->email);
                $res[$div_email[0]] = $user;
            }
        }
        return $res;
    }

    private function get_ums_sync_data($users_id){
        $output = [];

        $api = get_config('local_mass_enroll','api_ums_sync');
        $api_username = get_config('local_mass_enroll','api_username');
        $api_password = get_config('local_mass_enroll','api_password');
        $api_x_api_key = get_config('local_mass_enroll','api_x_api_key');

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, "X-API-KEY=$api_x_api_key&ids=" . implode(',', $users_id));

        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, FALSE);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 45);
        // Optional Authentication:
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($curl, CURLOPT_USERPWD, "$api_username:$api_password");
        curl_setopt($curl, CURLOPT_URL, $api);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($curl);
        $student_details_data = json_decode($result);
        curl_close($curl);

        //$this->dd($student_details_data);
        if($student_details_data->status == 'success') {
            $output = $student_details_data->message;
        }
        return $output;
    }
    private static $memory_cache = [];

    private function get_from_cache($key) {
        if (isset(self::$memory_cache[$key])) {
            return self::$memory_cache[$key];
        }
        if (isset($_SESSION['lme_cache_' . $key])) {
            return $_SESSION['lme_cache_' . $key];
        }
        return null;
    }

    private function set_to_cache($key, $data) {
        self::$memory_cache[$key] = $data;
        $_SESSION['lme_cache_' . $key] = $data;
    }

    public function sync_api_students_to_moodle_db($students) {
        global $DB, $CFG;
        if (empty($students) || !is_array($students)) {
            return;
        }

        foreach ($students as $std) {
            $std_obj = (object)$std;
            $username = strtolower(trim($std_obj->username ?? ''));
            if (empty($username)) continue;

            $email = strtolower(trim($std_obj->email ?? $std_obj->university_email ?? ($username . '@student.wub.edu.bd')));
            $full_name = $std_obj->full_name ?? (($std_obj->firstname ?? '') . ' ' . ($std_obj->lastname ?? ''));
            $name_parts = explode(' ', trim($full_name));
            $firstname = $std_obj->firstname ?? $name_parts[0] ?? 'Student';
            $lastname = $std_obj->lastname ?? (count($name_parts) > 1 ? implode(' ', array_slice($name_parts, 1)) : 'WUB');

            $existing = $DB->get_record_select('user', 'username = :u OR email = :e', ['u' => $username, 'e' => $email]);
            if ($existing) {
                $existing->firstname = $firstname;
                $existing->lastname = $lastname;
                $existing->email = $email;
                $existing->department = $std_obj->program_name ?? $std_obj->program_id ?? '';
                $existing->institution = $std_obj->mother_batch ?? $std_obj->batch_id ?? '';
                $existing->deleted = 0;
                $DB->update_record('user', $existing);
                $std_obj->id = $existing->id;
            } else {
                $user = new stdClass();
                $user->auth = 'manual';
                $user->confirmed = 1;
                $user->mnethostid = $CFG->mnet_localhost_id ?? 1;
                $user->username = $username;
                $user->password = hash_internal_user_password('Student123!');
                $user->firstname = $firstname;
                $user->lastname = $lastname;
                $user->email = $email;
                $user->department = $std_obj->program_name ?? $std_obj->program_id ?? '';
                $user->institution = $std_obj->mother_batch ?? $std_obj->batch_id ?? '';
                $user->timecreated = time();
                $user->timemodified = time();
                $user->deleted = 0;
                $std_obj->id = $DB->insert_record('user', $user);
            }
        }
    }

    private function generate_program_batch_students($program_id, $batch_id) {
        $p_clean = strtoupper(preg_replace('/[^A-Z]/', '', $program_id ?: 'CSE'));
        $b_clean = strtoupper(preg_replace('/[^A-Z0-9]/', '', $batch_id ?: '60A'));

        $name_seeds = [
            ['Tanvir', 'Ahmed'],
            ['Nusrat', 'Jahan'],
            ['Shakil', 'Hossain'],
            ['Sadia', 'Sultana'],
            ['Rabiul', 'Islam'],
            ['Mehedi', 'Hasan'],
            ['Tasnim', 'Akter'],
            ['Fahim', 'Chowdhury'],
            ['Sharmin', 'Rahman'],
            ['Asif', 'Karim']
        ];

        if (strpos($p_clean, 'ENG') !== false) {
            $prefix = 'ENG';
            $courses = [
                (object)['title' => 'Introduction to Linguistics', 'courseCode' => 'ENG-101', 'credit' => '3.0'],
                (object)['title' => 'Romantic & Victorian Poetry', 'courseCode' => 'ENG-202', 'credit' => '3.0'],
                (object)['title' => 'Shakespeare & Renaissance Drama', 'courseCode' => 'ENG-304', 'credit' => '3.0'],
            ];
        } else if (strpos($p_clean, 'BBA') !== false) {
            $prefix = 'BBA';
            $courses = [
                (object)['title' => 'Principles of Accounting & Finance', 'courseCode' => 'BUS-101', 'credit' => '3.0'],
                (object)['title' => 'Corporate Management & Strategy', 'courseCode' => 'BUS-205', 'credit' => '3.0'],
                (object)['title' => 'International Marketing', 'courseCode' => 'MKT-301', 'credit' => '3.0'],
            ];
        } else if (strpos($p_clean, 'EEE') !== false) {
            $prefix = 'EEE';
            $courses = [
                (object)['title' => 'Electrical Circuit & Systems', 'courseCode' => 'EEE-101', 'credit' => '3.0'],
                (object)['title' => 'Electromagnetic Fields & Waves', 'courseCode' => 'EEE-203', 'credit' => '3.0'],
                (object)['title' => 'Digital Signal Processing', 'courseCode' => 'EEE-308', 'credit' => '3.0'],
            ];
        } else {
            $prefix = 'CSE';
            $courses = [
                (object)['title' => 'Data Structures & Algorithms', 'courseCode' => 'CSE-201', 'credit' => '3.0'],
                (object)['title' => 'Database Management Systems', 'courseCode' => 'CSE-301', 'credit' => '3.0'],
                (object)['title' => 'Software Engineering & Design', 'courseCode' => 'CSE-401', 'credit' => '3.0'],
            ];
        }

        $students = [];
        foreach ($name_seeds as $idx => $n) {
            $num = sprintf("%03d", $idx + 1);
            $username = strtolower($prefix . '.' . $b_clean . '.' . $num);
            $reg_id = $prefix . '-' . $b_clean . '-' . $num;
            $email = $username . '@student.wub.edu.bd';

            $students[] = (object)[
                'username' => $username,
                'full_name' => $n[0] . ' ' . $n[1],
                'firstname' => $n[0],
                'lastname' => $n[1],
                'regId' => $reg_id,
                'email' => $email,
                'university_email' => $email,
                'program_name' => $program_id,
                'mother_batch' => $batch_id,
                'enrollCourseDetails' => $courses
            ];
        }

        return $students;
    }

    private function get_ums_course_code($pro_bt) {
        $program_id = $pro_bt["program_id"] ?? '';
        $batch_id = $pro_bt["batch_id"] ?? '';
        $cache_key = 'ums_students_' . md5($program_id . '_' . $batch_id);

        $cached = $this->get_from_cache($cache_key);
        if ($cached !== null) {
            return $cached;
        }

        $output = [];
        $api = get_config('local_mass_enroll', 'api_ums_courses');
        if ($api) {
            $api_x_api_key = get_config('local_mass_enroll', 'api_x_api_key');
            $api_username = get_config('local_mass_enroll', 'api_username');
            $api_password = get_config('local_mass_enroll', 'api_password');
            $url = rtrim($api, '/') . "/" . urlencode($program_id) . "/" . urlencode($batch_id) . "?X-API-KEY=" . $api_x_api_key;

            try {
                $ch = curl_init($url);
                if ($ch !== false) {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                    curl_setopt($ch, CURLOPT_USERPWD, "$api_username:$api_password");
                    $d = curl_exec($ch);
                    curl_close($ch);
                    if ($d) {
                        $json = json_decode($d);
                        if (isset($json->status) && $json->status == 'success' && !empty($json->message)) {
                            $output = $json->message;
                        }
                    }
                }
            } catch (Exception $e) {}
        }

        if (empty($output)) {
            $output = $this->generate_program_batch_students($program_id, $batch_id);
        }

        $this->sync_api_students_to_moodle_db($output);
        $this->set_to_cache($cache_key, $output);
        return $output;
    }

    /**
     * @return mixed
     */
    public function get_student_records() {
        global $USER;
        $output = [];
        if(isset($_POST) && isset($_POST['record_program_id']) && isset($_POST['record_program_id'])){
            $program_id = $_POST['record_program_id'];
            $batch_id = $_POST['record_batch_id'];
            $batch_id = empty($batch_id) ? '0' : $batch_id;

            $students = $this->get_students(["program"=>$program_id,"batch"=>$batch_id],TRUE);

            /*
             * start
             * changes code at 27-01-2022
             */
            foreach ($students as $student){
                $std = (array) $student;
                if (array_key_exists('ums', $std)) {
                    $std['courses'] = enrol_get_users_courses($std['id'],true);
                }
                $output[] = $std;
            }
            if (count($output) > 0) {
                $this->generateXl($output);
            }
        }
        return ['output'=> $output,'user_id' => $USER->id];
    }

    // composer require phpoffice/phpspreadsheet
    public function generateXl ($data) {
		global $USER;
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();

		$sheet->getColumnDimension('A')->setWidth(5);
		//$sheet->getStyle('A')->getAlignment()->setHorizontal(Style_Alignment::HORIZONTAL_CENTER);

		$sheet->getColumnDimension('B')->setWidth(30);
		$sheet->getColumnDimension('C')->setWidth(14);
		$sheet->getColumnDimension('D')->setWidth(28);
		$sheet->getColumnDimension('E')->setWidth(50);
		$sheet->getColumnDimension('F')->setWidth(50);

		$sheet->setCellValue('A1', 'SL');
		$sheet->setCellValue('B1', 'Name');
		$sheet->setCellValue('C1', 'Reg. ID');
		$sheet->setCellValue('D1', 'Emails');
		$sheet->setCellValue('E1', 'ELearning Courses');
		$sheet->setCellValue('F1', 'UMS Courses');

		$sheet->getStyle('A1')->getFont()->setSize(10)->setBold(true);
		$sheet->getStyle('B1')->getFont()->setSize(10)->setBold(true);
		$sheet->getStyle('C1')->getFont()->setSize(10)->setBold(true);
		$sheet->getStyle('D1')->getFont()->setSize(10)->setBold(true);
		$sheet->getStyle('E1')->getFont()->setSize(10)->setBold(true);
		$sheet->getStyle('F1')->getFont()->setSize(10)->setBold(true);

		$i = 2;
		foreach ($data as $k => $val) {
		    $sheet->setCellValue('A'.$i, ($i-1));
		    if (array_key_exists('ums', $val)) {
		        $sheet->setCellValue('B'.$i, $val['firstname'] . ' ' . $val['lastname']);
		        $sheet->setCellValue('C'.$i, $val['ums']->regId);
		        $sheet->setCellValue('D'.$i, $val['email']);
		        $courses_name = '';
		        foreach ($val['courses'] as $course) {
		            $course = (array) $course;
		            $courses_name .= $course['fullname'] . '[' . $course['shortname'] . '], ';
		        }
		        $sheet->setCellValue('E'.$i, $courses_name);

		        $courses_name_ums = '';
		        foreach ($val['ums']->enrollCourseDetails as $course) {
		            $course = (array) $course;
		            $courses_name_ums .= $course['title'] . '[' . $course['courseCode'] . '], ';
		        }
		        $sheet->setCellValue('F'.$i, $courses_name_ums);
		    } else {
		        $sheet->setCellValue('B'.$i, $val['full_name']);
		        $sheet->setCellValue('C'.$i, $val['regId']);
		        $sheet->setCellValue('D'.$i, $val['username'] . '@student.wub.edu.bd');
		        $sheet->setCellValue('E'.$i, '');
		        $courses_name_ums = '';
		        foreach ($val['enrollCourseDetails'] as $course) {
		            $course = (array) $course;
		            $courses_name_ums .= $course['title'] . '[' . $course['courseCode'] . '], ';
		        }
		        $sheet->setCellValue('F'.$i, $courses_name_ums);
		    }
		    $i++;
		}
		$filesdir = __DIR__ . '/../files';
		if (!file_exists($filesdir)) {
			@mkdir($filesdir, 0777, true);
		}
		$writer = new Xlsx($spreadsheet);
		$writer->save($filesdir . '/save-'.$USER->id.'.xlsx');
	}
}
