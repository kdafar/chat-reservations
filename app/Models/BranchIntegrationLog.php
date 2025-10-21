<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchIntegrationLog extends Model
{
    protected $fillable = [
        'branch_integration_id', 'started_at', 'finished_at', 'status',
        'categories', 'items', 'message', 'meta',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'meta' => 'array',
    ];

    public function integration()
    {
        return $this->belongsTo(BranchIntegration::class, 'branch_integration_id');
    }
}
