<?php

namespace App\Wa\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaCredential extends Model
{
    protected $connection = 'wa';
    protected $table = 'wa_credentials';

    protected $guarded = [];

    protected $casts = [
        'meta_raw' => 'array',
        'expires_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(WaAccount::class, 'wa_account_id');
    }

    public function numbers(): HasMany
    {
        return $this->hasMany(WaNumber::class, 'credential_id');
    }
}
