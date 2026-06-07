<?php

namespace App\Wa\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks individual point deductions from the system.
 *
 * @property int $id
 * @property int|null $user_id Optional: who triggered the usage (for logs)
 * @property int $points
 * @property string $event_type e.g., 'fleet_alert', 'whatsapp_bot', 'campaign'
 * @property array|null $meta
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class PointUsage extends Model
{
    protected $connection = 'wa';
    use HasFactory;

    protected $table = 'point_usages';

    protected $fillable = [
        'user_id',
        'points',
        'event_type', // e.g. 'fleet_alert', 'whatsapp_bot_interaction'
        'meta',       // JSON details about the message/context
    ];

    protected $casts = [
        'points' => 'integer',
        'meta' => 'array',
    ];

    /**
     * The user who triggered this usage (optional).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get total usage for the whole system.
     */
    public function scopeTotalSystemUsage($query)
    {
        return $query->sum('points');
    }
}
