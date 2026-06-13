<?php

namespace App\Services\Clinic;

use App\Models\DoctorCompensationLedger;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\StaffCompensationProfile;
use App\Models\StaffLoan;
use App\Models\StaffLoanRepayment;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds and posts monthly payroll.
 *
 * KEY ACCOUNTING INVARIANT — doctors are UNIFIED into payroll without
 * double-counting expense. A doctor's per-visit commission is already
 * expensed (Dr 6010 / Cr 2020 Doctor Payable) by the compensation ledger.
 * So payroll:
 *   - SHOWS the accrued, still-unsettled commission on the doctor's payslip,
 *   - does NOT re-accrue it (the salary accrual covers basic+allowances only),
 *   - and at PAYMENT settles the standing 2020 Doctor Payable for the cuts it
 *     pays out, stamping those ledger rows settled_payroll_run_id.
 *
 * Days basis for pro-rating: a 30-day month (basic / 30 per day).
 */
class PayrollService
{
    public function __construct(
        protected AccountingService $accounting,
        protected LeaveBalanceService $leave,
        protected LoanService $loans,
    ) {}

    /**
     * (Re)build every payslip for a DRAFT run. Wipes prior payslips + draft
     * loan-repayment rows for the run, then recomputes from scratch.
     */
    public function generate(PayrollRun $run): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_DRAFT) {
            throw new \RuntimeException('Only draft payroll runs can be generated.');
        }

        return DB::transaction(function () use ($run) {
            // Clear any previous draft computation.
            StaffLoanRepayment::where('payroll_run_id', $run->id)->delete();
            $run->payslips()->each(function (Payslip $p) {
                $p->lines()->delete();
                $p->delete();
            });

            [$year, $month] = [$run->period_year, $run->period_month];
            $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();

            $userIds = $this->staffForRun($run, $periodEnd);

            $totals = ['earn' => 0.0, 'ded' => 0.0, 'net' => 0.0, 'salary' => 0.0, 'comm' => 0.0, 'loan' => 0.0];

            foreach ($userIds as $userId) {
                $slip = $this->buildPayslip($run, (int) $userId, $year, $month, $periodEnd);
                $totals['earn'] += (float) $slip->gross_pay;
                $totals['ded'] += (float) $slip->deductions_total;
                $totals['net'] += (float) $slip->net_pay;
                $totals['salary'] += $slip->salaryAccrual();
                $totals['comm'] += (float) $slip->commission_total;
                $totals['loan'] += (float) $slip->loan_deduction;
            }

            $run->forceFill([
                'total_earnings' => round($totals['earn'], 3),
                'total_deductions' => round($totals['ded'], 3),
                'total_net' => round($totals['net'], 3),
                'total_salary' => round($totals['salary'], 3),
                'total_commission' => round($totals['comm'], 3),
                'total_loan_repaid' => round($totals['loan'], 3),
            ])->save();

            return $run->fresh('payslips');
        });
    }

    /**
     * Approve a generated run and post the salary accrual to the GL.
     */
    public function approve(PayrollRun $run, User $approver): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_DRAFT) {
            throw new \RuntimeException('Only draft runs can be approved.');
        }
        if ($run->payslips()->count() === 0) {
            throw new \RuntimeException('Generate payslips before approving.');
        }

        return DB::transaction(function () use ($run, $approver) {
            $run->forceFill([
                'status' => PayrollRun::STATUS_APPROVED,
                'approved_by_user_id' => $approver->id,
                'approved_at' => now(),
            ])->save();
            $run->payslips()->update(['status' => 'approved']);

            $this->accounting->recordPayrollAccrual($run->fresh(), $approver->id);

            return $run->fresh();
        });
    }

    /**
     * Mark an approved run paid: post the disbursement, settle the doctor
     * commission ledger rows it covered, and withhold the loan installments.
     */
    public function markPaid(PayrollRun $run, User $payer, ?int $paymentAccountId = null): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_APPROVED) {
            throw new \RuntimeException('Only approved runs can be paid.');
        }

        return DB::transaction(function () use ($run, $payer, $paymentAccountId) {
            $run->forceFill([
                'status' => PayrollRun::STATUS_PAID,
                'payment_account_id' => $paymentAccountId,
                'paid_at' => now(),
            ])->save();
            $run->payslips()->update(['status' => 'paid']);

            // Settle the exact doctor commission ledger rows captured at generate.
            foreach ($run->payslips()->where('commission_total', '>', 0)->get() as $slip) {
                $ledgerIds = (array) ($slip->meta['commission_ledger_ids'] ?? []);
                if ($ledgerIds) {
                    DoctorCompensationLedger::whereIn('id', $ledgerIds)
                        ->whereNull('settled_payroll_run_id')
                        ->update(['settled_payroll_run_id' => $run->id]);
                }
            }

            // Apply withheld loan installments to the loans.
            foreach (StaffLoanRepayment::where('payroll_run_id', $run->id)->whereNull('settled_at')->get() as $rep) {
                $loan = StaffLoan::find($rep->staff_loan_id);
                if ($loan) {
                    $this->loans->applyRepayment($loan, (float) $rep->amount);
                }
                $rep->forceFill(['settled_at' => now()])->save();
            }

            $this->accounting->recordPayrollPayment($run->fresh(), $payer->id);

            return $run->fresh();
        });
    }

    // -------------------------------------------------------------------------

    /** User ids in scope: active salary profiles + doctors with unsettled cuts. */
    protected function staffForRun(PayrollRun $run, Carbon $periodEnd): array
    {
        $profileQ = StaffCompensationProfile::query()->withoutGlobalScopes()
            ->whereNull('deleted_at')->where('is_active', true);
        if ($run->branch_id) {
            $profileQ->where(fn ($q) => $q->where('branch_id', $run->branch_id)->orWhereNull('branch_id'));
        }
        $ids = $profileQ->pluck('user_id')->all();

        // Doctors carrying unsettled commission, even without a salary profile.
        $commQ = DoctorCompensationLedger::query()->withoutGlobalScopes()
            ->whereNull('settled_payroll_run_id')
            ->where('doctor_cut_amount', '>', 0)
            ->where('created_at', '<=', $periodEnd);
        if ($run->branch_id) {
            $commQ->where('branch_id', $run->branch_id);
        }
        $doctorIds = $commQ->distinct()->pluck('doctor_id')->all();
        if ($doctorIds) {
            $commUserIds = \App\Models\Doctor::whereIn('id', $doctorIds)->whereNotNull('user_id')->pluck('user_id')->all();
            $ids = array_merge($ids, $commUserIds);
        }

        return array_values(array_unique(array_filter($ids)));
    }

    protected function buildPayslip(PayrollRun $run, int $userId, int $year, int $month, Carbon $periodEnd): Payslip
    {
        $profile = StaffCompensationProfile::query()->withoutGlobalScopes()
            ->whereNull('deleted_at')->where('is_active', true)->where('user_id', $userId)->first();

        $basic = (float) ($profile->basic_salary ?? 0);
        $allowanceLines = collect($profile?->allowances ?? []);
        $deductionLines = collect($profile?->deductions ?? []);
        $allowancesTotal = round($allowanceLines->sum(fn ($a) => (float) ($a['amount'] ?? 0)), 3);
        $recurringDed = round($deductionLines->sum(fn ($d) => (float) ($d['amount'] ?? 0)), 3);

        // Unpaid leave pro-ration (basic / 30 per day).
        $unpaidDays = $this->leave->unpaidDaysInMonth($userId, $year, $month);
        $unpaidDed = $basic > 0 ? round(($basic / 30) * $unpaidDays, 3) : 0.0;

        // Doctor commission: unsettled ledger cuts up to period end.
        $doctorId = \App\Models\Doctor::where('user_id', $userId)->value('id');
        $commissionTotal = 0.0;
        $commissionIds = [];
        if ($doctorId) {
            $ledgerQ = DoctorCompensationLedger::query()->withoutGlobalScopes()
                ->where('doctor_id', $doctorId)
                ->whereNull('settled_payroll_run_id')
                ->where('doctor_cut_amount', '>', 0)
                ->where('created_at', '<=', $periodEnd);
            if ($run->branch_id) {
                $ledgerQ->where('branch_id', $run->branch_id);
            }
            $rows = $ledgerQ->get(['id', 'doctor_cut_amount']);
            $commissionTotal = round((float) $rows->sum('doctor_cut_amount'), 3);
            $commissionIds = $rows->pluck('id')->all();
        }

        // Loan installments due (one draft repayment per active loan).
        $loanQ = StaffLoan::query()->withoutGlobalScopes()
            ->whereNull('deleted_at')->where('user_id', $userId)->where('status', StaffLoan::STATUS_ACTIVE)
            ->where('outstanding_amount', '>', 0);
        if ($run->branch_id) {
            $loanQ->where(fn ($q) => $q->where('branch_id', $run->branch_id)->orWhereNull('branch_id'));
        }
        $activeLoans = $loanQ->get();

        $gross = round($basic + $allowancesTotal + $commissionTotal, 3);
        $branchId = $profile->branch_id ?? $run->branch_id;

        $slip = Payslip::create([
            'payroll_run_id' => $run->id,
            'user_id' => $userId,
            'doctor_id' => $doctorId,
            'branch_id' => $branchId,
            'basic_salary' => $basic,
            'allowances_total' => $allowancesTotal,
            'commission_total' => $commissionTotal,
            'gross_pay' => $gross,
            'unpaid_leave_days' => $unpaidDays,
            'unpaid_leave_deduction' => $unpaidDed,
            'other_deductions' => $recurringDed,
            // loan_deduction + deductions_total + net_pay filled after loan rows.
            'loan_deduction' => 0,
            'deductions_total' => 0,
            'net_pay' => 0,
            'status' => 'draft',
            'meta' => [
                'commission_ledger_ids' => $commissionIds,
                'profile_id' => $profile?->id,
            ],
        ]);

        // --- Earning lines ---
        if ($basic > 0) {
            $this->line($slip, 'earning', 'basic', 'Basic salary', $basic);
        }
        foreach ($allowanceLines as $a) {
            $amt = (float) ($a['amount'] ?? 0);
            if ($amt != 0) {
                $this->line($slip, 'earning', 'allowance', (string) ($a['label'] ?? 'Allowance'), $amt);
            }
        }
        if ($commissionTotal > 0) {
            $this->line($slip, 'earning', 'commission', 'Doctor commission (accrued)', $commissionTotal);
        }

        // --- Deduction lines ---
        $loanDeduction = 0.0;
        foreach ($activeLoans as $loan) {
            $due = $loan->installmentDue();
            if ($due <= 0) {
                continue;
            }
            $loanDeduction += $due;
            StaffLoanRepayment::create([
                'staff_loan_id' => $loan->id,
                'payslip_id' => $slip->id,
                'payroll_run_id' => $run->id,
                'amount' => $due,
            ]);
            $this->line($slip, 'deduction', 'loan', ucfirst($loan->type).' repayment #'.$loan->id, $due, StaffLoan::class, $loan->id);
        }
        $loanDeduction = round($loanDeduction, 3);
        if ($unpaidDed > 0) {
            $this->line($slip, 'deduction', 'unpaid_leave', "Unpaid leave ({$unpaidDays}d)", $unpaidDed);
        }
        foreach ($deductionLines as $d) {
            $amt = (float) ($d['amount'] ?? 0);
            if ($amt != 0) {
                $this->line($slip, 'deduction', 'deduction', (string) ($d['label'] ?? 'Deduction'), $amt);
            }
        }

        $deductionsTotal = round($loanDeduction + $unpaidDed + $recurringDed, 3);
        $slip->forceFill([
            'loan_deduction' => $loanDeduction,
            'deductions_total' => $deductionsTotal,
            'net_pay' => round($gross - $deductionsTotal, 3),
        ])->save();

        return $slip;
    }

    protected function line(Payslip $slip, string $kind, string $source, string $label, float $amount, ?string $refType = null, ?int $refId = null): void
    {
        PayslipLine::create([
            'payslip_id' => $slip->id,
            'kind' => $kind,
            'source' => $source,
            'label' => $label,
            'amount' => round($amount, 3),
            'ref_type' => $refType,
            'ref_id' => $refId,
        ]);
    }
}
