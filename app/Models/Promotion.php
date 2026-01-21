<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Promotion extends Model
{
    use \App\Models\Concerns\BelongsToBranchScope;
    use HasTranslations, SoftDeletes;

    protected $fillable = [
        'service_id', 'partner_id', 'branch_id',
        'title', 'summary', 'type', 'status', 'priority', 'stack_behavior',
        'once_per_order', 'auto_apply', 'channels', 'image_path',
        'starts_at', 'ends_at', 'max_redemptions', 'max_per_user',
    ];

    public array $translatable = ['title', 'summary'];

    protected $casts = [
        'channels' => 'array',
        'once_per_order' => 'bool',
        'auto_apply' => 'bool',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function conditions()
    {
        return $this->hasMany(PromotionCondition::class)->orderBy('sort');
    }

    public function actions()
    {
        return $this->hasMany(PromotionAction::class)->orderBy('sort');
    }

    public function scopeActive(Builder $q): Builder
    {
        $now = now();

        return $q->where('status', 'active')
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function isWithinWindow(?CarbonInterface $at = null): bool
    {
        $at = $at ?: now();
        if ($this->starts_at && $at->lt($this->starts_at)) {
            return false;
        }
        if ($this->ends_at && $at->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function service()
    {
        return $this->belongsTo(\App\Models\Service::class);
    }

    public function partner()
    {
        return $this->belongsTo(\App\Models\Partner::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }
}
