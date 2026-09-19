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
 * Normalised student identity details from API #1 and API #5.
 *
 * API #1 (POST /students/multiple_username_wise_std_details, body email=<usernames>) returns
 * message.StudentDetails[] with: id, stud_id (= registration id "WUB03/.."), ugc_stud_id (16-digit UGC id, not the login),
 * username (= university e-mail whose local part is the 10-digit login), full_name, batch_id (= batch TITLE), program_id, shift, program_type,
 * semester_id, is_active, student_status ... plus many sensitive fields (password hash, dues, phones) that are
 * deliberately NOT retained by this model.
 *
 * API #5 (POST /students/reg_id_wise_multi_student_info, body ids=<10-digit usernames>) returns message[] with:
 * stud_id (= registration id), university_email (= 10-digit username, despite the name), email, department_id,
 * program_id, batch_id (= batch TITLE).
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\model;

/**
 * Identity details value object (whitelisted fields only).
 */
class ums_student_details {
    /** @var string 10-digit UMS username / Moodle username. */
    public readonly string $username;
    /** @var string Registration id, e.g. "WUB03/26/74/5565". */
    public readonly string $reg_id;
    /** @var string University e-mail (only when UMS supplies an address). */
    public readonly string $university_email;
    /** @var string Full name. */
    public readonly string $full_name;
    /** @var string Batch title. */
    public readonly string $batch_title;
    /** @var string Program id. */
    public readonly string $program_id;
    /** @var string Department id. */
    public readonly string $department_id;
    /** @var string Shift. */
    public readonly string $shift;
    /** @var string Program type. */
    public readonly string $program_type;
    /** @var bool Active flag when supplied. */
    public readonly bool $is_active;

    /**
     * Constructor.
     *
     * @param string $username
     * @param string $regid
     * @param string $universityemail
     * @param string $fullname
     * @param string $batchtitle
     * @param string $programid
     * @param string $departmentid
     * @param string $shift
     * @param string $programtype
     * @param bool $isactive
     */
    public function __construct(string $username, string $regid = '', string $universityemail = '', string $fullname = '',
            string $batchtitle = '', string $programid = '', string $departmentid = '', string $shift = '',
            string $programtype = '', bool $isactive = true) {
        $this->username = trim($username);
        $this->reg_id = trim($regid);
        $this->university_email = trim($universityemail);
        $this->full_name = trim($fullname);
        $this->batch_title = trim($batchtitle);
        $this->program_id = trim($programid);
        $this->department_id = trim($departmentid);
        $this->shift = trim($shift);
        $this->program_type = trim($programtype);
        $this->is_active = $isactive;
    }

    /**
     * Build from an API #1 StudentDetails record.
     *
     * @param object|array $raw
     * @return self
     */
    public static function from_api1(object|array $raw): self {
        $o = (object)$raw;
        // "username" carries the university e-mail; its local part is the 10-digit login. "ugc_stud_id" is the
        // 16-digit UGC identifier and is NOT the login.
        $rawusername = (string)($o->username ?? '');
        $email = str_contains($rawusername, '@') ? $rawusername : (string)($o->email ?? '');
        $username = $rawusername !== '' ? explode('@', $rawusername)[0] : '';
        return new self(
            $username,
            (string)($o->stud_id ?? ''),
            str_contains($email, '@') ? $email : '',
            (string)($o->full_name ?? ''),
            (string)($o->batch_id ?? ''),
            (string)($o->program_id ?? ''),
            (string)($o->department_id ?? ''),
            (string)($o->shift ?? ''),
            (string)($o->program_type ?? ''),
            !isset($o->is_active) || (string)$o->is_active === '1'
        );
    }

    /**
     * Build from an API #5 record.
     *
     * @param object|array $raw
     * @return self
     */
    public static function from_api5(object|array $raw): self {
        $o = (object)$raw;
        $ue = (string)($o->university_email ?? '');
        $username = str_contains($ue, '@') ? explode('@', $ue)[0] : $ue;
        $email = str_contains($ue, '@') ? $ue : (string)($o->email ?? '');
        return new self(
            $username,
            (string)($o->stud_id ?? ''),
            str_contains($email, '@') ? $email : '',
            (string)($o->full_name ?? ''),
            (string)($o->batch_id ?? $o->batch ?? ''),
            (string)($o->program_id ?? ''),
            (string)($o->department_id ?? '')
        );
    }

    /**
     * Array form (cache-safe, no sensitive data).
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'username' => $this->username,
            'reg_id' => $this->reg_id,
            'university_email' => $this->university_email,
            'full_name' => $this->full_name,
            'batch_title' => $this->batch_title,
            'program_id' => $this->program_id,
            'department_id' => $this->department_id,
            'shift' => $this->shift,
            'program_type' => $this->program_type,
            'is_active' => $this->is_active,
        ];
    }
}
