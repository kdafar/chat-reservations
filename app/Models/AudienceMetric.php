<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AudienceMetric extends Model
{
    protected $table = 'audience_metrics';

    protected $primaryKey = 'msisdn';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'msisdn', 'bookings_count', 'confirmed_count', 'last_booking_at', 'last_branch_id',
        'last_party_size', 'last_wa_in_at', 'last_wa_out_at', 'session_last_interacted_at',
        'last_interaction_at', 'refreshed_at',
    ];

    protected $casts = [
        'bookings_count' => 'int',
        'confirmed_count' => 'int',
        'last_booking_at' => 'datetime',
        'last_wa_in_at' => 'datetime',
        'last_wa_out_at' => 'datetime',
        'session_last_interacted_at' => 'datetime',
        'last_interaction_at' => 'datetime',
        'refreshed_at' => 'datetime',
    ];

    // Optional hard guard: throw on write attempts
    public function save(array $options = []): bool
    {
        throw new \LogicException('AudienceMetric is read-only.');
    }

    public function delete(): bool
    {
        throw new \LogicException('AudienceMetric is read-only.');
    }
}
