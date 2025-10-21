<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceOrderItem extends Model
{
    protected $table = 'commerce_order_items';

    protected $fillable = [
        'commerce_order_id', 'menu_item_id', 'name', 'sku', 'unit_price', 'quantity', 'subtotal',
    ];

    public function order()
    {
        return $this->belongsTo(CommerceOrder::class, 'commerce_order_id');
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function modifiers()
    {
        return $this->hasMany(CommerceOrderItemModifier::class, 'commerce_order_item_id');
    }
}
