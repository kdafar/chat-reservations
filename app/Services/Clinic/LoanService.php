<?php

namespace App\Services\Clinic;

use App\Models\StaffLoan;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\DB;

/**
 * Staff loan / advance lifecycle: approve (disburse cash + post GL), then
 * repay by withholding installments from payroll (PayrollService drives this
 * via applyRepayment()).
 */
class LoanService
{
    public function __construct(protected AccountingService $accounting) {}

    /**
     * Approve & disburse a pending loan: outstanding := principal, status
     * active, and post Dr 1130 / Cr Cash. Idempotent-ish (no-op if not pending).
     */
    public function approve(StaffLoan $loan, User $approver): StaffLoan
    {
        if ($loan->status !== StaffLoan::STATUS_PENDING) {
            return $loan;
        }

        return DB::transaction(function () use ($loan, $approver) {
            $loan->forceFill([
                'status' => StaffLoan::STATUS_ACTIVE,
                'outstanding_amount' => $loan->principal_amount,
                'approved_by_user_id' => $approver->id,
                'approved_at' => now(),
            ])->save();

            $this->accounting->recordLoanDisbursement($loan->fresh(), $approver->id);

            return $loan->fresh();
        });
    }

    /**
     * Reduce a loan's outstanding balance by a repayment amount, auto-settling
     * when it hits zero. Returns the actual amount applied (capped at balance).
     */
    public function applyRepayment(StaffLoan $loan, float $amount): float
    {
        $applied = round(min($amount, (float) $loan->outstanding_amount), 3);
        if ($applied <= 0) {
            return 0.0;
        }

        $loan->outstanding_amount = round((float) $loan->outstanding_amount - $applied, 3);
        if ($loan->outstanding_amount <= 0.0005) {
            $loan->outstanding_amount = 0;
            $loan->status = StaffLoan::STATUS_SETTLED;
        }
        $loan->save();

        return $applied;
    }

    /**
     * Reverse a repayment (used when a paid payroll run is rolled back) — adds
     * the amount back to the outstanding balance and reactivates the loan.
     */
    public function reverseRepayment(StaffLoan $loan, float $amount): void
    {
        $loan->outstanding_amount = round((float) $loan->outstanding_amount + $amount, 3);
        if ($loan->status === StaffLoan::STATUS_SETTLED && $loan->outstanding_amount > 0) {
            $loan->status = StaffLoan::STATUS_ACTIVE;
        }
        $loan->save();
    }
}
