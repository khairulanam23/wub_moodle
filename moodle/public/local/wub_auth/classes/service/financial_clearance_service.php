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

namespace local_wub_auth\service;

use cache;
use context_system;
use local_wub_auth\api_client;
use local_wub_auth\exception\ums_exception;
use local_wub_auth\model\clearance_result;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Institutional Financial Clearance Service.
 *
 * Implements the exact audited WUB financial business rules:
 * - Admin and teacher role exemption
 * - Dedicated authoritative waiver checks
 * - MUC caching (10-min TTL)
 * - Net due formula: max(0, remaining_deus - 100 - (day <= 15 ? installment : 0))
 * - Annual September 10 exemption for programs [324, 351, 359, 360, 363, 352, 361, 362, 313]
 * - Institutional threshold: 100 BDT
 * - Fail-closed outage policy by default
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class financial_clearance_service {
    public const DEFAULT_DUE_THRESHOLD = 100.0;
    public const DEFAULT_BUFFER_DEDUCTION = 100.0;
    public const DEFAULT_INSTALLMENT_DAY_CUTOFF = 15;
    public const DEFAULT_EXEMPT_PROGRAMS = [324, 351, 359, 360, 363, 352, 361, 362, 313];
    public const DEFAULT_EXEMPT_DATE_CUTOFF = '09-10';

    protected api_client $apiclient;
    protected waiver_service $waiverservice;
    protected role_resolver $roleresolver;

    public function __construct(
        ?api_client $apiclient = null,
        ?waiver_service $waiverservice = null,
        ?role_resolver $roleresolver = null
    ) {
        $this->apiclient = $apiclient ?? new api_client();
        $this->waiverservice = $waiverservice ?? new waiver_service();
        $this->roleresolver = $roleresolver ?? new role_resolver();
    }

    /**
     * Parse exemption cutoff date (supports YYYY-MM-DD or MM-DD).
     * Returns Unix timestamp for end of that day (23:59:59) or null if invalid.
     *
     * @param string $cutoff
     * @return int|null
     */
    public function parse_exemption_cutoff_timestamp(string $cutoff): ?int {
        $raw = trim($cutoff);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $ts = strtotime($raw . ' 23:59:59');
            return ($ts !== false) ? $ts : null;
        }

        if (preg_match('/^\d{2}-\d{2}$/', $raw)) {
            $date = date('Y') . '-' . $raw;
            $ts = strtotime($date . ' 23:59:59');
            return ($ts !== false) ? $ts : null;
        }

        $ts = strtotime($raw);
        return ($ts !== false) ? $ts : null;
    }

    /**
     * Check whether an exemption cutoff timestamp is still active (current_date <= exemption_until).
     *
     * @param int|null $cutoffTimestamp
     * @return bool
     */
    public function is_exemption_active(?int $cutoffTimestamp): bool {
        if ($cutoffTimestamp === null) {
            return false;
        }
        return time() <= $cutoffTimestamp;
    }

    /**
     * Map Moodle department text strings to known WUB UMS program IDs.
     *
     * @param string $departmentName
     * @return int|null
     */
    public function map_department_name_to_program_id(string $departmentName): ?int {
        $norm = strtolower(trim($departmentName));
        if ($norm === '') {
            return null;
        }

        $map = [
            'computer science and engineering' => 300,
            'b.sc in computer science and engineering' => 300,
            'cse' => 300,
            'bachelor of pharmacy' => 324,
            'pharmacy' => 324,
            'bachelor of architecture' => 351,
            'architecture' => 351,
            'bsc. in mechanical engineering' => 359,
            'mechanical engineering' => 359,
            'me' => 359,
            'bsc. in automobile engineering' => 360,
            'automobile engineering' => 360,
            'bachelor of laws (bi semester)' => 363,
            'bachelor of laws' => 363,
            'law' => 363,
            'llb' => 363,
            'masters in public health' => 352,
            'public health' => 352,
            'mph' => 352,
            'master of pharmacy (m pharm)-general group' => 361,
            'master of pharmacy (m pharm)-thesis group' => 362,
            'bachelor of education' => 313,
            'education' => 313,
            'bba' => 301,
            'bachelor of business administration' => 301,
            'business administration' => 301,
            'mba' => 302,
            'master of business administration' => 302,
            'civil engineering' => 304,
            'bsc. in civil engineering' => 304,
            'electrical and electronic engineering' => 305,
            'bsc. in electrical and electronic engineering' => 305,
            'eee' => 305,
            'english' => 308,
            'ba in english' => 308,
            'economics' => 310,
            'bss in economics' => 310,
        ];

        return $map[$norm] ?? null;
    }

    /**
     * Map WUB UMS program ID to department ID.
     *
     * @param int $programId
     * @return string|null
     */
    public function map_program_id_to_department_id(int $programId): ?string {
        $map = [
            300 => '143', // CSE
            324 => '149', // Pharmacy
            351 => '168', // Architecture
            359 => '145', // Mechanical
            360 => '145', // Automobile
            363 => '146', // Law
            352 => '173', // MPH
            361 => '149', // M Pharm General
            362 => '149', // M Pharm Thesis
            313 => '148', // Education
            301 => '144', // Business
            302 => '144', // Business
            304 => '147', // Civil
            305 => '150', // EEE
            308 => '148', // Arts/Humanities
            310 => '148', // Arts/Humanities
        ];

        return $map[$programId] ?? null;
    }

    /**
     * Resolve academic program ID and department context for a student.
     *
     * @param stdClass $user
     * @param array|null $paymentinfo
     * @return array ['program_id' => ?int, 'department_id' => ?string, 'department_name' => ?string]
     */
    public function resolve_academic_context(stdClass $user, ?array $paymentinfo = null): array {
        $programid = null;
        $deptid = null;
        $deptname = !empty($user->department) ? trim((string)$user->department) : null;

        if (!empty($paymentinfo['program_id'])) {
            $programid = (int)$paymentinfo['program_id'];
        }

        if (!empty($paymentinfo['department_id']) || !empty($paymentinfo['departments_id'])) {
            $deptid = (string)($paymentinfo['department_id'] ?? $paymentinfo['departments_id']);
        }

        if ($programid === null && $deptname !== null) {
            if (is_numeric($deptname)) {
                $programid = (int)$deptname;
            } else {
                $programid = $this->map_department_name_to_program_id($deptname);
            }
        }

        if ($deptid === null && $programid !== null) {
            $deptid = $this->map_program_id_to_department_id($programid);
        }

        return [
            'program_id' => $programid,
            'department_id' => $deptid,
            'department_name' => $deptname,
        ];
    }

    /**
     * Check financial clearance for a Moodle user.
     *
     * @param stdClass $user Moodle user object.
     * @param bool $skipcache Force fresh UMS evaluation.
     * @return clearance_result
     */
    public function check_clearance(stdClass $user, bool $skipcache = false): clearance_result {
        $userid = (int)$user->id;

        // 1. Check if financial access control is enabled site-wide
        $enabled = (bool)$this->apiclient->get_config('financial_control_enabled', 1);
        if (!$enabled) {
            return clearance_result::cleared(0.0, 0.0, 0.0, 100.0, null, false);
        }

        // 2. Administrators & teachers are exempt
        if (is_siteadmin($user)) {
            return clearance_result::exempt('admin');
        }

        $syscontext = context_system::instance();
        if (has_capability('local/wub_auth:bypassfinancial', $syscontext, $user)) {
            return clearance_result::exempt('bypass_capability');
        }

        $primaryrole = $this->roleresolver->resolve_primary_role($user);
        if ($primaryrole === role_resolver::ROLE_TEACHER || $primaryrole === role_resolver::ROLE_ADMIN) {
            return clearance_result::exempt($primaryrole);
        }

        // 3. Active Special Pardon / Waiver Check (Authoritative DB)
        $waiver = $this->waiverservice->get_active_waiver($userid);
        if ($waiver && $waiver->is_valid()) {
            return clearance_result::waived($waiver);
        }

        // 4. Resolve academic context (program ID & department)
        $context = $this->resolve_academic_context($user);
        $programid = $context['program_id'];
        $deptid = $context['department_id'];
        $deptname = $context['department_name'];

        // 5. Active Program Exemption Check (current_date <= exemption_until)
        if ($programid !== null) {
            $exemptprograms = $this->get_exempt_program_ids();
            if (in_array($programid, $exemptprograms, true)) {
                $cutoffraw = (string)$this->apiclient->get_config('exempt_date_cutoff', self::DEFAULT_EXEMPT_DATE_CUTOFF);
                $cutoffts = $this->parse_exemption_cutoff_timestamp($cutoffraw);
                if ($this->is_exemption_active($cutoffts)) {
                    return clearance_result::program_exempt(
                        $programid,
                        $cutoffts,
                        "Program '$programid' is temporarily exempt from payment restriction until " . userdate($cutoffts) . "."
                    );
                }
            }
        }

        // 6. Active Department Exemption Check (current_date <= exemption_until)
        $exemptdepartments = $this->get_exempt_departments();
        if (!empty($exemptdepartments) && ($deptid !== null || $deptname !== null)) {
            $matchesDept = false;
            foreach ($exemptdepartments as $ed) {
                if (($deptid !== null && strcasecmp($deptid, $ed) === 0) ||
                    ($deptname !== null && strcasecmp($deptname, $ed) === 0)) {
                    $matchesDept = true;
                    break;
                }
            }
            if ($matchesDept) {
                $deptcutoffraw = (string)$this->apiclient->get_config('exempt_department_date_cutoff', '');
                if ($deptcutoffraw === '') {
                    $deptcutoffraw = (string)$this->apiclient->get_config('exempt_date_cutoff', self::DEFAULT_EXEMPT_DATE_CUTOFF);
                }
                $deptcutoffts = $this->parse_exemption_cutoff_timestamp($deptcutoffraw);
                if ($this->is_exemption_active($deptcutoffts)) {
                    $label = $deptname ?: ($deptid ?: 'Department');
                    return clearance_result::department_exempt(
                        $label,
                        $deptcutoffts,
                        "Department '$label' is temporarily exempt from payment restriction until " . userdate($deptcutoffts) . "."
                    );
                }
            }
        }

        // 7. MUC Application Cache Check (10-minute TTL)
        if (!$skipcache) {
            $cachedresult = $this->get_from_cache($userid);
            if ($cachedresult !== null) {
                return $cachedresult;
            }
        }

        // 8. Query UMS payment information
        $cleanusername = explode('@', trim($user->username))[0];
        $paymentinfo = null;

        try {
            $paymentinfo = $this->apiclient->get_student_payment_info($cleanusername);
            if (!$paymentinfo && !empty($user->idnumber)) {
                $paymentinfo = $this->apiclient->get_student_payment_info(trim($user->idnumber));
            }
        } catch (ums_exception $e) {
            $outagepolicy = (string)$this->apiclient->get_config('outage_policy', 'restrict');
            $allowbyoutage = ($outagepolicy === 'allow');
            return clearance_result::ums_unavailable(
                $allowbyoutage,
                'Institutional UMS dues service is currently unavailable. ' . ($allowbyoutage ? 'Access allowed by emergency policy.' : 'Access restricted by institutional policy.'),
                clearance_result::RESTRICTION_UMS_UNAVAILABLE
            );
        }

        if ($paymentinfo === null) {
            // Student has no UMS payment record (may not have been registered in current semester)
            $outagepolicy = (string)$this->apiclient->get_config('outage_policy', 'restrict');
            $allowbyoutage = ($outagepolicy === 'allow');
            return clearance_result::ums_unavailable(
                $allowbyoutage,
                'Student financial record was not found in the institutional portal.',
                clearance_result::RESTRICTION_NO_RECORD
            );
        }

        // 9. Calculate Net Due according to institutional business rules
        $result = $this->calculate_net_due($paymentinfo, $user);

        // 10. Store in MUC application cache
        $this->save_to_cache($userid, $result);

        return $result;
    }

    /**
     * Compute net due from raw UMS payment payload and program rules.
     *
     * @param array $paymentinfo Raw UMS payment data.
     * @param stdClass $user Moodle user object.
     * @return clearance_result
     */
    public function calculate_net_due(array $paymentinfo, stdClass $user): clearance_result {
        // Raw due field fallback
        $rawdue = (float)($paymentinfo['remaining_deus']
            ?? $paymentinfo['remaining_dues']
            ?? $paymentinfo['due']
            ?? $paymentinfo['dues']
            ?? 0.0);

        // Monthly installment
        $installment = (float)($paymentinfo['monthly_installment_amount'] ?? 0.0);

        // Program ID
        $context = $this->resolve_academic_context($user, $paymentinfo);
        $programid = $context['program_id'];
        $progStr = $programid !== null ? (string)$programid : (string)($user->department ?? '');

        // Configuration values
        $threshold = (float)$this->apiclient->get_config('due_threshold', self::DEFAULT_DUE_THRESHOLD);
        $buffer = (float)$this->apiclient->get_config('buffer_deduction', self::DEFAULT_BUFFER_DEDUCTION);
        $daycutoff = (int)$this->apiclient->get_config('installment_day_cutoff', self::DEFAULT_INSTALLMENT_DAY_CUTOFF);

        // Step 1: Base deduction (rawDue - buffer)
        $adjusteddue = $rawdue - $buffer;

        // Step 2: Monthly installment adjustment if day <= cutoff (e.g. 15th)
        $currentday = (int)date('j');
        if ($currentday <= $daycutoff) {
            $adjusteddue -= $installment;
        }

        // Step 3: Check program exemption if not already evaluated
        $exemptprograms = $this->get_exempt_program_ids();
        $isexemptprogram = ($programid !== null && in_array($programid, $exemptprograms, true));

        $cutoffraw = (string)$this->apiclient->get_config('exempt_date_cutoff', self::DEFAULT_EXEMPT_DATE_CUTOFF);
        $cutoffts = $this->parse_exemption_cutoff_timestamp($cutoffraw);

        if ($isexemptprogram && $this->is_exemption_active($cutoffts)) {
            return clearance_result::program_exempt(
                $progStr,
                $cutoffts,
                "Program '$progStr' is temporarily exempt from payment restriction until " . userdate($cutoffts) . "."
            );
        }

        // Step 4: Normalize — due cannot be negative
        $finaldue = max(0.0, $adjusteddue);

        // Step 5: Evaluate against institutional threshold
        if ($finaldue > $threshold) {
            return clearance_result::restricted(
                $finaldue,
                $rawdue,
                $installment,
                $threshold,
                $progStr,
                get_string('error_login_due_restriction', 'local_wub_auth')
            );
        }

        return clearance_result::cleared(
            $finaldue,
            $rawdue,
            $installment,
            $threshold,
            $progStr,
            $isexemptprogram
        );
    }

    /**
     * Get configured exempt program IDs.
     *
     * @return array
     */
    public function get_exempt_program_ids(): array {
        $raw = (string)$this->apiclient->get_config('exempt_programs', '');
        if (trim($raw) === '') {
            return self::DEFAULT_EXEMPT_PROGRAMS;
        }
        $parts = explode(',', $raw);
        return array_map('intval', array_map('trim', $parts));
    }

    /**
     * Get configured exempt department IDs or names.
     *
     * @return array
     */
    public function get_exempt_departments(): array {
        $raw = (string)$this->apiclient->get_config('exempt_departments', '');
        if (trim($raw) === '') {
            return [];
        }
        $parts = explode(',', $raw);
        return array_values(array_filter(array_map('trim', $parts)));
    }

    protected function get_from_cache(int $userid): ?clearance_result {
        try {
            $cache = cache::make('local_wub_auth', 'financial_clearance');
            $data = $cache->get((string)$userid);
            if (is_array($data) && isset($data['state'])) {
                return new clearance_result(
                    $data['state'],
                    (bool)$data['allowed'],
                    (float)$data['net_due'],
                    (float)$data['raw_due'],
                    (float)$data['monthly_installment'],
                    (float)$data['threshold'],
                    $data['program_id'] ?? null,
                    (bool)($data['is_exempt_program'] ?? false),
                    (string)($data['reason'] ?? ''),
                    null,
                    true, // is cached
                    isset($data['expires_at']) ? (int)$data['expires_at'] : null,
                    $data['restriction_type'] ?? null
                );
            }
        } catch (\Exception $e) {
            // Non-fatal cache failure
        }
        return null;
    }

    protected function save_to_cache(int $userid, clearance_result $result): void {
        try {
            $cache = cache::make('local_wub_auth', 'financial_clearance');
            $cache->set((string)$userid, $result->to_array());
        } catch (\Exception $e) {
            // Non-fatal cache failure
        }
    }
}
