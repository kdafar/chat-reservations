<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionAction extends Model
{
    protected $fillable = ['promotion_id', 'action_type', 'payload', 'sort'];

    protected $casts = ['payload' => 'array'];

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }
}
