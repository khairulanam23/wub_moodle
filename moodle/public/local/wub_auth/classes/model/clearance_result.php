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

defined('MOODLE_INTERNAL') || die();

/**
 * Financial Clearance State Model.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clearance_result {
    public const STATE_CLEARED = 'CLEARED';
    public const STATE_RESTRICTED = 'RESTRICTED';
    public const STATE_WAIVED = 'WAIVED';
    public const STATE_EXEMPT = 'EXEMPT';
    public const STATE_PROGRAM_EXEMPT = 'PROGRAM_EXEMPT';
    public const STATE_DEPARTMENT_EXEMPT = 'DEPARTMENT_EXEMPT';
    public const STATE_UMS_UNAVAILABLE = 'UMS_UNAVAILABLE';
    public const STATE_UNKNOWN = 'UNKNOWN';

    public const RESTRICTION_NONE = 'NONE';
    public const RESTRICTION_DUE = 'DUE_RESTRICTION';
    public const RESTRICTION_UMS_UNAVAILABLE = 'UMS_UNAVAILABLE';
    public const RESTRICTION_NO_RECORD = 'NO_FINANCIAL_RECORD';

    /** @var string Clearance state */
    protected string $state;
    /** @var bool Whether institutional access is permitted */
    protected bool $allowed;
    /** @var float Calculated net due */
    protected float $netdue;
    /** @var float Raw total due reported by UMS */
    protected float $rawdue;
    /** @var float Monthly installment */
    protected float $monthlyinstallment;
    /** @var float Allowable threshold */
    protected float $threshold;
    /** @var string|null Program ID */
    protected ?string $programid;
    /** @var bool Whether program is exempt until cutoff */
    protected bool $isexemptprogram;
    /** @var string Explanation / notice */
    protected string $reason;
    /** @var waiver_record|null Active waiver if any */
    protected ?waiver_record $waiver;
    /** @var bool Whether result came from cache */
    protected bool $cached;
    /** @var int|null Expiry timestamp (for waivers and temporary exemptions) */
    protected ?int $expiresat;
    /** @var string Restriction type identifier */
    protected string $restrictiontype;

    public function __construct(
        string $state,
        bool $allowed,
        float $netdue = 0.0,
        float $rawdue = 0.0,
        float $monthlyinstallment = 0.0,
        float $threshold = 100.0,
        ?string $programid = null,
        bool $isexemptprogram = false,
        string $reason = '',
        ?waiver_record $waiver = null,
        bool $cached = false,
        ?int $expiresat = null,
        ?string $restrictiontype = null
    ) {
        $this->state = $state;
        $this->allowed = $allowed;
        $this->netdue = $netdue;
        $this->rawdue = $rawdue;
        $this->monthlyinstallment = $monthlyinstallment;
        $this->threshold = $threshold;
        $this->programid = $programid;
        $this->isexemptprogram = $isexemptprogram;
        $this->reason = $reason;
        $this->waiver = $waiver;
        $this->cached = $cached;
        $this->expiresat = $expiresat;
        $this->restrictiontype = $restrictiontype ?? ($allowed ? self::RESTRICTION_NONE : self::RESTRICTION_DUE);
    }

    public static function cleared(
        float $netdue,
        float $rawdue,
        float $monthlyinstallment,
        float $threshold,
        ?string $programid = null,
        bool $isexempt = false,
        bool $cached = false
    ): self {
        return new self(
            self::STATE_CLEARED,
            true,
            $netdue,
            $rawdue,
            $monthlyinstallment,
            $threshold,
            $programid,
            $isexempt,
            'Account is financially cleared.',
            null,
            $cached
        );
    }

    public static function restricted(
        float $netdue,
        float $rawdue,
        float $monthlyinstallment,
        float $threshold,
        ?string $programid = null,
        string $reason = 'Outstanding dues exceed the institutional threshold.',
        bool $cached = false
    ): self {
        return new self(
            self::STATE_RESTRICTED,
            false,
            $netdue,
            $rawdue,
            $monthlyinstallment,
            $threshold,
            $programid,
            false,
            $reason,
            null,
            $cached
        );
    }

    public static function waived(waiver_record $waiver, float $netdue = 0.0, bool $cached = false): self {
        return new self(
            self::STATE_WAIVED,
            true,
            $netdue,
            0.0,
            0.0,
            100.0,
            null,
            false,
            'Special permission waiver active until ' . userdate($waiver->get_timeend()),
            $waiver,
            $cached,
            $waiver->get_timeend(),
            self::RESTRICTION_NONE
        );
    }

    public static function exempt(string $role): self {
        return new self(
            self::STATE_EXEMPT,
            true,
            0.0,
            0.0,
            0.0,
            100.0,
            null,
            false,
            "Role '$role' is exempt from financial dues check.",
            null,
            false,
            null,
            self::RESTRICTION_NONE
        );
    }

    public static function program_exempt(
        $programid,
        ?int $expiresat = null,
        string $reason = '',
        bool $cached = false
    ): self {
        $expirytext = $expiresat ? ' until ' . userdate($expiresat) : '';
        $msg = $reason ?: "Program '$programid' is temporarily exempt from payment restriction$expirytext.";
        return new self(
            self::STATE_PROGRAM_EXEMPT,
            true,
            0.0,
            0.0,
            0.0,
            100.0,
            (string)$programid,
            true,
            $msg,
            null,
            $cached,
            $expiresat,
            self::RESTRICTION_NONE
        );
    }

    public static function department_exempt(
        string $department,
        ?int $expiresat = null,
        string $reason = '',
        bool $cached = false
    ): self {
        $expirytext = $expiresat ? ' until ' . userdate($expiresat) : '';
        $msg = $reason ?: "Department '$department' is temporarily exempt from payment restriction$expirytext.";
        return new self(
            self::STATE_DEPARTMENT_EXEMPT,
            true,
            0.0,
            0.0,
            0.0,
            100.0,
            null,
            false,
            $msg,
            null,
            $cached,
            $expiresat,
            self::RESTRICTION_NONE
        );
    }

    public static function ums_unavailable(
        bool $allowbyoutagepolicy,
        string $message,
        string $restrictiontype = self::RESTRICTION_UMS_UNAVAILABLE
    ): self {
        return new self(
            self::STATE_UMS_UNAVAILABLE,
            $allowbyoutagepolicy,
            0.0,
            0.0,
            0.0,
            100.0,
            null,
            false,
            $message,
            null,
            false,
            null,
            $allowbyoutagepolicy ? self::RESTRICTION_NONE : $restrictiontype
        );
    }

    public function is_allowed(): bool {
        return $this->allowed;
    }

    public function get_state(): string {
        return $this->state;
    }

    public function get_restriction_type(): string {
        return $this->restrictiontype;
    }

    public function get_expires_at(): ?int {
        return $this->expiresat;
    }

    public function get_net_due(): float {
        return $this->netdue;
    }

    public function get_raw_due(): float {
        return $this->rawdue;
    }

    public function get_monthly_installment(): float {
        return $this->monthlyinstallment;
    }

    public function get_threshold(): float {
        return $this->threshold;
    }

    public function get_program_id(): ?string {
        return $this->programid;
    }

    public function is_exempt_program(): bool {
        return $this->isexemptprogram;
    }

    public function get_reason(): string {
        return $this->reason;
    }

    public function get_waiver(): ?waiver_record {
        return $this->waiver;
    }

    public function is_cached(): bool {
        return $this->cached;
    }

    /**
     * Return minimal, non-sensitive decision payload for clients and integration APIs.
     *
     * @return array
     */
    public function to_decision_array(): array {
        return [
            'allowed' => $this->allowed,
            'state' => $this->state,
            'restriction_type' => $this->get_restriction_type(),
            'reason' => $this->reason,
            'expires_at' => $this->expiresat,
        ];
    }

    public function to_array(): array {
        return [
            'state' => $this->state,
            'allowed' => $this->allowed,
            'restriction_type' => $this->get_restriction_type(),
            'expires_at' => $this->expiresat,
            'net_due' => $this->netdue,
            'raw_due' => $this->rawdue,
            'monthly_installment' => $this->monthlyinstallment,
            'threshold' => $this->threshold,
            'program_id' => $this->programid,
            'is_exempt_program' => $this->isexemptprogram,
            'reason' => $this->reason,
            'cached' => $this->cached,
        ];
    }
}
