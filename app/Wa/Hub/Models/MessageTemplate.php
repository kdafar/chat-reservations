<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a reusable message template synced from WhatsApp (Meta).
 *
 * @property int $id
 * @property string|null $meta_id
 * @property string $name
 * @property string|null $category
 * @property string|null $language
 * @property string|null $status // Meta Status: APPROVED, PENDING, REJECTED
 * @property string $local_status // Local Status: draft, published
 * @property string|null $rejection_reason
 * @property array|null $components
 * @property string|null $body
 * @property string|null $body_preview
 * @property array|null $auto_reply_data
 */
class MessageTemplate extends Model
{
    protected $connection = 'wa';
    use HasFactory;

    protected $fillable = [
        'meta_id',
        'name',
        'category',
        'language',
        'status',
        'local_status',
        'rejection_reason',
        'last_synced_at',
        'components',
        'points_cost',
        'body',
        'body_preview',
        'is_auto_reply',
        'triggers',
        'campaign_media_url',
        'campaign_link',
        'header_sample_path',
        'campaign_media_id',
        'auto_reply_data',
    ];

    protected $casts = [
        'components' => 'array',
        'triggers' => 'array',
        'auto_reply_data' => 'array',
        'is_auto_reply' => 'boolean',
        'last_synced_at' => 'datetime',
        'campaign_media_id' => 'integer',
    ];

    public function getCampaignMediaUrlAttribute($value)
    {
        if (! $value) {
            return null;
        }

        if (str_starts_with($value, 'http')) {
            return $value;
        }

        return asset('storage/'.$value);
    }

    public function getHeaderSampleUrlAttribute(): ?string
    {
        $path = $this->header_sample_path;
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http')
            ? $path
            : asset('storage/'.ltrim($path, '/'));
    }

    public static function isScopedToTenant(): bool
    {
        return false;
    }
}
