<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicItem extends Model
{
    // Clinic isolation: the catalog is owned by the CLINIC (partner) and shared
    // across that clinic's branches. Non-admins only see their clinic's items
    // (plus global rows where partner_id is null). Admin/super_admin bypass.
    // branch_id remains an optional within-clinic branch override.
    use \App\Models\Concerns\BelongsToPartnerScope;

    protected $guarded = [];

    protected $casts = [
        'name' => 'array',
        'default_cost' => 'decimal:3',
        'default_price' => 'decimal:3',
        'is_active' => 'boolean',
        'partner_id' => 'integer',
        'branch_id' => 'integer',

        // New fields
        'is_stockable' => 'boolean',
        'conversion_factor' => 'decimal:4',
        'consume_step' => 'decimal:4',
        'is_billable' => 'boolean',
    ];

    /**
     * Safety net: if a row is saved with a branch but no clinic, derive the
     * clinic from the branch. Keeps seeders / imports / any code path from
     * accidentally creating a partner-less ("global, all clinics") catalog row.
     */
    protected static function booted(): void
    {
        static::saving(function (self $m) {
            if (empty($m->partner_id) && ! empty($m->branch_id)) {
                $m->partner_id = Branch::query()->whereKey($m->branch_id)->value('partner_id');
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
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
