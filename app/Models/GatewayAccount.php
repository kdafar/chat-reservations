<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GatewayAccount extends Model
{
    protected $fillable = [
        'gateway_id', 'owner_type', 'partner_id', 'branch_id', 'service_id', 'display_name', 'credentials', 'currency', 'is_active', 'is_default',
    ];

    protected $casts = [
        'credentials' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $m) {
            // 1. Normalize scope in a more maintainable way.
            $ownerKeys = ['branch_id', 'partner_id', 'service_id'];
            $activeKey = match ($m->owner_type) {
                'branch' => 'branch_id',
                'partner' => 'partner_id',
                'service' => 'service_id',
                default => null,
            };

            foreach ($ownerKeys as $key) {
                if ($key !== $activeKey) {
                    $m->{$key} = null;
                }
            }

            // 2. If this is default, unset others in the same scope using a clean query scope.
            if ($m->is_default) {
                self::query()
                    ->where('id', '!=', $m->id ?? 0)
                    ->inSameScope($m)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * Scope a query to only include records in the same operational scope.
     */
    public function scopeInSameScope(Builder $query, self $model): void
    {
        $query->where('gateway_id', $model->gateway_id)
            ->where('currency', $model->currency)
            ->where('owner_type', $model->owner_type)
            ->when($model->owner_type === 'branch', fn ($q) => $q->where('branch_id', $model->branch_id))
            ->when($model->owner_type === 'partner', fn ($q) => $q->where('partner_id', $model->partner_id))
            ->when($model->owner_type === 'service', fn ($q) => $q->where('service_id', $model->service_id));
    }

    public function gateway()
    {
        return $this->belongsTo(Gateway::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
