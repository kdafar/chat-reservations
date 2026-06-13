<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single loan installment withheld via a payslip. Created (draft) when a
 * payroll run is generated; settled_at is stamped when that run is paid.
 */
class StaffLoanRepayment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:3',
        'settled_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(StaffLoan::class, 'staff_loan_id');
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }
}
