<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessage extends Model
{
    protected $connection = 'wa';
    // Delivery status constants
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_READ = 'read';

    public const STATUS_FAILED = 'failed';

    /**
     * Mass assignable attributes.
     * Keep legacy 'status' for backward-compat if old code still touches it.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'whatsapp_session_id',
        'restaurant_id',
        'direction',          // incoming | outgoing
        'type',               // text | image | template | etc.
        'content',            // JSON payload (we'll keep using this)
        'meta_message_id',    // wamid.*
        'delivery_status',    // queued|sent|delivered|read|failed
        'error_code',
        'error_title',
        'error_details',      // JSON
        'status',             // <— legacy field, safe to keep
    ];

    /**
     * Attribute casting.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'content' => 'array',
        'error_details' => 'array', // works with JSON or TEXT(JSON) fallback
    ];

    /**
     * Relationships
     */
    public function whatsappSession(): BelongsTo
    {
        return $this->belongsTo(WhatsappSession::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Vendors::class);
    }

    /**
     * Scopes
     */
    public function scopeOutgoing($query)
    {
        return $query->where('direction', 'outgoing');
    }

    public function scopeIncoming($query)
    {
        return $query->where('direction', 'incoming');
    }

    public function scopeWithWamid($query, string $wamid)
    {
        return $query->where('meta_message_id', $wamid);
    }

    /**
     * Convenience helpers
     */
    public function isFinalStatus(): bool
    {
        return in_array($this->delivery_status, [
            self::STATUS_DELIVERED,
            self::STATUS_READ,
            self::STATUS_FAILED,
        ], true);
    }

    public function markDelivery(string $status, ?int $code = null, ?string $title = null, $details = null): bool
    {
        $this->delivery_status = $status;
        $this->error_code = $code;
        $this->error_title = $title;
        $this->error_details = $details; // array or null

        return $this->save();
    }
}
