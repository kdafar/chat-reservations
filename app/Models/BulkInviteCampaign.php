<?php

namespace App\Models;

use App\Models\Concerns\LogsClinicActivity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// <-- ADD THIS LINE

class BulkInviteCampaign extends Model
{
    use LogsClinicActivity;

    protected $table = 'bulk_invite_campaigns';

    protected $fillable = [
        'name',
        'template_name',
        'template_details',
        'template_variables',
        'default_locale',
        'header_image_path',
        'scheduled_at',
        'status',
        'send_rate_per_min',
        'total_recipients',
        'sent_count',
        'failed_count',
    ];

    protected $casts = [
        'template_details' => 'array',
        'template_variables' => 'array',
        'scheduled_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'draft',
        'send_rate_per_min' => 600,
        'total_recipients' => 0,
        'sent_count' => 0,
        'failed_count' => 0,
    ];

    /**
     * Get all recipients for this campaign
     * CRITICAL: Relationship name MUST match RelationManager's protected static string $relationship = 'recipients'
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(BulkInviteCampaignRecipient::class, 'bulk_invite_campaign_id');
    }

    /**
     * Get campaigns that are ready to run
     */
    public function scopeRunnable($query)
    {
        return $query->whereIn('status', ['scheduled', 'running'])
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            });
    }

    /**
     * Mark campaign as completed
     */
    public function markCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }

    /**
     * Mark campaign as failed
     */
    public function markFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    /**
     * Update send counts from recipient status
     */
    public function updateCounts(): void
    {
        $this->update([
            'total_recipients' => $this->recipients()->count(),
            'sent_count' => $this->recipients()->where('status', 'sent')->count(),
            'failed_count' => $this->recipients()->where('status', 'failed')->count(),
        ]);
    }
}
