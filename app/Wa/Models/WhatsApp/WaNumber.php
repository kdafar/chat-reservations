<?php

namespace App\Wa\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaNumber extends Model
{
    protected $connection = 'wa';
    protected $table = 'wa_numbers';

    protected $guarded = [];

    protected $casts = [
        'meta_raw' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(WaAccount::class, 'wa_account_id');
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(WaCredential::class, 'credential_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WaConversation::class, 'wa_number_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WaMessage::class, 'wa_number_id');
    }

    public function scopeConnected($query)
    {
        return $query->where('status', 'connected');
    }
}
