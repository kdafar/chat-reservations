<?php

namespace App\Wa\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaTemplate extends Model
{
    protected $connection = 'wa';
    protected $table = 'wa_templates';

    protected $guarded = [];

    protected $casts = [
        'components' => 'array',
        'meta_raw' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(WaAccount::class, 'wa_account_id');
    }

    public function getLabelAttribute(): string
    {
        return "{$this->name} ({$this->language})";
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'APPROVED');
    }
}
