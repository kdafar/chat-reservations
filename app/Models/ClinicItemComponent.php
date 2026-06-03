<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a service's bill of materials: a consumable/product the parent
 * service consumes when performed. See clinic_item_components migration.
 */
class ClinicItemComponent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'service_item_id' => 'integer',
        'component_item_id' => 'integer',
        'qty_base' => 'decimal:4',
        'is_optional' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(ClinicItem::class, 'service_item_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(ClinicItem::class, 'component_item_id');
    }
}
