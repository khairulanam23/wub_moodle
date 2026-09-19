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
 * Normalised UMS batch (API #3: GET /students/batches/{program_id}).
 *
 * Real payload keys (verified 2026-09-14): id, batch_title, shift, program_type, program_id,
 * total_no_of_students, is_active, is_open. IMPORTANT: API #4 accepts the batch TITLE (e.g. "74F"),
 * not the numeric id; both are kept here.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\model;

/**
 * Batch value object.
 */
class ums_batch {
    /** @var string Authoritative UMS batch database id (e.g. "2368"). Not accepted by API #4. */
    public readonly string $id;
    /** @var string Batch title (e.g. "74F"). This is the identifier API #4 expects. */
    public readonly string $title;
    /** @var string Owning program id. */
    public readonly string $program_id;
    /** @var string Shift, e.g. "Day" / "Evening". */
    public readonly string $shift;
    /** @var string Program type, e.g. "Regular" / "Diploma". */
    public readonly string $program_type;
    /** @var int Number of students UMS reports for the batch. */
    public readonly int $total_students;
    /** @var bool Active flag. */
    public readonly bool $is_active;
    /** @var bool Open flag. */
    public readonly bool $is_open;

    /**
     * Constructor.
     *
     * @param string $id
     * @param string $title
     * @param string $programid
     * @param string $shift
     * @param string $programtype
     * @param int $totalstudents
     * @param bool $isactive
     * @param bool $isopen
     */
    public function __construct(string $id, string $title, string $programid = '', string $shift = '', string $programtype = '',
            int $totalstudents = 0, bool $isactive = true, bool $isopen = true) {
        $this->id = trim($id);
        $this->title = trim($title);
        $this->program_id = trim($programid);
        $this->shift = trim($shift);
        $this->program_type = trim($programtype);
        $this->total_students = $totalstudents;
        $this->is_active = $isactive;
        $this->is_open = $isopen;
    }

    /**
     * Build from a raw UMS record.
     *
     * @param object|array $raw
     * @param string $programid Program the batch was requested for.
     * @return self
     */
    public static function from_raw(object|array $raw, string $programid = ''): self {
        $o = (object)$raw;
        return new self(
            (string)($o->id ?? ''),
            (string)($o->batch_title ?? $o->title ?? $o->name ?? ''),
            (string)($o->program_id ?? $programid),
            (string)($o->shift ?? ''),
            (string)($o->program_type ?? ''),
            (int)($o->total_no_of_students ?? 0),
            !isset($o->is_active) || (string)$o->is_active === '1',
            !isset($o->is_open) || (string)$o->is_open === '1'
        );
    }

    /**
     * Array form (also used for cache storage).
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'program_id' => $this->program_id,
            'shift' => $this->shift,
            'program_type' => $this->program_type,
            'total_students' => $this->total_students,
            'is_active' => $this->is_active,
            'is_open' => $this->is_open,
        ];
    }

    /**
     * Get batch id.
     *
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Get batch title.
     *
     * @return string
     */
    public function get_title(): string {
        return $this->title;
    }

    /**
     * Get program id.
     *
     * @return string
     */
    public function get_program_id(): string {
        return $this->program_id;
    }

    /**
     * Get shift.
     *
     * @return string
     */
    public function get_shift(): string {
        return $this->shift;
    }
}
