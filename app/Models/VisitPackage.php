<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitPackage extends Model
{
    use BelongsToBranchScope;

    protected $guarded = [];

    protected $casts = [
        'visit_id' => 'integer',
        'clinic_package_id' => 'integer',
        'branch_id' => 'integer',
        'qty' => 'decimal:3',
        'unit_price_snapshot' => 'decimal:3',
        'line_total' => 'decimal:3',
        'added_by_user_id' => 'integer',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ClinicPackage::class, 'clinic_package_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }
}
