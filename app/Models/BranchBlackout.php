<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchBlackout extends Model
{
    use \App\Models\Concerns\BelongsToBranchScope;

    protected $fillable = ['branch_id', 'date', 'reason'];

    protected $casts = ['date' => 'date'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
