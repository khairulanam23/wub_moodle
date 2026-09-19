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
 * Normalised UMS academic program (API #2: GET /students/programs).
 *
 * Real payload keys (verified 2026-09-14): id, title, short_name, short_title, code, main_title, is_active.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\model;

/**
 * Program value object.
 */
class ums_program {
    /** @var string UMS program id (authoritative identifier for API #3 and #4). */
    public readonly string $id;
    /** @var string Full program title, e.g. "B.Sc in Computer Science and Engineering". */
    public readonly string $title;
    /** @var string Short title, e.g. "CSE". */
    public readonly string $short_title;
    /** @var string Short name, e.g. "B.Sc in CSE". */
    public readonly string $short_name;
    /** @var string UMS program code, e.g. "03". */
    public readonly string $code;
    /** @var bool Whether UMS flags the program as active. */
    public readonly bool $is_active;

    /**
     * Constructor.
     *
     * @param string $id
     * @param string $title
     * @param string $shorttitle
     * @param string $shortname
     * @param string $code
     * @param bool $isactive
     */
    public function __construct(string $id, string $title, string $shorttitle = '', string $shortname = '', string $code = '', bool $isactive = true) {
        $this->id = trim($id);
        $this->title = trim($title) ?: $this->id;
        $this->short_title = trim($shorttitle);
        $this->short_name = trim($shortname);
        $this->code = trim($code);
        $this->is_active = $isactive;
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
            (string)($o->id ?? ''),
            (string)($o->title ?? $o->main_title ?? $o->name ?? ''),
            (string)($o->short_title ?? ''),
            (string)($o->short_name ?? ''),
            (string)($o->code ?? ''),
            !isset($o->is_active) || (string)$o->is_active === '1' || $o->is_active === true
        );
    }

    /**
     * Display label: "B.Sc in Computer Science and Engineering (CSE)".
     *
     * @return string
     */
    public function get_label(): string {
        return $this->short_title !== '' && $this->short_title !== $this->title
            ? "{$this->title} ({$this->short_title})" : $this->title;
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
            'short_title' => $this->short_title,
            'short_name' => $this->short_name,
            'code' => $this->code,
            'is_active' => $this->is_active,
            'label' => $this->get_label(),
        ];
    }

    /**
     * Get program id.
     *
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Get program name (title).
     *
     * @return string
     */
    public function get_name(): string {
        return $this->title;
    }

    /**
     * Get program title.
     *
     * @return string
     */
    public function get_title(): string {
        return $this->title;
    }

    /**
     * Get short name.
     *
     * @return string
     */
    public function get_short_name(): string {
        return $this->short_name;
    }

    /**
     * Get program code.
     *
     * @return string
     */
    public function get_code(): string {
        return $this->code;
    }
}
