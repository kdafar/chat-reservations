<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchOpeningHour extends Model
{
    use \App\Models\Concerns\BelongsToBranchScope;
    use HasFactory;

    protected $fillable = [
        'branch_id', 'day_of_week', 'opens_at', 'closes_at', 'is_closed',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
