<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One branch's daily cash close (see CashCloseController). Totals are
 * server-computed snapshots taken when the day was closed.
 */
class CashClose extends Model
{
    protected $table = 'cash_closes';

    protected $fillable = [
        'branch_id',
        'business_date',
        'closed_by_user_id',
        'opening_float',
        'expected',
        'cash_expected',
        'cash_counted',
        'cash_diff',
        'knet_system',
        'knet_slip',
        'knet_diff',
        'denominations',
        'note',
        'closed_at',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'business_date' => 'date:Y-m-d',
        'closed_by_user_id' => 'integer',
        'opening_float' => 'decimal:3',
        'expected' => 'array',
        'cash_expected' => 'decimal:3',
        'cash_counted' => 'decimal:3',
        'cash_diff' => 'decimal:3',
        'knet_system' => 'decimal:3',
        'knet_slip' => 'decimal:3',
        'knet_diff' => 'decimal:3',
        'denominations' => 'array',
        'closed_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}
