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

namespace local_wub_auth\model;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Authoritative Student Waiver Entity Model.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class waiver_record {
    protected int $id;
    protected int $userid;
    protected int $status;
    protected int $timestart;
    protected int $timeend;
    protected int $grantedby;
    protected string $reason;
    protected int $timecreated;
    protected int $timemodified;

    public function __construct(
        int $id,
        int $userid,
        int $status,
        int $timestart,
        int $timeend,
        int $grantedby = 0,
        string $reason = '',
        int $timecreated = 0,
        int $timemodified = 0
    ) {
        $this->id = $id;
        $this->userid = $userid;
        $this->status = $status;
        $this->timestart = $timestart;
        $this->timeend = $timeend;
        $this->grantedby = $grantedby;
        $this->reason = $reason;
        $this->timecreated = $timecreated;
        $this->timemodified = $timemodified;
    }

    public static function from_record(stdClass $record): self {
        return new self(
            (int)$record->id,
            (int)$record->userid,
            (int)$record->status,
            (int)($record->timestart ?? 0),
            (int)$record->timeend,
            (int)($record->grantedby ?? 0),
            (string)($record->reason ?? ''),
            (int)($record->timecreated ?? 0),
            (int)($record->timemodified ?? 0)
        );
    }

    public function is_valid(): bool {
        return ($this->status === 1) && ($this->timeend >= time());
    }

    public function is_expired(): bool {
        return time() > $this->timeend;
    }

    public function is_revoked(): bool {
        return $this->status !== 1;
    }

    public function get_id(): int {
        return $this->id;
    }

    public function get_userid(): int {
        return $this->userid;
    }

    public function get_status(): int {
        return $this->status;
    }

    public function get_timestart(): int {
        return $this->timestart;
    }

    public function get_timeend(): int {
        return $this->timeend;
    }

    public function get_grantedby(): int {
        return $this->grantedby;
    }

    public function get_reason(): string {
        return $this->reason;
    }

    public function get_timecreated(): int {
        return $this->timecreated;
    }

    public function get_timemodified(): int {
        return $this->timemodified;
    }
}
