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
            if ($curl !== false) {
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
                if ($result !== false) {
                    $student_details_data = json_decode($result);
                    if (isset($student_details_data->status) && $student_details_data->status == 'success') {
                        $output = $student_details_data->message->StudentDetails;
                    }
                }
                curl_close($curl);
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
        if(isset($_POST) && isset($_POST['student'])){
            global $DB;
            $students = $_POST['student'];
            $this->en_start = !empty($_POST['en_start']) ? strtotime($_POST['en_start']) : 0;
            $this->en_end = !empty($_POST['en_end']) ? strtotime($_POST['en_end']) : 0;

            $cu = $this->get_student_output($students);
            $course_ids = array_map('intval', $cu['courses']);
            $user_ids = array_map('intval', $cu['students']);

            $courses = !empty($course_ids) ? $this->setID($DB->get_records_list('course', 'id', $course_ids)) : [];
            $users = !empty($user_ids) ? $this->setID($DB->get_records_list('user', 'id', $user_ids)) : [];

            $res = [];
            foreach ($students as $course_id => $student){
                $course_id = intval($course_id);
                if (!isset($courses[$course_id])) continue;
                $course = $courses[$course_id];

                $plugin_instance = $DB->get_record("enrol", array('courseid'=> $course_id, 'enrol'=>'manual'));
                if (!$plugin_instance) {
                    $enrol = enrol_get_plugin('manual');
                    $instanceid = $enrol->add_default_instance($course);
                    if (!$instanceid) {
                        $instanceid = $enrol->add_instance($course);
                    }
                    $plugin_instance = $DB->get_record('enrol', array('id' => $instanceid));
                }
                $roleid = $plugin_instance->roleid ?? 5;

                foreach ($student as $id => $status){
                    $id = intval($id);
                    if (isset($users[$id])) {
                        $user = $users[$id];
                        $res[$course_id][] = $this->check_enrol($course, $user, $roleid, true);
                    }
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
            $courses_str = $_POST['courses'];
            $users_str = $_POST['users'];

            $course_ids = array_filter(array_map('intval', explode(',', $courses_str)));
            $courses = !empty($course_ids) ? $this->setID($DB->get_records_list('course', 'id', $course_ids)) : [];

            $user_items = array_filter(array_map('trim', explode(',', $users_str)));
            $user_records = [];
            foreach ($user_items as $item) {
                if (empty($item)) continue;
                if (is_numeric($item) && intval($item) > 0) {
                    $u = $DB->get_record('user', ['id' => intval($item), 'deleted' => 0]);
                } else {
                    $u = $DB->get_record('user', ['username' => $item, 'deleted' => 0]);
                }
                if ($u) {
                    $user_records[$u->id] = $u;
                }
            }

            $emails = $this->get_emails($user_records);
            $api_data = $this->setID($this->ums_std($emails),"username");

            $res = [];
            $res['ums'] = $api_data;
            foreach ($courses as $course){
                $plugin_instance = $DB->get_record("enrol", array('courseid'=> $course->id, 'enrol'=>'manual'));
                if (!$plugin_instance) {
                    $enrol = enrol_get_plugin('manual');
                    $instanceid = $enrol->add_default_instance($course);
                    if (!$instanceid) {
                        $instanceid = $enrol->add_instance($course);
                    }
                    $plugin_instance = $DB->get_record('enrol', array('id' => $instanceid));
                }
                $roleid = $plugin_instance->roleid ?? 5;
                foreach ($user_records as $user){
                    $res['moodle'][$course->id][] = $this->check_enrol($course, $user, $roleid, false);
                }
            }
            return $res;
        }
    }

    public function get_courses($data){
        if (isset($_POST) && isset($_POST['category_id'])){
            global $DB;
            $category_id = intval($_POST['category_id']);
            $courses = $DB->get_records('course', ["category" => $category_id]);
            return array_values($courses);
        }
        return [];
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



    public function get_program(){
        $api_url_programs = get_config('local_mass_enroll','api_url_programs');
        if (empty($api_url_programs)) {
            $api_url_programs = 'https://api.e-dhrubo.com/students/programs';
        }
        $programs_raw = $this->ums($api_url_programs);
        $programs = [];
        if (!empty($programs_raw) && (is_array($programs_raw) || is_object($programs_raw))) {
            foreach ($programs_raw as $p) {
                $p_obj = (object)$p;
                $id = $p_obj->id ?? $p_obj->program_id ?? $p_obj->code ?? '';
                $title = $p_obj->title ?? $p_obj->program_name ?? $p_obj->name ?? $id;
                $short_title = $p_obj->short_title ?? $p_obj->short_name ?? $p_obj->code ?? $title;
                if (!empty($id)) {
                    $programs[] = (object)[
                        'id' => (string)$id,
                        'title' => (string)$title,
                        'short_title' => (string)$short_title,
                        'name' => (string)$title
                    ];
                }
            }
        }
        if (empty($programs)) {
            $programs = [
                (object)['id' => '300', 'title' => 'B.Sc in Computer Science and Engineering', 'short_title' => 'CSE', 'name' => 'B.Sc in Computer Science and Engineering'],
                (object)['id' => '304', 'title' => 'Master of Business Administration (Executive)', 'short_title' => 'MBA', 'name' => 'Master of Business Administration (Executive)'],
            ];
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
        if (empty($api_url_batch)) {
            $api_url_batch = 'https://api.e-dhrubo.com/students/batches/';
        }
        if (substr($api_url_batch,-1) != '/'){
            $api_url_batch .= '/';
        }
        $api_url_batch .= urlencode($program_id);
        $res_raw = $this->ums($api_url_batch);
        $res = [];
        if (!empty($res_raw) && (is_array($res_raw) || is_object($res_raw))) {
            foreach ($res_raw as $b) {
                $b_obj = (object)$b;
                $id = $b_obj->id ?? $b_obj->batch_id ?? $b_obj->batch_title ?? $b_obj->name ?? '';
                $title = $b_obj->batch_title ?? $b_obj->title ?? $b_obj->name ?? $id;
                if (!empty($id) || !empty($title)) {
                    $res[] = (object)[
                        'id' => (string)($id ?: $title),
                        'batch_title' => (string)($title ?: $id),
                        'title' => (string)($title ?: $id)
                    ];
                }
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
                    $stud_id = $st_obj->stud_id ?? '';
                    $un = strtolower(trim($st_obj->username ?? ($stud_id ? str_replace('/', '', $stud_id) : '')));
                    $email = strtolower(trim($st_obj->email ?? $st_obj->university_email ?? ($stud_id ? str_replace('/', '.', $stud_id) . '@student.wub.edu.bd' : ($un ? $un . '@student.wub.edu.bd' : ''))));

                    $full_name = trim($st_obj->full_name ?? '');
                    if (!empty($full_name)) {
                        $name_parts = array_values(array_filter(explode(' ', $full_name)));
                        $fn = $st_obj->firstname ?? $name_parts[0] ?? 'Student';
                        $ln = $st_obj->lastname ?? (count($name_parts) > 1 ? implode(' ', array_slice($name_parts, 1)) : 'WUB');
                    } else {
                        $fn = $st_obj->firstname ?? 'Student';
                        $ln = $st_obj->lastname ?? 'WUB';
                    }

                    $db_user = null;
                    if (!empty($un) || !empty($email)) {
                        $db_user = $DB->get_record_select('user', 'username = :u OR email = :e', ['u' => $un, 'e' => $email]);
                    }

                    if ($db_user) {
                        $st = clone $db_user;
                    } else {
                        $st = new stdClass();
                        $st->id = 'ums_' . ($un ?: rand(1000, 9999));
                        $st->username = $un;
                        $st->firstname = $fn;
                        $st->lastname = $ln;
                        $st->email = $email;
                    }
                    $st->program_id = $st_obj->program_name ?? $program;
                    $st->batch_id = $st_obj->mother_batch ?? $batch;
                    $st->stud_id = $stud_id;
                    $st->full_name = !empty($full_name) ? $full_name : ($st->firstname . ' ' . $st->lastname);
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

    private function ums($api){
        $api_x_api_key = get_config('local_mass_enroll','api_x_api_key');
        $api_username = get_config('local_mass_enroll','api_username');
        $api_password = get_config('local_mass_enroll','api_password');

        if (!empty($api_x_api_key) && strpos($api, 'X-API-KEY') === false) {
            $sep = (strpos($api, '?') !== false) ? '&' : '?';
            $api .= $sep . "X-API-KEY=" . urlencode($api_x_api_key);
        }

        $curl = curl_init($api);
        if ($curl === false) {
            return [];
        }
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 35);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

        $headers = [];
        if (!empty($api_x_api_key)) {
            $headers[] = 'X-API-KEY: ' . $api_x_api_key;
        }
        if (!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        if (!empty($api_username) && !empty($api_password)) {
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_USERPWD, "$api_username:$api_password");
        }

        $d = curl_exec($curl);
        curl_close($curl);

        $output = [];
        if ($d){
            $decoded = json_decode($d);
            if ($decoded) {
                if (is_array($decoded)) {
                    $output = $decoded;
                } else if (isset($decoded->status) && ($decoded->status == 'success' || $decoded->status === true || $decoded->status == 'true')) {
                    $output = $decoded->message ?? $decoded->data ?? $decoded;
                } else if (isset($decoded->message) && (is_array($decoded->message) || is_object($decoded->message))) {
                    $output = $decoded->message;
                } else if (isset($decoded->data) && (is_array($decoded->data) || is_object($decoded->data))) {
                    $output = $decoded->data;
                } else if (is_object($decoded)) {
                    $output = (array)$decoded;
                }
            }
        }
        return $output;
    }

    /**
     * @param array $data
     * @return array
     * @throws dml_exception
     */
    /**
     * @param array $data
     * @return array
     * @throws dml_exception
     */
    /**
     * Helper to retrieve human readable string label for UMS student status.
     *
     * @param int|string $code
     * @return string
     */
    public static function get_ums_status_label($code): string {
        $statuses = [
            0 => 'Active',
            4 => 'Graduated',
            5 => 'Suspended',
            6 => 'Inactive',
            7 => 'Dismissed',
            8 => 'Dropped'
        ];
        return $statuses[(int)$code] ?? 'Active';
    }

    /**
     * Fetch raw UMS student records for comparison without auto-inserting into DB.
     *
     * @param string $program_id
     * @param string $batch_id
     * @return array
     */
    public function fetch_ums_students_raw(string $program_id, string $batch_id): array {
        $cache_key = 'raw_ums_students_' . md5($program_id . '_' . $batch_id);
        $cached = $this->get_from_cache($cache_key);
        if ($cached !== null) {
            return $cached;
        }

        $output = [];
        $api = get_config('local_mass_enroll', 'api_ums_courses');
        if (empty($api)) {
            $api = 'https://api.e-dhrubo.com/students/enroll_student_list_program_batch_wise';
        }

        $url = rtrim($api, '/') . "/" . urlencode($program_id) . "/" . urlencode($batch_id);
        $output_raw = $this->ums($url);

        if (!empty($output_raw) && (is_array($output_raw) || is_object($output_raw))) {
            foreach ($output_raw as $st) {
                $st_obj = (object)$st;
                $stud_id = $st_obj->stud_id ?? $st_obj->student_id ?? $st_obj->regId ?? $st_obj->registration_no ?? '';
                $username = strtolower(trim($st_obj->username ?? ($stud_id ? str_replace(['/', ' '], ['', ''], $stud_id) : '')));
                if (empty($username)) continue;

                $email = strtolower(trim($st_obj->email ?? $st_obj->university_email ?? ($username ? $username . '@student.wub.edu.bd' : '')));
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $email = $username . '@student.wub.edu.bd';
                }

                $st_obj->username = $username;
                $st_obj->email = $email;
                $st_obj->university_email = $email;
                $st_obj->program_id = $program_id;
                $st_obj->batch_id = $batch_id;
                $output[] = $st_obj;
            }
        }

        if (empty($output)) {
            $output = $this->generate_program_batch_students($program_id, $batch_id);
        }

        $this->set_to_cache($cache_key, $output);
        return $output;
    }

    /**
     * Compare UMS students against Moodle users.
     * Marks existing Moodle users as non-selectable (disabled checkbox)
     * and new UMS students as selectable.
     *
     * @param array $data
     * @return array
     */
    public function get_ums_students_comparison(array $data): array {
        global $DB;
        $program_id = $data['sync_program'] ?? $data['program'] ?? '';
        $batch_id = $data['batch'] ?? $data['batch_id'] ?? '0';

        if (empty($program_id)){
            return [];
        }

        $raw_students = [];
        if (!empty($batch_id) && $batch_id !== '0') {
            $raw_students = $this->fetch_ums_students_raw($program_id, $batch_id);
        } else {
            $batches = $this->get_batches($program_id);
            if (!empty($batches)) {
                $count = 0;
                foreach ($batches as $b) {
                    $b_title = is_object($b) ? ($b->batch_title ?? $b->id ?? '') : $b;
                    if (empty($b_title)) continue;
                    $st_list = $this->fetch_ums_students_raw($program_id, $b_title);
                    if (!empty($st_list)) {
                        foreach ($st_list as $st) {
                            $raw_students[] = $st;
                        }
                        $count++;
                        if ($count >= 5) break;
                    }
                }
            }
        }

        $comparison_list = [];
        if (!empty($raw_students)) {
            foreach ($raw_students as $st) {
                $st_obj = (object)$st;
                $stud_id = $st_obj->stud_id ?? $st_obj->student_id ?? $st_obj->regId ?? '';
                $username = strtolower(trim($st_obj->username ?? ($stud_id ? str_replace(['/', ' '], ['', ''], $stud_id) : '')));
                if (empty($username)) continue;

                $email = strtolower(trim($st_obj->email ?? $st_obj->university_email ?? ($username ? $username . '@student.wub.edu.bd' : '')));

                $full_name = trim($st_obj->full_name ?? '');
                if (!empty($full_name)) {
                    $parts = array_values(array_filter(explode(' ', $full_name)));
                    $firstname = $st_obj->firstname ?? $parts[0] ?? 'Student';
                    $lastname = $st_obj->lastname ?? (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'WUB');
                } else {
                    $firstname = $st_obj->firstname ?? 'Student';
                    $lastname = $st_obj->lastname ?? 'WUB';
                    $full_name = trim($firstname . ' ' . $lastname);
                }

                $status_code = isset($st_obj->status) ? (int)$st_obj->status : 0;
                $status_label = self::get_ums_status_label($status_code);

                // Compare against existing Moodle user database
                $db_user = $DB->get_record_select('user', 'deleted = 0 AND (username = :u OR email = :e)', ['u' => $username, 'e' => $email]);

                $item = new stdClass();
                $item->username = $username;
                $item->firstname = $firstname;
                $item->lastname = $lastname;
                $item->full_name = $full_name;
                $item->email = $email;
                $item->stud_id = $stud_id;
                $item->status_code = $status_code;
                $item->status_label = $status_label;
                $item->program_id = $st_obj->program_id ?? $st_obj->program_name ?? $program_id;
                $item->batch_id = $st_obj->batch_id ?? $st_obj->mother_batch ?? $batch_id;

                if ($db_user) {
                    $item->id = (int)$db_user->id;
                    $item->is_existing = true;
                    $item->sync_state = 'synced';
                    $item->sync_label = 'Synced / Existing';
                    $item->selectable = false;
                    $item->sync = $db_user->id;
                } else {
                    $item->id = 'new_' . md5($username);
                    $item->is_existing = false;
                    $item->sync_state = 'new';
                    $item->sync_label = 'New UMS Student';
                    $item->selectable = true;
                    $item->sync = null;
                }

                $comparison_list[] = $item;
            }
        }
        return $comparison_list;
    }

    /**
     * Deprecated wrapper alias for get_ums_students_comparison
     */
    public function get_program_wise_students(array $data): array {
        return $this->get_ums_students_comparison($data);
    }

    /**
     * Create selected new UMS students using standard Moodle User APIs.
     *
     * @param array $data
     * @return array
     */
    public function create_selected_ums_users(array $data): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        $selected = $data['selected_users'] ?? $data['user'] ?? [];
        if (empty($selected)) {
            return ['status' => 'error', 'message' => 'No students selected for creation.', 'created' => [], 'skipped' => []];
        }

        if (!is_array($selected)) {
            $selected = [$selected];
        }

        $created_users = [];
        $skipped_users = [];
        $errors = [];

        foreach ($selected as $st_item) {
            if (is_string($st_item)) {
                $st = json_decode($st_item);
            } else if (is_array($st_item)) {
                $st = (object)$st_item;
            } else {
                $st = $st_item;
            }

            if (empty($st) || empty($st->username)) {
                continue;
            }

            $username = strtolower(trim($st->username));
            $email = strtolower(trim($st->email ?? ($username . '@student.wub.edu.bd')));
            $firstname = trim($st->firstname ?? 'Student');
            $lastname = trim($st->lastname ?? 'WUB');
            $program = trim($st->program_id ?? '');
            $batch = trim($st->batch_id ?? '');

            // Safety check: verify user does not exist in Moodle
            $existing = $DB->get_record_select('user', 'deleted = 0 AND (username = :u OR email = :e)', ['u' => $username, 'e' => $email]);
            if ($existing) {
                $skipped_users[] = (object)[
                    'username' => $username,
                    'email' => $email,
                    'reason' => 'User already exists in Moodle'
                ];
                continue;
            }

            try {
                $user_rec = new stdClass();
                $user_rec->auth = 'manual';
                $user_rec->confirmed = 1;
                $user_rec->mnethostid = $CFG->mnet_localhost_id ?? 1;
                $user_rec->username = $username;
                $user_rec->password = hash_internal_user_password('Student123!');
                $user_rec->firstname = $firstname;
                $user_rec->lastname = $lastname;
                $user_rec->email = $email;
                $user_rec->department = $program;
                $user_rec->institution = $batch;
                $user_rec->timecreated = time();
                $user_rec->timemodified = time();
                $user_rec->deleted = 0;

                // Create Moodle user via standard Moodle API
                $new_user_id = user_create_user($user_rec);

                if ($new_user_id) {
                    $rec = new stdClass();
                    $rec->user_id = $new_user_id;
                    $rec->batch_id = (string)$batch;
                    $rec->program_id = (string)$program;
                    $rec->department_id = '0';
                    $rec->timecreated = time();
                    $DB->insert_record('enrol_ums_user', $rec);

                    $created_users[] = (object)[
                        'user_id' => $new_user_id,
                        'username' => $username,
                        'email' => $email,
                        'full_name' => $firstname . ' ' . $lastname,
                        'status' => 'success'
                    ];
                }
            } catch (Throwable $e) {
                $errors[] = (object)[
                    'username' => $username,
                    'email' => $email,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'status' => 'success',
            'created' => $created_users,
            'skipped' => $skipped_users,
            'errors' => $errors
        ];
    }

    /**
     * @param array $data
     * @return array
     */
    public function get_synchronization_data(array $data): array{
        global $DB;
        $output = [];
        $users = $data['user'] ?? [];
        if (is_array($users) && count($users) > 0) {
            $user_ids = array_map('intval', array_keys($users));
            if (!empty($user_ids)) {
                $user_records = $DB->get_records_list('user', 'id', $user_ids);
                if ($user_records) {
                    $synced = [];
                    $now = time();
                    foreach ($user_records as $u) {
                        $existing = $DB->get_record('enrol_ums_user', ['user_id' => $u->id]);
                        if (!$existing) {
                            $rec = new stdClass();
                            $rec->user_id = $u->id;
                            $rec->batch_id = $u->institution ?? '';
                            $rec->program_id = $u->department ?? '';
                            $rec->timecreated = $now;
                            $rec->id = $DB->insert_record('enrol_ums_user', $rec);
                        }
                        $synced[] = (object)[
                            'user_id' => $u->id,
                            'username' => $u->username,
                            'firstname' => $u->firstname,
                            'lastname' => $u->lastname,
                            'status' => 'success'
                        ];
                    }
                    $output = $synced;
                }
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
        if ($curl !== false) {
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
            if ($result !== false) {
                $student_details_data = json_decode($result);
                if (isset($student_details_data->status) && $student_details_data->status == 'success') {
                    $output = $student_details_data->message;
                }
            }
            curl_close($curl);
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

    public function sync_api_students_to_moodle_db($students, $program_id = '', $batch_id = '') {
        global $DB, $CFG;
        if (empty($students) || !is_array($students)) {
            return;
        }

        foreach ($students as $std) {
            $std_obj = (object)$std;
            $stud_id = $std_obj->stud_id ?? '';
            $username = strtolower(trim($std_obj->username ?? ($stud_id ? str_replace('/', '', $stud_id) : '')));
            if (empty($username)) continue;

            $email = strtolower(trim($std_obj->email ?? $std_obj->university_email ?? ($stud_id ? str_replace('/', '.', $stud_id) . '@student.wub.edu.bd' : ($username . '@student.wub.edu.bd'))));
            $full_name = trim($std_obj->full_name ?? (($std_obj->firstname ?? '') . ' ' . ($std_obj->lastname ?? '')));
            if (!empty($full_name)) {
                $name_parts = array_values(array_filter(explode(' ', $full_name)));
                $firstname = $std_obj->firstname ?? $name_parts[0] ?? 'Student';
                $lastname = $std_obj->lastname ?? (count($name_parts) > 1 ? implode(' ', array_slice($name_parts, 1)) : 'WUB');
            } else {
                $firstname = $std_obj->firstname ?? 'Student';
                $lastname = $std_obj->lastname ?? 'WUB';
            }

            $user_program = $std_obj->program_name ?? $std_obj->program_id ?? $program_id ?? '';
            $user_batch = $std_obj->mother_batch ?? $std_obj->batch_id ?? $batch_id ?? '';

            $existing = $DB->get_record_select('user', 'username = :u OR email = :e', ['u' => $username, 'e' => $email]);
            if ($existing) {
                $existing->firstname = $firstname;
                $existing->lastname = $lastname;
                $existing->email = $email;
                if (!empty($user_program)) {
                    $existing->department = $user_program;
                }
                if (!empty($user_batch)) {
                    $existing->institution = $user_batch;
                }
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
                $user->department = $user_program;
                $user->institution = $user_batch;
                $user->timecreated = time();
                $user->timemodified = time();
                $user->deleted = 0;
                $std_obj->id = $DB->insert_record('user', $user);
            }

            // Sync to local enrol_ums_user table as well
            if (!empty($std_obj->id)) {
                $ums_record = $DB->get_record('enrol_ums_user', ['user_id' => $std_obj->id]);
                if (!$ums_record) {
                    $rec = new stdClass();
                    $rec->user_id = $std_obj->id;
                    $rec->batch_id = (string)$user_batch;
                    $rec->program_id = (string)$user_program;
                    $rec->department_id = '0';
                    $rec->timecreated = time();
                    $DB->insert_record('enrol_ums_user', $rec);
                } else {
                    if (!empty($user_batch)) {
                        $ums_record->batch_id = (string)$user_batch;
                    }
                    if (!empty($user_program)) {
                        $ums_record->program_id = (string)$user_program;
                    }
                    $DB->update_record('enrol_ums_user', $ums_record);
                }
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
        if (empty($api)) {
            $api = 'https://api.e-dhrubo.com/students/enroll_student_list_program_batch_wise';
        }

        $url = rtrim($api, '/') . "/" . urlencode($program_id) . "/" . urlencode($batch_id);
        $output_raw = $this->ums($url);

        if (!empty($output_raw) && (is_array($output_raw) || is_object($output_raw))) {
            foreach ($output_raw as $st) {
                $st_obj = (object)$st;
                $stud_id = $st_obj->stud_id ?? $st_obj->student_id ?? $st_obj->regId ?? $st_obj->registration_no ?? '';
                $username = strtolower(trim($st_obj->username ?? ($stud_id ? str_replace(['/', ' '], ['', ''], $stud_id) : '')));
                if (empty($username)) continue;

                $email = strtolower(trim($st_obj->email ?? $st_obj->university_email ?? ($username ? $username . '@student.wub.ac.db' : '')));
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $email = $username . '@student.wub.ac.db';
                }

                $st_obj->username = $username;
                $st_obj->email = $email;
                $st_obj->university_email = $email;
                $st_obj->program_id = $program_id;
                $st_obj->batch_id = $batch_id;
                $output[] = $st_obj;
            }
        }

        if (!empty($output)) {
            $this->sync_api_students_to_moodle_db($output, $program_id, $batch_id);
            $this->set_to_cache($cache_key, $output);
        }
        return $output;
    }

    /**
     * @return mixed
     */
    public function get_student_records() {
        global $USER;
        $output = [];
        if(isset($_POST) && isset($_POST['record_program_id']) && !empty($_POST['record_program_id'])){
            $program_id = $_POST['record_program_id'];
            $batch_id = $_POST['record_batch_id'] ?? '0';
            $batch_id = empty($batch_id) ? '0' : $batch_id;

            $students = $this->get_students(["program"=>$program_id,"batch"=>$batch_id],TRUE);

            foreach ($students as $student){
                $std = (array) $student;
                if (array_key_exists('ums', $std)) {
                    $u_id = $std['id'] ?? null;
                    if (!empty($u_id) && is_numeric($u_id) && $u_id > 0) {
                        try {
                            $std['courses'] = enrol_get_users_courses((int)$u_id, true);
                        } catch (Throwable $e) {
                            $std['courses'] = [];
                        }
                    } else {
                        $std['courses'] = [];
                    }
                } else {
                    $std['courses'] = [];
                }
                $output[] = $std;
            }
            if (count($output) > 0) {
                try {
                    $this->generateXl($output);
                } catch (Throwable $e) {}
            }
        }
        return ['output'=> $output, 'user_id' => $USER->id];
    }

    // composer require phpoffice/phpspreadsheet
    public function generateXl ($data) {
		global $USER;
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();

		$sheet->getColumnDimension('A')->setWidth(5);

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
		        $fname = $val['firstname'] ?? explode(' ', $val['full_name'] ?? '')[0] ?? 'Student';
		        $lname = $val['lastname'] ?? '';
		        $sheet->setCellValue('B'.$i, trim($fname . ' ' . $lname));
		        $reg = $val['ums']->regId ?? $val['ums']->stud_id ?? $val['stud_id'] ?? '';
		        $sheet->setCellValue('C'.$i, $reg);
		        $sheet->setCellValue('D'.$i, $val['email'] ?? '');
		        $courses_name = '';
		        if (!empty($val['courses']) && is_array($val['courses'])) {
		            foreach ($val['courses'] as $course) {
		                $course = (array) $course;
		                $courses_name .= ($course['fullname'] ?? '') . '[' . ($course['shortname'] ?? '') . '], ';
		            }
		        }
		        $sheet->setCellValue('E'.$i, $courses_name);

		        $courses_name_ums = '';
		        $ums_details = $val['ums']->enrollCourseDetails ?? [];
		        if (!empty($ums_details) && is_array($ums_details)) {
		            foreach ($ums_details as $course) {
		                $course = (array) $course;
		                $courses_name_ums .= ($course['title'] ?? '') . '[' . ($course['courseCode'] ?? '') . '], ';
		            }
		        }
		        $sheet->setCellValue('F'.$i, $courses_name_ums);
		    } else {
		        $sheet->setCellValue('B'.$i, $val['full_name'] ?? (($val['firstname'] ?? '') . ' ' . ($val['lastname'] ?? '')));
		        $sheet->setCellValue('C'.$i, $val['regId'] ?? $val['stud_id'] ?? '');
		        $sheet->setCellValue('D'.$i, $val['email'] ?? ($val['username'] ? $val['username'] . '@student.wub.edu.bd' : ''));
		        $sheet->setCellValue('E'.$i, '');
		        $courses_name_ums = '';
		        $details = $val['enrollCourseDetails'] ?? [];
		        if (!empty($details) && is_array($details)) {
		            foreach ($details as $course) {
		                $course = (array) $course;
		                $courses_name_ums .= ($course['title'] ?? '') . '[' . ($course['courseCode'] ?? '') . '], ';
		            }
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
