<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactEngagementStat extends Model
{
    protected $connection = 'wa';
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'campaigns_count',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
        'pending_count',
        'replied_count',
        'last_sent_at',
        'last_delivered_at',
        'last_read_at',
        'last_failed_at',
        'last_pending_at',
        'last_replied_at',
        'last_activity_at',
        'is_active',
    ];

    protected $casts = [
        'last_sent_at' => 'datetime',
        'last_delivered_at' => 'datetime',
        'last_read_at' => 'datetime',
        'last_failed_at' => 'datetime',
        'last_pending_at' => 'datetime',
        'last_replied_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
