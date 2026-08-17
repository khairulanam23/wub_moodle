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

namespace local_wub_auth_penalty\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Service calculating student financial dues according to institutional WUB rules.
 *
 * Rules:
 * 1. Subtract baseline buffer deduction (100 BDT) from total remaining dues.
 * 2. If calculation date is on or before 15th of the current month, subtract monthly installment.
 * 3. ONLY explicitly listed program_ids (324, 351, 359, 360, 363, 352, 361, 362, 313) are exempt
 *    from due enforcement until September 10 (09-10).
 * 4. ALL OTHER program_ids (e.g. 315) receive NO date exemption and must be blocked if due > 100 BDT.
 *
 * @package    local_wub_auth_penalty
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class due_calculator {

    /**
     * Baseline buffer deduction in BDT.
     */
    const BASELINE_BUFFER_DEDUCTION = 100.0;

    /**
     * Explicit list of program IDs eligible for temporary semester start due exemption (until Sept 10).
     * SEPTEMBER_10_PROGRAMS in the pseudocode specification.
     */
    const EXEMPT_PROGRAM_IDS = [324, 351, 359, 360, 363, 352, 361, 362, 313];

    /**
     * Explicit list of exception program IDs that use a separate due calculation.
     * EXCEPTION_PROGRAMS in the pseudocode specification.
     *
     * Populate with program IDs that require calculateExceptionDue().
     * Currently empty — to be configured when exception programs are defined.
     */
    const EXCEPTION_PROGRAM_IDS = [];

    /**
     * Check if a given program ID is in the explicit list of exempt programs (SEPTEMBER_10_PROGRAMS).
     *
     * @param mixed $programId
     * @return bool
     */
    public function isExemptProgram($programId): bool {
        return $this->programIdInList($programId, self::EXEMPT_PROGRAM_IDS);
    }

    /**
     * Check if a given program ID belongs to EXCEPTION_PROGRAMS.
     *
     * @param mixed $programId
     * @return bool
     */
    public function isExceptionProgram($programId): bool {
        return $this->programIdInList($programId, self::EXCEPTION_PROGRAM_IDS);
    }

    /**
     * Calculate due for an exception program.
     *
     * Placeholder implementation — returns 0.0 (allow login) until
     * exception program rules are defined and populated.
     *
     * @param object|array|null $paymentInfo Raw UMS payment payload.
     * @param mixed $programId The exception program ID.
     * @return float Calculated due amount for the exception program.
     */
    public function calculateExceptionDue($paymentInfo, $programId): float {
        // TODO: Implement exception-program-specific due calculation once rules are defined.
        return 0.0;
    }

    /**
     * Check if a program ID exists in a given list of program IDs.
     *
     * @param mixed $programId
     * @param array $list
     * @return bool
     */
    protected function programIdInList($programId, array $list): bool {
        if (empty($programId) || empty($list)) {
            return false;
        }

        $pidStr = strtolower(trim((string)$programId));

        if (is_numeric($pidStr)) {
            $pidInt = (int)$pidStr;
            return in_array($pidInt, $list, true);
        }

        foreach ($list as $id) {
            if ($pidStr === (string)$id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate student due amount based on UMS payment info and program rules.
     *
     * Pseudocode steps implemented:
     *   3. Exception program check → calculateExceptionDue()
     *   4. Normal due = remaining_deus − 100
     *   5. Monthly installment adjustment (day ≤ 15)
     *   6–7. Semester start date exemption (SEPTEMBER_10_PROGRAMS only)
     *   8. Normalize due (< 0 → 0)
     *
     * @param object|array|null $paymentInfo
     * @param mixed $programId
     * @param object|array|null $feeDetails
     * @return array [0 => final_due, 1 => adjusted_due, ...]
     */
    public function getDue($paymentInfo, $programId = null, $feeDetails = null): array {
        $rawDue = 0.0;
        $monthlyInstallment = 0.0;
        $extractedProgramId = $programId;

        if (!empty($paymentInfo)) {
            if (is_object($paymentInfo)) {
                if (isset($paymentInfo->remaining_deus)) {
                    $rawDue = (float)$paymentInfo->remaining_deus;
                } else if (isset($paymentInfo->remaining_dues)) {
                    $rawDue = (float)$paymentInfo->remaining_dues;
                } else if (isset($paymentInfo->due)) {
                    $rawDue = (float)$paymentInfo->due;
                } else if (isset($paymentInfo->dues)) {
                    $rawDue = (float)$paymentInfo->dues;
                }

                if (isset($paymentInfo->monthly_installment_amount)) {
                    $monthlyInstallment = (float)$paymentInfo->monthly_installment_amount;
                }

                if (isset($paymentInfo->program_id)) {
                    $extractedProgramId = $paymentInfo->program_id;
                } else if (isset($paymentInfo->StudentPaymentInfo->program_id)) {
                    $extractedProgramId = $paymentInfo->StudentPaymentInfo->program_id;
                }
            } else if (is_array($paymentInfo)) {
                if (isset($paymentInfo['remaining_deus'])) {
                    $rawDue = (float)$paymentInfo['remaining_deus'];
                } else if (isset($paymentInfo['remaining_dues'])) {
                    $rawDue = (float)$paymentInfo['remaining_dues'];
                } else if (isset($paymentInfo['due'])) {
                    $rawDue = (float)$paymentInfo['due'];
                } else if (isset($paymentInfo['dues'])) {
                    $rawDue = (float)$paymentInfo['dues'];
                }

                if (isset($paymentInfo['monthly_installment_amount'])) {
                    $monthlyInstallment = (float)$paymentInfo['monthly_installment_amount'];
                }

                if (isset($paymentInfo['program_id'])) {
                    $extractedProgramId = $paymentInfo['program_id'];
                } else if (isset($paymentInfo['message']['program_id'])) {
                    $extractedProgramId = $paymentInfo['message']['program_id'];
                }
            }
        }

        if (empty($extractedProgramId)) {
            $extractedProgramId = $programId;
        }

        if ($feeDetails && (is_object($feeDetails) || is_array($feeDetails))) {
            $fObj = (object)$feeDetails;
            if (!empty($fObj->monthly_installment_amount)) {
                $monthlyInstallment = (float)$fObj->monthly_installment_amount;
            } else if (!empty($fObj->monthly_installment)) {
                $monthlyInstallment = (float)$fObj->monthly_installment;
            }
        }

        // -----------------------------------------
        // Step 3: Exception program check.
        // -----------------------------------------
        $isException = $this->isExceptionProgram($extractedProgramId);

        if ($isException) {
            $dueAmount = $this->calculateExceptionDue($paymentInfo, $extractedProgramId);
        } else {

            // -----------------------------------------
            // Step 4: Normal due = remaining_deus − 100.
            // -----------------------------------------
            $total_due_as_of_today = $rawDue - self::BASELINE_BUFFER_DEDUCTION;

            // -----------------------------------------
            // Step 5: Monthly installment adjustment (day ≤ 15).
            // -----------------------------------------
            if ((int)date('j') <= 15) {
                $total_due_as_of_today -= $monthlyInstallment;
            }

            // -----------------------------------------
            // Step 6–7: Semester start date exemption (SEPTEMBER_10_PROGRAMS only).
            // -----------------------------------------
            $isExempt = $this->isExemptProgram($extractedProgramId);
            $currentDate = date('Y-m-d');
            $septemberCutoff = date('Y') . '-09-10';

            if ($isExempt && $currentDate <= $septemberCutoff) {
                $dueAmount = 0.0;
            } else {
                $dueAmount = $total_due_as_of_today;
            }
        }

        // -----------------------------------------
        // Step 8: Normalize — ensure due is never negative.
        // -----------------------------------------
        if ($dueAmount < 0) {
            $dueAmount = 0.0;
        }

        $dueAmount = (float)(int)$dueAmount;

        return [
            0 => $dueAmount,
            1 => $total_due_as_of_today ?? $dueAmount,
            'raw_due' => $rawDue,
            'adjusted_due' => $total_due_as_of_today ?? $dueAmount,
            'final_due' => $dueAmount,
            'program_id' => (string)($extractedProgramId ?? ''),
            'is_exempt_program' => $isExempt ?? false,
            'is_exception_program' => $isException,
        ];
    }
}
