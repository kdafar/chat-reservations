<?php

namespace App\Wa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportEmailBroadcast extends Model
{
    protected $connection = 'wa';
    protected $fillable = [
        'created_by',
        'template_id',
        'audience_type',
        'role_id',
        'user_ids',
        'lang_filter',
        'active_within_days',
        'exclude_recent_recipients',
        'exclude_days',
        'subject',
        'body',
        'recipients_count',
        'queued_count',
        'sent_count',
        'failed_count',
        'status',
        'error',
        'scheduled_at',
        'started_at',
        'completed_at',
        'rate_limit_per_minute',
        'priority',
        'tags',
        'notes',
    ];

    protected $casts = [
        'user_ids' => 'array',
        'tags' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'rate_limit_per_minute' => 'integer',
        'recipients_count' => 'integer',
        'queued_count' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SupportEmailTemplate::class, 'template_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SupportEmailLog::class, 'broadcast_id');
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->queued_count === 0) {
            return 0;
        }

        return round(($this->sent_count / $this->queued_count) * 100, 2);
    }

    public function getSuccessRateAttribute(): float
    {
        $total = $this->sent_count + $this->failed_count;

        if ($total === 0) {
            return 0;
        }

        return round(($this->sent_count / $total) * 100, 2);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'sending');
    }

    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'sending',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'done',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
            'completed_at' => now(),
        ]);
    }
}
