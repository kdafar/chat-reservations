<?php

namespace App\Wa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportEmailLog extends Model
{
    protected $connection = 'wa';
    protected $fillable = [
        'broadcast_id',
        'user_id',
        'email',
        'subject',
        'status',
        'sent_at',
        'failed_at',
        'error_message',
        'attempts',
        'opened_at',
        'clicked_at',
        'type',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(SupportEmailBroadcast::class, 'broadcast_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeOpened($query)
    {
        return $query->whereNotNull('opened_at');
    }

    public function scopeClicked($query)
    {
        return $query->whereNotNull('clicked_at');
    }
}
