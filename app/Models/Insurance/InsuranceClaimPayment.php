<?php

namespace App\Models\Insurance;

use App\Models\Accounting\Account;
use App\Models\Branch;
use App\Models\Concerns\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceClaimPayment extends Model
{
    use BelongsToBranchScope;

    public const METHOD_CHEQUE = 'cheque';

    public const METHOD_TRANSFER = 'transfer';

    public const METHOD_CASH = 'cash';

    protected $table = 'insurance_claim_payments';

    protected $fillable = [
        'claim_id',
        'branch_id',
        'amount',
        'method',
        'reference_no',
        'paid_at',
        'received_by_user_id',
        'deposited_to_account_id',
        'notes',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'paid_at' => 'datetime',
        'amount' => 'decimal:3',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class, 'claim_id');
    }

    public function depositedToAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deposited_to_account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
