<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningHour extends Model
{
    protected $fillable = ['branch_id', 'weekday', 'opens_at', 'closes_at', 'is_closed', 'sort_order'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
