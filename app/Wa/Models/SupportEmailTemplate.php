<?php

namespace App\Wa\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class SupportEmailTemplate extends Model
{
    protected $connection = 'wa';
    use HasTranslations;

    public array $translatable = [
        'subject',
        'body',
    ];

    protected $fillable = [
        'name',
        'slug',
        'category',
        'subject',
        'body',
        'description',
        'is_active',
        'usage_count',
        'last_used_at',
        'tags',
        'preview_text',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'tags' => 'array',
        'usage_count' => 'integer',
    ];

    public function broadcasts(): HasMany
    {
        return $this->hasMany(SupportEmailBroadcast::class, 'template_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query->where('usage_count', '>', 0)
            ->orderBy('usage_count', 'desc');
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'onboarding' => 'Onboarding',
            'billing' => 'Billing',
            'maintenance' => 'Maintenance',
            'marketing' => 'Marketing',
            'security' => 'Security',
            'feature_announcement' => 'Feature Announcement',
            'policy_update' => 'Policy Update',
            default => 'Other',
        };
    }

    public function getAvailableVariables(): array
    {
        return [
            '{{name}}' => 'User full name',
            '{{email}}' => 'User email address',
            '{{company_name}}' => 'User company name',
            '{{username}}' => 'User username',
            '{{first_name}}' => 'User first name',
            '{{last_login}}' => 'Last login date',
            '{{account_age}}' => 'Days since registration',
        ];
    }
}
