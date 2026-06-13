<?php

namespace App\Models;

use App\Models\Accounting\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * End-of-service final settlement for a departing staff member.
 *
 *   net_settlement = gratuity + leave_encashment + other_additions
 *                    - loan_clawback - other_deductions
 *
 * Lifecycle: draft → approved (Dr 6016 End-of-Service Expense / Cr 2030
 * payable, plus clawback of any outstanding loan) → paid (Cr cash).
 */
class StaffSettlement extends Model
{
    use SoftDeletes;
    use Concerns\BelongsToBranchScope;
    use Concerns\LogsClinicActivity;

    protected $guarded = [];

    protected $activityLogName = 'staff_settlements';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';

    protected $casts = [
        'hire_date' => 'date:Y-m-d',
        'last_working_day' => 'date:Y-m-d',
        'years_of_service' => 'decimal:3',
        'basic_salary_snapshot' => 'decimal:3',
        'gratuity_amount' => 'decimal:3',
        'leave_encashment' => 'decimal:3',
        'other_additions' => 'decimal:3',
        'loan_clawback' => 'decimal:3',
        'other_deductions' => 'decimal:3',
        'net_settlement' => 'decimal:3',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'meta' => 'array',
        'user_id' => 'integer',
        'branch_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
