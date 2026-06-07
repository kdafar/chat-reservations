<?php

namespace App\Wa\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaContact extends Model
{
    protected $connection = 'wa';
    protected $table = 'wa_contacts';

    protected $guarded = [];

    protected $casts = [
        'meta_raw' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(WaAccount::class, 'wa_account_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WaConversation::class, 'contact_id');
    }
}
