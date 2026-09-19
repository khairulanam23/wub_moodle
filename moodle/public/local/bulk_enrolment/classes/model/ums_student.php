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
 * Normalised UMS roster student (API #4: GET /students/enroll_student_list_program_batch_wise/{program}/{batch_title}).
 *
 * Real payload keys (verified 2026-09-14): username (10-digit student number), regId (e.g. "WUB03/26/74/5565"),
 * full_name, mother_batch, current_batch, program_name, program_id, enrollCourseDetails[{title, courseCode, credit}].
 * The roster does NOT contain an e-mail address or a separate student id; nothing is fabricated here.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\model;

/**
 * Roster student value object.
 */
class ums_student {
    /** @var string UMS username = 10-digit student number = Moodle username convention. */
    public readonly string $username;
    /** @var string Registration id, e.g. "WUB03/26/74/5565". */
    public readonly string $reg_id;
    /** @var string Full name as recorded by UMS. */
    public readonly string $full_name;
    /** @var string Mother batch title, e.g. "74F". */
    public readonly string $mother_batch;
    /** @var string Current batch title (often empty). */
    public readonly string $current_batch;
    /** @var string Program name. */
    public readonly string $program_name;
    /** @var string Program id. */
    public readonly string $program_id;
    /** @var ums_course[] Registered courses (previous/current) reported by UMS. */
    public readonly array $courses;
    /** @var string Email address. */
    public readonly string $email;

    /**
     * Constructor.
     *
     * @param string $username
     * @param string $regid
     * @param string $fullname
     * @param string $motherbatch
     * @param string $currentbatch
     * @param string $programname
     * @param string $programid
     * @param ums_course[] $courses
     * @param string $email
     */
    public function __construct(string $username, string $regid = '', string $fullname = '', string $motherbatch = '',
            string $currentbatch = '', string $programname = '', string $programid = '', array $courses = [], string $email = '') {
        $this->username = trim($username);
        $this->reg_id = trim($regid);
        $this->full_name = trim($fullname);
        $this->mother_batch = trim($motherbatch);
        $this->current_batch = trim($currentbatch);
        $this->program_name = trim($programname);
        $this->program_id = trim($programid);
        $this->courses = $courses;
        $this->email = trim($email);
    }

    /**
     * Build from a raw UMS roster record.
     *
     * @param object|array $raw
     * @return self
     */
    public static function from_raw(object|array $raw): self {
        $o = (object)$raw;
        $courses = [];
        foreach ((array)($o->enrollCourseDetails ?? []) as $rc) {
            $c = ums_course::from_raw($rc);
            if ($c->course_code !== '') {
                $courses[] = $c;
            }
        }
        return new self(
            (string)($o->username ?? ''),
            (string)($o->regId ?? $o->reg_id ?? ''),
            (string)($o->full_name ?? ''),
            (string)($o->mother_batch ?? ''),
            (string)($o->current_batch ?? ''),
            (string)($o->program_name ?? ''),
            (string)($o->program_id ?? ''),
            $courses,
            (string)($o->email ?? '')
        );
    }

    /**
     * Effective batch: current batch when set, otherwise mother batch.
     *
     * @return string
     */
    public function get_batch(): string {
        return $this->current_batch !== '' ? $this->current_batch : $this->mother_batch;
    }

    /**
     * Comma-separated course codes.
     *
     * @return string
     */
    public function get_course_codes_summary(): string {
        return implode(', ', array_unique(array_map(fn($c) => $c->course_code, $this->courses)));
    }

    /**
     * Whether UMS reports any registered course.
     *
     * @return bool
     */
    public function has_courses(): bool {
        return !empty($this->courses);
    }

    /**
     * Array form (also used for cache storage).
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'username' => $this->username,
            'reg_id' => $this->reg_id,
            'full_name' => $this->full_name,
            'mother_batch' => $this->mother_batch,
            'current_batch' => $this->current_batch,
            'program_name' => $this->program_name,
            'program_id' => $this->program_id,
            'courses' => array_map(fn($c) => $c->to_array(), $this->courses),
            'email' => $this->email,
        ];
    }

    /**
     * Rebuild from the array form.
     *
     * @param array $a
     * @return self
     */
    public static function from_array(array $a): self {
        $courses = array_map(fn($c) => new ums_course($c['course_code'] ?? '', $c['title'] ?? '', $c['credit'] ?? '', $c['semester'] ?? ''), $a['courses'] ?? []);
        return new self($a['username'] ?? '', $a['reg_id'] ?? '', $a['full_name'] ?? '', $a['mother_batch'] ?? '',
            $a['current_batch'] ?? '', $a['program_name'] ?? '', $a['program_id'] ?? '', $courses, $a['email'] ?? '');
    }

    /**
     * Get username.
     *
     * @return string
     */
    public function get_username(): string {
        return $this->username;
    }

    /**
     * Get full name.
     *
     * @return string
     */
    public function get_full_name(): string {
        return $this->full_name;
    }

    /**
     * Get registration id.
     *
     * @return string
     */
    public function get_reg_id(): string {
        return $this->reg_id;
    }

    /**
     * Get email.
     *
     * @return string
     */
    public function get_email(): string {
        return $this->email;
    }

    /**
     * Get courses.
     *
     * @return ums_course[]
     */
    public function get_courses(): array {
        return $this->courses;
    }

    /**
     * Get mother batch.
     *
     * @return string
     */
    public function get_mother_batch(): string {
        return $this->mother_batch;
    }

    /**
     * Get current batch.
     *
     * @return string
     */
    public function get_current_batch(): string {
        return $this->current_batch;
    }

    /**
     * Get program name.
     *
     * @return string
     */
    public function get_program_name(): string {
        return $this->program_name;
    }

    /**
     * Get program id.
     *
     * @return string
     */
    public function get_program_id(): string {
        return $this->program_id;
    }
}
