<?php

namespace App\Models;

use App\Models\Accounting\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A staff loan or salary advance, repaid by withholding installments from
 * payroll. outstanding_amount falls as repayments are recorded; the loan is
 * auto-settled when it reaches zero.
 *
 *   pending → active (disbursed, Dr 1130 / Cr Cash) → settled
 */
class StaffLoan extends Model
{
    use SoftDeletes;
    use Concerns\BelongsToBranchScope;
    use Concerns\LogsClinicActivity;

    protected $guarded = [];

    protected $activityLogName = 'staff_loans';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_CANCELLED = 'cancelled';

    protected $casts = [
        'principal_amount' => 'decimal:3',
        'outstanding_amount' => 'decimal:3',
        'installment_amount' => 'decimal:3',
        'issued_on' => 'date:Y-m-d',
        'approved_at' => 'datetime',
        'user_id' => 'integer',
        'branch_id' => 'integer',
    ];

    /** Installment to withhold this run, capped at the outstanding balance. */
    public function installmentDue(): float
    {
        $inst = (float) $this->installment_amount;
        $out = (float) $this->outstanding_amount;
        if ($inst <= 0) {
            return 0.0;
        }

        return round(min($inst, $out), 3);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(StaffLoanRepayment::class);
    }
}
