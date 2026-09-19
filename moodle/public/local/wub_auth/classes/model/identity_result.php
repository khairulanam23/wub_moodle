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
 * Structured Identity Resolution Result.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class identity_result {
    public const STATUS_FOUND = 'FOUND';
    public const STATUS_NOT_FOUND = 'NOT_FOUND';
    public const STATUS_AMBIGUOUS = 'AMBIGUOUS';
    public const STATUS_INVALID = 'INVALID';

    public const MATCH_USERNAME = 'USERNAME';
    public const MATCH_EMAIL = 'EMAIL';
    public const MATCH_IDNUMBER = 'IDNUMBER';
    public const MATCH_UMS = 'UMS';

    /** @var string Status code */
    protected string $status;
    /** @var stdClass|null Resolved Moodle user record */
    protected ?stdClass $user;
    /** @var string|null Match strategy */
    protected ?string $matchtype;
    /** @var string Normalized query identifier */
    protected string $identifier;
    /** @var string|null Resolved canonical username */
    protected ?string $resolvedusername;
    /** @var array|null Safe UMS student metadata */
    protected ?array $umsdata;
    /** @var string|null Optional error message */
    protected ?string $errormessage;

    public function __construct(
        string $status,
        string $identifier,
        ?stdClass $user = null,
        ?string $matchtype = null,
        ?string $resolvedusername = null,
        ?array $umsdata = null,
        ?string $errormessage = null
    ) {
        $this->status = $status;
        $this->identifier = $identifier;
        $this->user = $user;
        $this->matchtype = $matchtype;
        $this->resolvedusername = $resolvedusername;
        $this->umsdata = $umsdata;
        $this->errormessage = $errormessage;
    }

    public static function found(stdClass $user, string $matchtype, string $identifier, ?array $umsdata = null): self {
        return new self(self::STATUS_FOUND, $identifier, $user, $matchtype, $user->username, $umsdata);
    }

    public static function not_found(string $identifier, ?array $umsdata = null): self {
        return new self(self::STATUS_NOT_FOUND, $identifier, null, null, null, $umsdata);
    }

    public static function ambiguous(string $identifier, string $message): self {
        return new self(self::STATUS_AMBIGUOUS, $identifier, null, null, null, null, $message);
    }

    public static function invalid(string $identifier, string $message): self {
        return new self(self::STATUS_INVALID, $identifier, null, null, null, null, $message);
    }

    public function is_found(): bool {
        return $this->status === self::STATUS_FOUND && !empty($this->user);
    }

    public function is_ambiguous(): bool {
        return $this->status === self::STATUS_AMBIGUOUS;
    }

    public function get_status(): string {
        return $this->status;
    }

    public function get_user(): ?stdClass {
        return $this->user;
    }

    public function get_match_type(): ?string {
        return $this->matchtype;
    }

    public function get_identifier(): string {
        return $this->identifier;
    }

    public function get_resolved_username(): ?string {
        return $this->resolvedusername ?? ($this->user ? $this->user->username : null);
    }

    public function get_ums_data(): ?array {
        return $this->umsdata;
    }

    public function get_error_message(): ?string {
        return $this->errormessage;
    }
}
