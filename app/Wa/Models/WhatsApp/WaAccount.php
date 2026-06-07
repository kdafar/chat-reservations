<?php

namespace App\Wa\Models\WhatsApp;

use App\Wa\Models\User; // adjust if your User class is different
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaAccount extends Model
{
    protected $connection = 'wa';
    protected $table = 'wa_accounts';

    protected $guarded = [];

    protected $casts = [
        'meta_raw' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function numbers(): HasMany
    {
        return $this->hasMany(WaNumber::class, 'wa_account_id');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(WaCredential::class, 'wa_account_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(WaContact::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WaConversation::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WaTemplate::class);
    }

    // convenience scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
