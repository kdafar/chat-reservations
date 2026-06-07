<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Model;

class HubBranch extends Model
{
    protected $connection = 'wa';
    protected $fillable = [
        'external_key',
        'restaurant_domain',
        'vendor_id',
        'name_en',
        'name_ar',
        'wa_phone',
        'logo_url',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    public function getNameForLocale(string $locale): string
    {
        if ($locale === 'ar') {
            return $this->name_ar ?: $this->name_en ?: 'مطعمنا';
        }

        return $this->name_en ?: $this->name_ar ?: 'our restaurant';
    }

    public function restaurant()
    {
        return $this->belongsTo(Vendors::class, 'vendor_id');
    }

    public function deliveryAreas()
    {
        return $this->hasMany(DeliveryArea::class, 'hub_branch_id');
    }
}
