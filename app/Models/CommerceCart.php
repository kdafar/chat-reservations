<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommerceCart extends Model
{
    protected $fillable = ['user_id', 'session_id', 'branch_id', 'currency',
        'delivery_fee', 'order_type', 'address_id',
        'coupon_id', 'coupon_code', 'coupon_meta', ];

    protected $casts = [
        'coupon_meta' => 'array',
        'delivery_fee' => 'decimal:3',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CommerceCartItem::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}
