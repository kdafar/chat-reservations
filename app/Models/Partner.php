<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Partner extends Model
{
    use HasTranslations;

    protected $fillable = ['name', 'slug', 'logo_path', 'is_active'];

    public $translatable = ['name'];

    protected $casts = [
        'name' => 'array', // For spatie/laravel-translatable
        'is_active' => 'boolean',
    ];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    // NEW: services at partner level
    public function services(): BelongsToMany
    {
        // Assumes you have a pivot table named 'partner_service'
        return $this->belongsToMany(Service::class);
    }

    // NEW: partner-level users (owners/managers)
    public function users()
    {
        return $this->belongsToMany(User::class, 'partner_user');
    }

    public function getNameLabelAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale());
    }

    public function branchIntegrations(): HasMany
    {
        return $this->hasMany(BranchIntegration::class);
    }

    public function gatewayAccounts()
    {
        return $this->hasMany(GatewayAccount::class, 'partner_id')
            ->where('owner_type', 'partner');
    }
}
