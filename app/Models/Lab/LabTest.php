<?php

namespace App\Models\Lab;

use App\Models\Branch;
use App\Models\Concerns\BelongsToBranchScope;
use App\Models\Concerns\LogsClinicActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabTest extends Model
{
    use SoftDeletes;
    use BelongsToBranchScope;
    use LogsClinicActivity;

    protected $guarded = [];

    protected $activityLogName = 'lab_tests';

    protected $casts = [
        'default_price' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(LabOrderItem::class);
    }
}
