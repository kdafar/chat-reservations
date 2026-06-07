<?php

namespace App\Wa\Models;

use App\Wa\Hub\Models\Vendors;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $connection = 'wa';
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'order_details' => 'array',
        'api_response' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Vendors::class);
    }
}
