<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One staff member's pay for a single payroll run.
 *
 *   gross_pay      = basic + allowances + commission
 *   deductions     = loan + unpaid_leave + other (recurring)
 *   net_pay        = gross_pay - deductions
 *
 * commission_total is the doctor's accrued cut (already expensed via the
 * compensation ledger / GL 6010) being PAID OUT this run — it is not a fresh
 * expense, just a settlement of the standing 2020 Doctor Payable.
 */
class Payslip extends Model
{
    protected $guarded = [];

    protected $casts = [
        'basic_salary' => 'decimal:3',
        'allowances_total' => 'decimal:3',
        'commission_total' => 'decimal:3',
        'gross_pay' => 'decimal:3',
        'loan_deduction' => 'decimal:3',
        'unpaid_leave_deduction' => 'decimal:3',
        'other_deductions' => 'decimal:3',
        'deductions_total' => 'decimal:3',
        'net_pay' => 'decimal:3',
        'unpaid_leave_days' => 'integer',
        'meta' => 'array',
    ];

    /** Salary portion that accrues to 6015/2030 (excludes already-accrued commission). */
    public function salaryAccrual(): float
    {
        return round((float) $this->basic_salary + (float) $this->allowances_total
            - (float) $this->unpaid_leave_deduction - (float) $this->other_deductions, 3);
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class);
    }
}
