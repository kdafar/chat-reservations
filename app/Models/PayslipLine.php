<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Itemised earning / deduction line on a payslip (basic, each allowance,
 * commission, each loan installment, unpaid-leave, each recurring deduction).
 */
class PayslipLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:3',
    ];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function ref(): MorphTo
    {
        return $this->morphTo();
    }
}
