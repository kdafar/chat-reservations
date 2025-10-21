<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchIntegration extends Model
{
    protected $fillable = ['branch_id', 'provider', 'api_base_url', 'api_key', 'settings', 'enabled', 'partner_id'];

    protected $casts = ['settings' => 'array', 'enabled' => 'boolean'];

    protected static function booted(): void
    {
        // Automatically set the partner_id when creating a new record
        static::creating(function (BranchIntegration $integration) {
            if ($integration->branch && is_null($integration->partner_id)) {
                $integration->partner_id = $integration->branch->partner_id;
            }
        });
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function logs()
    {
        return $this->hasMany(BranchIntegrationLog::class);
    }
}
