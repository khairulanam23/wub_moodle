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
            foreach ($instances as $inst) {
                if ($inst->enrol == $enrolmethod) {
                    $manualinstance = $inst;
                    break;
                }
            }
            if ($manualinstance === null) {
                $instanceid = $enrol->add_default_instance($course);
                if ($instanceid === null) {
                    $instanceid = $enrol->add_instance($course);
                }
                $manualinstance = $DB->get_record('enrol', array('id' => $instanceid));
            }
            $instance = $manualinstance;
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
        $students = $data['student'] ?? $_POST['student'] ?? [];
        if (!empty($students) && is_array($students)){
            global $DB;
            $en_start_str = $data['en_start'] ?? $_POST['en_start'] ?? '';
            $en_end_str = $data['en_end'] ?? $_POST['en_end'] ?? '';
            $this->en_start = !empty($en_start_str) ? strtotime($en_start_str) : 0;
            $this->en_end = !empty($en_end_str) ? strtotime($en_end_str) : 0;

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

                if (is_array($student)) {
                    foreach ($student as $id => $status){
                        $id = intval($id);
                        if (isset($users[$id])) {
                            $user = $users[$id];
                            $res[$course_id][] = $this->check_enrol($course, $user, $roleid, true);
                        }
                    }
                }
            }
            return $res;
        }
        return [];
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
        $courses_str = $data['courses'] ?? $_POST['courses'] ?? '';
        $users_str = $data['users'] ?? $_POST['users'] ?? '';
        if (!empty($courses_str) && !empty($users_str)){
            global $DB;

            $course_ids = array_filter(array_map('intval', explode(',', $courses_str)));
            $courses = !empty($course_ids) ? $this->setID($DB->get_records_list('course', 'id', $course_ids)) : [];

            $user_items = array_filter(array_map('trim', explode(',', $users_str)));
            $user_records = [];
            foreach ($user_items as $item) {
                if (empty($item)) continue;
                $u = $DB->get_record('user', ['username' => $item, 'deleted' => 0]);
                if (!$u && is_numeric($item) && intval($item) > 0) {
                    $u = $DB->get_record('user', ['id' => intval($item), 'deleted' => 0]);
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
     * Diagnostic helper for inspecting data arrays.
     * Only outputs when Moodle debugdisplay is enabled.
     *
     * @param mixed $data
     */
    public function pre($data){
        global $CFG;
        if (!empty($CFG->debugdisplay)) {
            echo "<pre style='background:#1e293b; color:#38bdf8; padding:16px; border-radius:8px;'>";
            print_r($data);
            echo "</pre>";
        }
    }

    /**
     * Diagnostic helper for inspecting data arrays and terminating execution.
     * Only outputs when Moodle debugdisplay is enabled.
     *
     * @param mixed $data
     */
    public function dd($data){
        global $CFG;
        if (!empty($CFG->debugdisplay)) {
            echo "<pre style='background:#1e293b; color:#38bdf8; padding:16px; border-radius:8px;'>";
            print_r($data);
            echo "</pre>";
            die();
        }
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
        global $CFG;
        // Ensure UMS API client is available.
        if (!class_exists('local_wub_ums\\api_client')) {
            $path = $CFG->dirroot . '/local/wub_ums/classes/api_client.php';
            if (file_exists($path)) {
                require_once($path);
            }
        }
        $client = new \local_wub_ums\api_client();
        $programs = $client->get_programs();

        // Whitelist of allowed program IDs for bulk enrolment.
        $allowed = [324,351,359,360,363,352,361,362,313];
        $filtered = [];
        foreach ($programs as $p) {
            $id = (int)($p->id ?? 0);
            if (in_array($id, $allowed, true)) {
                $filtered[] = $p;
            }
        }
        return $filtered;
    }

    public function get_all_programs(){
        global $CFG;
        // Ensure UMS API client is available.
        if (!class_exists('local_wub_ums\\api_client')) {
            $path = $CFG->dirroot . '/local/wub_ums/classes/api_client.php';
            if (file_exists($path)) {
                require_once($path);
            }
        }
        $client = new \local_wub_ums\api_client();
        return $client->get_programs();
    }

    /**
     * @param $program_id
     * @return array
     * @throws dml_exception
     */
    public function get_batches($program_id){
        if (class_exists('\\local_wub_ums\\api_client')) {
            $client = new \local_wub_ums\api_client();
            return $client->get_batches((string)$program_id);
        }
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
                $this->sync_api_students_to_moodle_db($courses, $program, $batch_param);
                foreach ($courses as $student) {
                    $st_obj = (object)$student;
                    $stud_id = $st_obj->stud_id ?? '';
                    $raw_un = strtolower(trim($st_obj->username ?? ($stud_id ? str_replace(['/', ' ', '-'], ['', '', ''], $stud_id) : '')));
                    if (empty($raw_un)) continue;
                    $orig_un = explode('@', $raw_un)[0];
                    $digits = preg_replace('/[^0-9]/', '', $orig_un);
                    $clean_id = !empty($digits) ? $digits : $orig_un;

                    $moodle_username = $clean_id;
                    $email = $clean_id . '@student.wub.edu.bd';

                    $full_name = trim($st_obj->full_name ?? '');
                    if (!empty($full_name)) {
                        $name_parts = array_values(array_filter(explode(' ', $full_name)));
                        $fn = $st_obj->firstname ?? $name_parts[0] ?? 'Student';
                        $ln = $st_obj->lastname ?? (count($name_parts) > 1 ? implode(' ', array_slice($name_parts, 1)) : 'WUB');
                    } else {
                        $fn = $st_obj->firstname ?? 'Student';
                        $ln = $st_obj->lastname ?? 'WUB';
                    }

                    $db_user = $DB->get_record_select('user', 'deleted = 0 AND (username = :u1 OR username = :u2 OR email = :e1 OR email = :e2)', [
                        'u1' => $moodle_username,
                        'u2' => $orig_un,
                        'e1' => $moodle_username . '@student.wub.edu.bd',
                        'e2' => $email
                    ]);

                    if ($db_user) {
                        $st = clone $db_user;
                    } else {
                        $st = new stdClass();
                        $st->id = 'ums_' . ($orig_un ?: rand(1000, 9999));
                        $st->username = $moodle_username;
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
        // Resolve batch title if numeric batch ID was passed
        $batch_param = trim($batch_id);
        if (is_numeric($batch_param)) {
            $batches = $this->get_batches($program_id);
            foreach ($batches as $b) {
                $b_obj = (object)$b;
                if (($b_obj->id ?? '') == $batch_param && !empty($b_obj->batch_title)) {
                    $batch_param = $b_obj->batch_title;
                    break;
                }
            }
        }

        $cache_key = 'raw_ums_students_' . md5($program_id . '_' . $batch_param);
        $cached = $this->get_from_cache($cache_key);
        if ($cached !== null) {
            return $cached;
        }

        $output = [];
        $api = get_config('local_mass_enroll', 'api_ums_courses');
        if (empty($api)) {
            $api = 'https://api.e-dhrubo.com/students/enroll_student_list_program_batch_wise';
        }

        $url = rtrim($api, '/') . "/" . urlencode($program_id) . "/" . urlencode($batch_param);
        $output_raw = $this->ums($url);

        // If batch_param didn't return students and batch_id was different, try original batch_id
        if (empty($output_raw) && $batch_param !== $batch_id) {
            $url2 = rtrim($api, '/') . "/" . urlencode($program_id) . "/" . urlencode($batch_id);
            $output_raw = $this->ums($url2);
        }

        if (!empty($output_raw) && (is_array($output_raw) || is_object($output_raw))) {
            foreach ($output_raw as $st) {
                $st_obj = (object)$st;
                $stud_id = $st_obj->stud_id ?? $st_obj->student_id ?? $st_obj->regId ?? $st_obj->registration_no ?? '';
                $raw_un = strtolower(trim($st_obj->username ?? ($stud_id ? str_replace(['/', ' '], ['', ''], $stud_id) : '')));
                if (empty($raw_un)) continue;
                $orig_un = explode('@', $raw_un)[0];
                $moodle_username = $orig_un . '@student.wub.edu.bd';

                $email = strtolower(trim($st_obj->email ?? $st_obj->university_email ?? ($orig_un ? $orig_un . '@student.wub.edu.bd' : '')));
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $email = $moodle_username;
                }

                $full_name = trim($st_obj->full_name ?? (($st_obj->firstname ?? '') . ' ' . ($st_obj->lastname ?? '')));

                $st_obj->username = $moodle_username;
                $st_obj->email = $email;
                $st_obj->university_email = $email;
                $st_obj->full_name = $full_name;
                $st_obj->program_id = $program_id;
                $st_obj->batch_id = $batch_param;
                $output[] = $st_obj;
            }
        }

        $this->set_to_cache($cache_key, $output);
        return $output;
    }

    /**
     * Create or safely update a Moodle student user account from UMS API data.
     * Moodle username is set to <student_username>@student.wub.ac.bd.
     * Initial password is set to student's original UMS username.
     * Preserves existing user IDs, enrolments, roles, and grades.
     *
     * @param mixed $std_data
     * @param string $program_id
     * @param string $batch_id
     * @return stdClass|null
     */
    public function sync_or_create_student_user($std_data, $program_id = '', $batch_id = '') {
        // Delegate entirely to the canonical sync service in local_wub_ums.
        // This avoids duplicating user creation, password handling, and special_premission logic.
        global $CFG;
        if (!class_exists('\\local_wub_ums\\sync_service')) {
            $path = $CFG->dirroot . '/local/wub_ums/classes/sync_service.php';
            if (file_exists($path)) {
                require_once($CFG->dirroot . '/local/wub_ums/classes/api_client.php');
                require_once($path);
            }
        }
        if (class_exists('\\local_wub_ums\\sync_service')) {
            $service = new \local_wub_ums\sync_service();
            return $service->sync_student($std_data, (string)$program_id, (string)$batch_id);
        }
        // If sync_service is unavailable (plugin not installed), return null gracefully.
        return null;
    }

    /**
     * Compare UMS students against Moodle users.
     * Marks students existing in local DB as "Sync" and selectable for sync/update.
     * Marks students only existing in UMS API as "Not Sync" and selectable to create/sync.
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
                $raw_un = strtolower(trim($st_obj->username ?? ($stud_id ? str_replace(['/', ' '], ['', ''], $stud_id) : '')));
                if (empty($raw_un)) continue;
                $orig_un = explode('@', $raw_un)[0];
                $moodle_un = $orig_un;

                $email = strtolower(trim($st_obj->email ?? $st_obj->university_email ?? ($orig_un ? $orig_un . '@student.wub.edu.bd' : '')));

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

                $status_code = isset($st_obj->student_status) ? (int)$st_obj->student_status : (isset($st_obj->status) ? (int)$st_obj->status : 0);
                $status_label = self::get_ums_status_label($status_code);

                // Compare against existing Moodle user database
                $db_user = $DB->get_record_select('user', 'deleted = 0 AND (username = :u1 OR username = :u2 OR email = :e1 OR email = :e2 OR email = :e3)', [
                    'u1' => $moodle_un,
                    'u2' => $orig_un,
                    'e1' => $moodle_un,
                    'e2' => $email,
                    'e3' => $orig_un . '@student.wub.edu.bd'
                ]);

                $item = new stdClass();
                $item->username = $moodle_un;
                $item->firstname = $firstname;
                $item->lastname = $lastname;
                $item->full_name = $full_name;
                $item->email = $email;
                $item->stud_id = $stud_id;
                $item->status_code = $status_code;
                $item->status_label = $status_label;
                $item->program_id = $st_obj->program_id ?? $st_obj->program_name ?? $program_id;
                $item->batch_id = $st_obj->batch_id ?? $st_obj->mother_batch ?? $batch_id;
                $item->selectable = true;

                if ($db_user) {
                    // Student exists in local db -> Synced
                    $item->id = (int)$db_user->id;
                    $item->is_existing = true;
                    $item->sync_state = 'synced';
                    $item->sync_label = 'Sync';
                    $item->sync = (int)$db_user->id;
                } else {
                    // Student exists in UMS API but not yet in local db -> Not Sync
                    $item->id = 'not_sync_' . md5($orig_un);
                    $item->is_existing = false;
                    $item->sync_state = 'not_sync';
                    $item->sync_label = 'Not Sync';
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
     * Synchronize selected student records with UMS API data in local Moodle database.
     * Creates new student accounts (<student_username>@student.wub.ac.bd) or updates existing accounts safely.
     *
     * @param array $data
     * @return array
     */
    public function create_selected_ums_users(array $data): array {
        $selected = $data['selected_users'] ?? $data['user'] ?? [];
        if (empty($selected)) {
            return ['status' => 'error', 'message' => 'No students selected for synchronization.', 'created' => [], 'skipped' => []];
        }

        if (!is_array($selected)) {
            $selected = [$selected];
        }

        $synced_users = [];
        $skipped_users = [];

        foreach ($selected as $st_item) {
            if (is_string($st_item)) {
                $st = json_decode($st_item);
            } else if (is_array($st_item)) {
                $st = (object)$st_item;
            } else {
                $st = $st_item;
            }

            if (empty($st)) {
                continue;
            }

            $user_rec = $this->sync_or_create_student_user($st);
            if ($user_rec) {
                $synced_users[] = (object)[
                    'user_id' => $user_rec->id,
                    'username' => $user_rec->username,
                    'email' => $user_rec->email,
                    'full_name' => $user_rec->firstname . ' ' . $user_rec->lastname,
                    'status' => 'success'
                ];
            } else {
                $skipped_users[] = (object)[
                    'username' => $st->username ?? 'unknown',
                    'reason' => 'Invalid student data or missing username'
                ];
            }
        }

        return [
            'status' => 'success',
            'created' => $synced_users,
            'skipped' => $skipped_users,
            'message' => count($synced_users) . ' student record(s) synchronized with local database.'
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
        if (isset($_SESSION['lme_live_ums_' . $key])) {
            return $_SESSION['lme_live_ums_' . $key];
        }
        return null;
    }

    private function set_to_cache($key, $data) {
        self::$memory_cache[$key] = $data;
        $_SESSION['lme_live_ums_' . $key] = $data;
    }
    /**
     * Check whether a student is restricted due to outstanding dues (> 100 BDT) or status in UMS.
     * Administrators and Teachers are completely exempt from UMS calls.
     *
     * @param int $userid
     * @return array ['allowed' => bool, 'reason' => string, 'status' => string, 'due' => float]
     */
    public function check_student_due_status(int $userid): array {
        global $CFG;
        if (file_exists($CFG->dirroot . '/local/wub_auth_penalty/lib.php')) {
            require_once($CFG->dirroot . '/local/wub_auth_penalty/lib.php');
        }
        if (function_exists('wub_auth_penalty_check_student_due_status')) {
            return wub_auth_penalty_check_student_due_status($userid);
        }
        if (function_exists('wub_ums_check_student_due_status')) {
            return wub_ums_check_student_due_status($userid);
        }
        global $DB, $SESSION;

        // Exempt administrators.
        if (is_siteadmin($userid)) {
            return ['allowed' => true, 'reason' => 'Administrator exempt', 'status' => 'Active', 'due' => 0.0];
        }

        // Exempt teachers.
        $courses = enrol_get_users_courses($userid, true, ['id']);
        if (!empty($courses)) {
            foreach ($courses as $c) {
                $ccontext = context_course::instance($c->id);
                if (has_capability('moodle/course:manageactivities', $ccontext, $userid, false) ||
                    has_capability('moodle/course:viewhiddenactivities', $ccontext, $userid, false)) {
                    return ['allowed' => true, 'reason' => 'Teacher exempt', 'status' => 'Active', 'due' => 0.0];
                }
            }
        }

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$user) {
            return ['allowed' => false, 'reason' => 'User record not found', 'status' => 'Not_Found', 'due' => 0.0];
        }

        // Check session cache (10 minutes) to avoid repeated API calls.
        $cache_key = 'wub_due_status_' . $userid;
        if (isset($SESSION->$cache_key) && is_array($SESSION->$cache_key)) {
            $cached = $SESSION->$cache_key;
            if (isset($cached['time']) && (time() - $cached['time']) < 600) {
                return $cached['data'];
            }
        }

        // Extract base student username (e.g. 0326735386 from 0326735386@student.wub.ac.bd).
        $student_username = explode('@', $user->username)[0];
        if (empty($student_username)) {
            $student_username = explode('@', $user->email)[0];
        }

        $payment_api = get_config('local_mass_enroll', 'api_student_payment_info');
        if (empty($payment_api)) {
            $payment_api = 'https://api.e-dhrubo.com/students/student_payment_info/';
        }
        if (substr($payment_api, -1) !== '/') {
            $payment_api .= '/';
        }
        $payment_url = $payment_api . urlencode($student_username);

        $payment_info = $this->ums($payment_url);

        $allowed = true;
        $status = 'Active';
        $reason = '';
        $remaining_dues = null;

        if (!empty($payment_info) && (is_object($payment_info) || is_array($payment_info))) {
            $p_obj = (object)$payment_info;
            if (isset($p_obj->remaining_deus)) {
                $remaining_dues = (float)$p_obj->remaining_deus;
            } else if (isset($p_obj->remaining_dues)) {
                $remaining_dues = (float)$p_obj->remaining_dues;
            } else if (isset($p_obj->due)) {
                $remaining_dues = (float)$p_obj->due;
            } else if (isset($p_obj->dues)) {
                $remaining_dues = (float)$p_obj->dues;
            }
        }

        if ($remaining_dues !== null) {
            if ($remaining_dues > 100.0) {
                $allowed = false;
                $status = 'Payment_Due';
                $reason = 'Access to the Moodle dashboard is restricted due to outstanding dues of ' . number_format($remaining_dues, 2) . ' BDT (exceeding the allowable limit of 100 BDT). Please clear your pending dues in UMS to restore access.';
            } else {
                $allowed = true;
                $status = 'Active';
            }
        } else {
            // Fallback: If payment API returned no dues data, check student status via ums_std
            $email = $user->email;
            $ums_data = $this->ums_std([$email]);
            if (!empty($ums_data) && is_array($ums_data) && isset($ums_data[$email])) {
                $st = (array)$ums_data[$email];
                $st_status = $st['student_status'] ?? null;
                if ($st_status !== null) {
                    switch ((int)$st_status) {
                        case 0:
                            $status = 'Active';
                            $allowed = true;
                            break;
                        case 4:
                            $status = 'Graduated';
                            $allowed = false;
                            $reason = 'Graduated student status. New course enrolments are restricted.';
                            break;
                        case 5:
                            $status = 'Suspended';
                            $allowed = false;
                            $reason = 'Account suspended due to outstanding dues or academic hold.';
                            break;
                        case 6:
                            $status = 'Inactive';
                            $allowed = false;
                            $reason = 'Inactive student account. Please contact the admissions/accounts office.';
                            break;
                        case 7:
                            $status = 'Dismissed';
                            $allowed = false;
                            $reason = 'Dismissed student status.';
                            break;
                        case 8:
                            $status = 'Dropped';
                            $allowed = false;
                            $reason = 'Dropped student status.';
                            break;
                        default:
                            $status = 'Active';
                            $allowed = true;
                    }
                }
            }
        }

        $res = [
            'allowed' => $allowed,
            'reason' => $reason,
            'status' => $status,
            'due' => $remaining_dues !== null ? $remaining_dues : 0.0
        ];

        // Store in session cache with timestamp.
        $SESSION->$cache_key = [
            'time' => time(),
            'data' => $res
        ];

        return $res;
    }

    public function sync_api_students_to_moodle_db($students, $program_id = '', $batch_id = '') {
        if (empty($students) || !is_array($students)) {
            return [];
        }

        $synced = [];
        foreach ($students as $std) {
            $res = $this->sync_or_create_student_user($std, $program_id, $batch_id);
            if ($res) {
                $synced[] = $res;
            }
        }
        return $synced;
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
                $raw_un = strtolower(trim($st_obj->username ?? ($stud_id ? str_replace(['/', ' '], ['', ''], $stud_id) : '')));
                if (empty($raw_un)) continue;
                $orig_un = explode('@', $raw_un)[0];
                $moodle_username = $orig_un . '@student.wub.edu.bd';

                $email = strtolower(trim($st_obj->email ?? $st_obj->university_email ?? ($orig_un ? $orig_un . '@student.wub.edu.bd' : '')));
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $email = $moodle_username;
                }

                $st_obj->username = $moodle_username;
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
		$tempdir = make_temp_directory('local_mass_enroll');
		$tempfile = $tempdir . '/export_' . $USER->id . '_' . time() . '.xlsx';
		$writer = new Xlsx($spreadsheet);
		$writer->save($tempfile);

		// Store in Moodle file storage securely.
		$fs = get_file_storage();
		$syscontext = context_system::instance();
		$fileinfo = [
			'contextid' => $syscontext->id,
			'component' => 'local_mass_enroll',
			'filearea' => 'export',
			'itemid' => (int)$USER->id,
			'filepath' => '/',
			'filename' => 'enrolment_records_' . $USER->id . '.xlsx',
			'timecreated' => time(),
			'timemodified' => time(),
			'userid' => (int)$USER->id,
		];
		// Clean up existing old exports for this user.
		$fs->delete_area_files($syscontext->id, 'local_mass_enroll', 'export', (int)$USER->id);
		$stored_file = $fs->create_file_from_pathname($fileinfo, $tempfile);
		@unlink($tempfile);
		return $stored_file;
	}
}
