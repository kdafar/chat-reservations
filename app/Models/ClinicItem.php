<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicItem extends Model
{
    // use \App\Models\Concerns\BelongsToBranchScope;

    protected $guarded = [];

    protected $casts = [
        'name' => 'array',
        'default_cost' => 'decimal:3',
        'default_price' => 'decimal:3',
        'is_active' => 'boolean',
        'branch_id' => 'integer',

        // New fields
        'is_stockable' => 'boolean',
        'conversion_factor' => 'decimal:4',
        'consume_step' => 'decimal:4',
        'is_billable' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getLocalizedNameAttribute(): string
    {
        $locale = app()->getLocale();
        $arr = $this->name ?? [];

        return (string) ($arr[$locale] ?? $arr['en'] ?? $arr['ar'] ?? ('#'.$this->id));
    }

    public function stocks()
    {
        return $this->hasMany(ClinicItemStock::class);
    }

    public function stockRequestLines()
    {
        return $this->hasMany(VisitStockRequestLine::class, 'clinic_item_id');
    }

    /**
     * Bill-of-materials lines: the consumables/products this service uses each
     * time it is performed. Only meaningful when type === 'service'.
     */
    public function components()
    {
        return $this->hasMany(ClinicItemComponent::class, 'service_item_id');
    }

    public function isService(): bool
    {
        return $this->type === 'service';
    }
}
