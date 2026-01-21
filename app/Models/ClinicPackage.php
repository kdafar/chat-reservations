<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicPackage extends Model
{
    use BelongsToBranchScope;

    protected $guarded = [];

    protected $casts = [
        'branch_id' => 'integer',
        'name' => 'array',
        'is_active' => 'boolean',
        'default_price' => 'decimal:3',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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
