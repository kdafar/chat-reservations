<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicPackageItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'clinic_package_id' => 'integer',
        'clinic_item_id' => 'integer',
        'qty_base' => 'decimal:4',
        'is_consumable' => 'boolean',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(ClinicPackage::class, 'clinic_package_id');
    }

    public function clinicItem(): BelongsTo
    {
        return $this->belongsTo(ClinicItem::class, 'clinic_item_id');
    }
}
