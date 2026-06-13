<?php

namespace App\Models;

use App\Models\Accounting\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A monthly payroll batch for a branch (or all branches when branch_id is null).
 *
 * Lifecycle: draft → approved → paid. Generating (re)builds payslips while
 * draft. Approval posts the salary accrual (Dr 6015 / Cr 2030). Marking paid
 * posts the disbursement (Dr 2030 + Dr 2020 doctor payable, Cr loans recv +
 * Cr cash) and settles the doctor commission ledger rows it covered.
 */
class PayrollRun extends Model
{
    use SoftDeletes;
    use Concerns\BelongsToBranchScope;
    use Concerns\LogsClinicActivity;

    protected $guarded = [];

    protected $activityLogName = 'payroll_runs';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'total_earnings' => 'decimal:3',
        'total_deductions' => 'decimal:3',
        'total_net' => 'decimal:3',
        'total_salary' => 'decimal:3',
        'total_commission' => 'decimal:3',
        'total_loan_repaid' => 'decimal:3',
        'pay_date' => 'date:Y-m-d',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'branch_id' => 'integer',
    ];

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function accrualJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'accrual_journal_entry_id');
    }

    public function paymentJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'payment_journal_entry_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function periodLabel(): string
    {
        return sprintf('%04d-%02d', $this->period_year, $this->period_month);
    }
}
