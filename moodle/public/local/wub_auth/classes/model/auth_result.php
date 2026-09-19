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
 * Structured Authentication Result.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_result {
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    public const STATUS_NOT_PROVISIONED = 'NOT_PROVISIONED';
    public const STATUS_RESTRICTED = 'RESTRICTED';
    public const STATUS_POLICY_REQUIRED = 'POLICY_REQUIRED';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_AMBIGUOUS = 'AMBIGUOUS';
    public const STATUS_UNAUTHORIZED_PERSONA = 'UNAUTHORIZED_PERSONA';
    public const STATUS_ERROR = 'ERROR';

    /** @var string Result status */
    protected string $status;
    /** @var stdClass|null Authenticated user */
    protected ?stdClass $user;
    /** @var string User-facing or log message */
    protected string $message;
    /** @var string|null Safe redirect URL */
    protected ?string $redirecturl;
    /** @var clearance_result|null Financial clearance result */
    protected ?clearance_result $clearance;
    /** @var bool Whether policy must be accepted before dashboard */
    protected bool $policyrequired;
    /** @var string Authoritative role (student, teacher, admin) */
    protected string $role;
    /** @var bool Whether UMS fallback authentication was utilized */
    protected bool $umsfallbackused;

    public function __construct(
        string $status,
        ?stdClass $user = null,
        string $message = '',
        ?string $redirecturl = null,
        ?clearance_result $clearance = null,
        bool $policyrequired = false,
        string $role = 'student',
        bool $umsfallbackused = false
    ) {
        $this->status = $status;
        $this->user = $user;
        $this->message = $message;
        $this->redirecturl = $redirecturl;
        $this->clearance = $clearance;
        $this->policyrequired = $policyrequired;
        $this->role = $role;
        $this->umsfallbackused = $umsfallbackused;
    }

    public static function success(
        stdClass $user,
        string $role,
        ?string $redirecturl = null,
        bool $policyrequired = false,
        bool $umsfallbackused = false
    ): self {
        return new self(
            self::STATUS_SUCCESS,
            $user,
            'Authentication successful',
            $redirecturl,
            null,
            $policyrequired,
            $role,
            $umsfallbackused
        );
    }

    public static function restricted(stdClass $user, clearance_result $clearance, string $role): self {
        return new self(
            self::STATUS_RESTRICTED,
            $user,
            $clearance->get_reason(),
            '/local/wub_auth/restricted.php',
            $clearance,
            false,
            $role
        );
    }

    public static function policy_required(stdClass $user, string $role, ?string $returnurl = null): self {
        return new self(
            self::STATUS_POLICY_REQUIRED,
            $user,
            'Policy review required',
            '/local/wub_auth/policy.php',
            null,
            true,
            $role
        );
    }

    public static function not_provisioned(string $message): self {
        return new self(self::STATUS_NOT_PROVISIONED, null, $message);
    }

    public static function invalid_credentials(string $message): self {
        return new self(self::STATUS_INVALID_CREDENTIALS, null, $message);
    }

    public static function suspended(string $message): self {
        return new self(self::STATUS_SUSPENDED, null, $message);
    }

    public static function ambiguous(string $message): self {
        return new self(self::STATUS_AMBIGUOUS, null, $message);
    }

    public static function unauthorized_persona(string $message): self {
        return new self(self::STATUS_UNAUTHORIZED_PERSONA, null, $message);
    }

    public static function error(string $message): self {
        return new self(self::STATUS_ERROR, null, $message);
    }

    public function is_success(): bool {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function is_restricted(): bool {
        return $this->status === self::STATUS_RESTRICTED;
    }

    public function is_policy_required(): bool {
        return $this->policyrequired;
    }

    public function get_status(): string {
        return $this->status;
    }

    public function get_user(): ?stdClass {
        return $this->user;
    }

    public function get_message(): string {
        return $this->message;
    }

    public function get_redirect_url(): ?string {
        return $this->redirecturl;
    }

    public function get_clearance(): ?clearance_result {
        return $this->clearance;
    }

    public function get_role(): string {
        return $this->role;
    }

    public function was_ums_fallback_used(): bool {
        return $this->umsfallbackused;
    }
}
