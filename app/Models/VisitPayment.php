<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitPayment extends Model
{
    use SoftDeletes;

    public const KIND_CONSULTATION = 'consultation';

    protected $table = 'visit_payments';

    protected $fillable = [
        'visit_id',
        'amount',
        'method',
        'status',
        'reference_no',
        'collected_by_user_id',
        'paid_at',
        'kind',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
        'paid_at' => 'datetime',
        'meta' => 'array',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by_user_id');
    }
}
