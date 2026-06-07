<?php

namespace App\Wa\Models\WhatsApp;

use App\Wa\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ================================

class WaConversation extends Model
{
    protected $connection = 'wa';
    protected $table = 'wa_conversations';

    protected $guarded = [];

    protected $casts = [
        'meta_raw' => 'array',
        'last_message_at' => 'datetime',
        'last_incoming_at' => 'datetime',
        'last_outgoing_at' => 'datetime',
    ];

    // === NEW: ADD THIS STATIC FUNCTION ===
    /**
     * Find or create a conversation for an incoming message.
     * This will be called by your webhook.
     */
    public static function findOrCreate(WaNumber $waNumber, WaContact $waContact): WaConversation
    {
        $conversation = WaConversation::updateOrCreate(
            [
                // Find by this unique combination
                'wa_number_id' => $waNumber->id,
                'contact_id' => $waContact->id,
            ],
            [
                // Create or update with this data
                'wa_account_id' => $waNumber->wa_account_id,
                'status' => 'open', // Re-open the conversation if it was resolved
                'last_message_at' => now(),
                'last_incoming_at' => now(),
            ]
        );

        return $conversation;
    }
    // =====================================

    public function account(): BelongsTo
    {
        return $this->belongsTo(WaAccount::class, 'wa_account_id');
    }

    public function number(): BelongsTo
    {
        return $this->belongsTo(WaNumber::class, 'wa_number_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function messages(): HasMany
    {
        // This assumes your WaMessage model uses 'conversation_id'
        return $this->hasMany(WaMessage::class, 'conversation_id');
    }

    public function scopeOpen($q)
    {
        return $q->where('status', 'open');
    }

    public function lastMessage()
    {
        return $this->hasOne(WaMessage::class, 'conversation_id')
            ->latest('sent_at')
            ->latest('id');
    }
}
