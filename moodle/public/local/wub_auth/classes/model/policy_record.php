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
 * Policy Acceptance Entity Model.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class policy_record {
    protected int $id;
    protected int $userid;
    protected ?string $deviceidentifier;
    protected string $role;
    protected string $policyversion;
    protected int $timeaccepted;
    protected ?string $userip;
    protected ?string $useragent;

    public function __construct(
        int $id,
        int $userid,
        ?string $deviceidentifier,
        string $role,
        string $policyversion,
        int $timeaccepted,
        ?string $userip = null,
        ?string $useragent = null
    ) {
        $this->id = $id;
        $this->userid = $userid;
        $this->deviceidentifier = $deviceidentifier;
        $this->role = $role;
        $this->policyversion = $policyversion;
        $this->timeaccepted = $timeaccepted;
        $this->userip = $userip;
        $this->useragent = $useragent;
    }

    public static function from_record(stdClass $record): self {
        return new self(
            (int)$record->id,
            (int)($record->userid ?? 0),
            $record->deviceidentifier ?? null,
            (string)$record->role,
            (string)($record->policyversion ?? '1.0.0'),
            (int)$record->timeaccepted,
            $record->userip ?? null,
            $record->useragent ?? null
        );
    }

    public function is_valid(int $expiryseconds, string $currentversion): bool {
        if ($this->policyversion !== $currentversion) {
            return false;
        }
        $mintime = time() - $expiryseconds;
        return $this->timeaccepted >= $mintime;
    }

    public function get_id(): int {
        return $this->id;
    }

    public function get_userid(): int {
        return $this->userid;
    }

    public function get_device_identifier(): ?string {
        return $this->deviceidentifier;
    }

    public function get_role(): string {
        return $this->role;
    }

    public function get_policy_version(): string {
        return $this->policyversion;
    }

    public function get_time_accepted(): int {
        return $this->timeaccepted;
    }
}
