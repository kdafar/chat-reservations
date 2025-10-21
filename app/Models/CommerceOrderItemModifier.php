<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceOrderItemModifier extends Model
{
    protected $table = 'commerce_order_item_modifiers';

    protected $fillable = [
        'commerce_order_item_id', 'modifier_group_id', 'modifier_option_id', 'group_name', 'option_name', 'price_delta',
    ];

    public function orderItem()
    {
        return $this->belongsTo(CommerceOrderItem::class, 'commerce_order_item_id');
    }
}
