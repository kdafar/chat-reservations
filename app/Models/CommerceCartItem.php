<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceCartItem extends Model
{
    protected $fillable = [
        'commerce_cart_id', 'item_id', 'qty', 'unit_price', 'subtotal',
        'modifiers', 'row_id', 'note', 'offer',
    ];

    protected $casts = [
        'modifiers' => 'array',
        'offer' => 'array',
        'unit_price' => 'decimal:3',
        'subtotal' => 'decimal:3',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(CommerceCart::class, 'commerce_cart_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_id');
    }
}
