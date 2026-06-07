<?php

namespace App\Wa\Models;

use Illuminate\Database\Eloquent\Model;

class PointRefund extends Model
{
    protected $connection = 'wa';
    protected $guarded = [];

    protected $casts = [
        'original_meta' => 'array',
        'refunded_at' => 'datetime',
    ];
}
