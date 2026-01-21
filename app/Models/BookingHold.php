<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BookingHold extends Model
{
    use \App\Models\Concerns\BelongsToBranchScope;

    protected $fillable = [
        'branch_id', 'msisdn', 'slot_key',
        'res_date', 'res_time', 'party_size',
        'res_start', 'res_end',
        'expires_at', 'meta',
    ];

    protected $casts = [
        'res_date' => 'date',
        'res_start' => 'datetime',
        'res_end' => 'datetime',
        'expires_at' => 'datetime',
        'meta' => 'array',
    ];

    public $timestamps = true;

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // Scopes
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('expires_at', '>', now(config('app.timezone')));
    }

    public function scopeForSlotKey(Builder $q, string $slotKey): Builder
    {
        return $q->where('slot_key', $slotKey);
    }

    /**
     * Overlap window: any hold that intersects [start, end)
     */
    public function scopeOverlapping(Builder $q, Carbon $start, Carbon $end, ?int $branchId = null, ?int $partySize = null): Builder
    {
        if ($branchId !== null) {
            $q->where('branch_id', $branchId);
        }
        if ($partySize !== null) {
            $q->where('party_size', $partySize);
        }

        return $q->whereNotNull('res_start')->whereNotNull('res_end')
            ->where(function (Builder $qq) use ($start, $end) {
                $qq->where('res_start', '<', $end)
                    ->where('res_end', '>', $start);
            });
    }
}
