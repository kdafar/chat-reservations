<?php

namespace App\Models;

use App\Models\Concerns\LogsClinicActivity;

use App\Models\Concerns\BelongsToPartnerScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicPackage extends Model
{
    use LogsClinicActivity;

    // Clinic-owned catalog: scoped by partner (shared across the clinic's
    // branches; partner_id null = global). branch_id is an optional override.
    use BelongsToPartnerScope;

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'integer',
        'branch_id' => 'integer',
        'name' => 'array',
        'is_active' => 'boolean',
        'default_price' => 'decimal:3',
    ];

    /** Safety net: derive the owning clinic from the branch when missing. */
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

    public function items(): HasMany
    {
        return $this->hasMany(ClinicPackageItem::class, 'clinic_package_id');
    }

    public function getLocalizedNameAttribute(): string
    {
        $locale = app()->getLocale();
        $arr = $this->name ?? [];

        return (string) ($arr[$locale] ?? $arr['en'] ?? $arr['ar'] ?? ('#'.$this->id));
    }
}
