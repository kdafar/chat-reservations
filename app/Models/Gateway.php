<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;

class Gateway extends Model
{
    use HasTranslations;

    protected $fillable = [
        'name',
        'driver',
        'is_system',
        'description',
        'logo_path',
        'is_active',
    ];

    public array $translatable = ['name', 'description'];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = ['logo_url'];

    public function accounts()
    {
        return $this->hasMany(GatewayAccount::class);
    }

    /**
     * Accessor for the full logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if ($this->logo_path) {
            return Storage::disk('public')->url($this->logo_path);
        }

        return null;
    }
}
