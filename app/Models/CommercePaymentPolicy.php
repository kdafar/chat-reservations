<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercePaymentPolicy extends Model
{
    protected $table = 'commerce_payment_policies';

    protected $fillable = [
        'name', 'is_enabled', 'priority', 'partner_id', 'service_id', 'branch_id', 'conditions', 'action',
    ];

    protected $casts = [
        'is_enabled' => 'bool',
        'conditions' => 'array',
        'action' => 'array',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
