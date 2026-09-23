<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A heading in the clinic's catalogue ("Peels", "Lasers", "Skincare") used to
 * browse services, items and packages on the visit screen. Owned per clinic
 * (partner) like the catalogue it files.
 */
class ClinicCatalogCategory extends Model
{
    use \App\Models\Concerns\BelongsToPartnerScope;

    protected $guarded = [];

    protected $casts = [
        'name' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'partner_id' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ClinicItem::class, 'category_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ClinicPackage::class, 'category_id');
    }

    /** The name in the given locale, falling back to the other language. */
    public function label(string $locale = 'en'): string
    {
        $n = (array) $this->name;

        return (string) ($n[$locale] ?? $n['en'] ?? $n['ar'] ?? reset($n) ?: '');
    }
}
