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
 * Normalised UMS registered course (element of enrollCourseDetails in API #4).
 *
 * Real payload keys (verified 2026-09-14): title, courseCode, credit. Semester is not provided by UMS.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\model;

/**
 * Course value object.
 */
class ums_course {
    /** @var string Course code, e.g. "CSE 06131210". */
    public readonly string $course_code;
    /** @var string Course title. */
    public readonly string $title;
    /** @var string Credit, e.g. "3". */
    public readonly string $credit;
    /** @var string Semester, when UMS provides one (currently empty). */
    public readonly string $semester;

    /**
     * Constructor.
     *
     * @param string $coursecode
     * @param string $title
     * @param string $credit
     * @param string $semester
     */
    public function __construct(string $coursecode, string $title = '', string $credit = '', string $semester = '') {
        $this->course_code = trim($coursecode);
        $this->title = trim($title) ?: $this->course_code;
        $this->credit = trim($credit);
        $this->semester = trim($semester);
    }

    /**
     * Build from a raw UMS record.
     *
     * @param object|array $raw
     * @return self
     */
    public static function from_raw(object|array $raw): self {
        $o = (object)$raw;
        return new self(
            (string)($o->courseCode ?? $o->course_code ?? ''),
            (string)($o->title ?? $o->course_title ?? ''),
            (string)($o->credit ?? $o->credits ?? ''),
            (string)($o->semester ?? $o->semester_name ?? '')
        );
    }

    /**
     * Array form.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'course_code' => $this->course_code,
            'title' => $this->title,
            'credit' => $this->credit,
            'semester' => $this->semester,
        ];
    }

    /**
     * Get course code.
     *
     * @return string
     */
    public function get_code(): string {
        return $this->course_code;
    }

    /**
     * Get course title.
     *
     * @return string
     */
    public function get_title(): string {
        return $this->title;
    }

    /**
     * Get course credit.
     *
     * @return string
     */
    public function get_credit(): string {
        return $this->credit;
    }

    /**
     * Get semester.
     *
     * @return string
     */
    public function get_semester(): string {
        return $this->semester;
    }
}
