<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranchScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use BelongsToBranchScope;

    public const S_DRAFT = 'draft';

    public const S_HOLD = 'hold';

    public const S_PENDING = 'pending';

    public const S_CONFIRMED = 'confirmed';

    public const S_CANCELLED = 'cancelled';

    public const S_COMPLETED = 'completed'; // Added based on Resource usage

    const REASON_PRICE = 'price_high';

    const REASON_EMERGENCY = 'patient_emergency';

    const REASON_NO_ANSWER = 'no_answer';

    const REASON_COMPETITOR = 'found_other_clinic';

    const REASON_RESCHEDULED = 'rescheduled';

    protected $fillable = [
        'branch_id', 'doctor_id', 'msisdn', 'party_size',
        'res_date', 'res_time', 'res_start', 'res_end',
        'status', 'booking_code', 'notes', 'meta',
        'qr_token', 'table_id', 'checked_in_at', 'contact_id',
        'patient_id', 'source', 'source_ref',
        'cancelled_by_user_id', 'cancelled_at', 'no_show_at',
    ];

    protected $casts = [
        'res_date' => 'date',
        'res_start' => 'datetime',
        'res_end' => 'datetime',
        'checked_in_at' => 'datetime',
        'meta' => 'array',
        'patient_id' => 'integer',
        'cancelled_at' => 'datetime',
        'no_show_at' => 'datetime',
        'expected_revenue_snapshot' => 'decimal:3',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // Strictly typed to prevent "Call to member function on null" crashes
    // =========================================================================

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Branch::class, 'branch_id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(\App\Models\RestaurantTable::class, 'table_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Doctor::class, 'doctor_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Models\WhatsappContact::class, 'contact_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Patient::class, 'patient_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'cancelled_by_user_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeForDate(Builder $q, $date): Builder
    {
        return $q->whereDate('res_date', $date);
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        // Upcoming by start time if present, else by date
        return $q->where(function (Builder $qq) {
            $qq->whereNotNull('res_start')->where('res_start', '>=', now(config('app.timezone')));
        })->orWhere(function (Builder $qq) {
            $qq->whereNull('res_start')->where('res_date', '>=', now()->toDateString());
        });
    }

    /**
     * Overlap window: any booking that intersects [start, end)
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
                $qq->whereBetween('res_start', [$start, $end])
                    ->orWhereBetween('res_end', [$start, $end])
                    ->orWhere(function (Builder $q3) use ($start, $end) {
                        $q3->where('res_start', '<=', $start)
                            ->where('res_end', '>=', $end);
                    });
            });
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getTimeslotLabelAttribute(): ?string
    {
        if (! $this->res_start || ! $this->res_end) {
            return null;
        }
        $tz = config('app.timezone', 'Asia/Kuwait');

        return $this->res_start->timezone($tz)->format('D, d M h:i A').' – '.
               $this->res_end->timezone($tz)->format('h:i A');
    }

    public function getNameAttribute(): ?string
    {
        // 1) if the linked contact has a name, use it
        // Uses optional() for blade safety
        $fromContact = optional($this->contact)->name;
        if (! empty($fromContact)) {
            return $fromContact;
        }

        // 2) normalize $meta to array even if stored as TEXT JSON
        $meta = $this->meta;
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $meta = $decoded;
            } else {
                $meta = [];
            }
        }

        // 3) try meta[name] then meta[contact_snapshot.name]
        return data_get($meta, 'name')
            ?: data_get($meta, 'contact_snapshot.name')
            ?: null;
    }

    public function getStartAtAttribute(): ?Carbon
    {
        if ($this->res_start) {
            return $this->res_start; // already a Carbon (cast)
        }

        if ($this->res_date && $this->res_time) {
            try {
                $tz = config('app.timezone', 'Asia/Kuwait');

                return Carbon::parse($this->res_date.' '.$this->res_time, $tz);
            } catch (\Throwable $e) {
                // Silently fail on invalid dates to prevent view crashes
            }
        }

        return null;
    }

    public function getEndAtAttribute(): ?Carbon
    {
        // Prefer actual end; otherwise null
        return $this->res_end ?: null;
    }

    /**
     * Resolved patient id with backward compatibility
     */
    public function getPatientIdResolvedAttribute(): ?int
    {
        if (! empty($this->patient_id)) {
            return (int) $this->patient_id;
        }

        $meta = $this->meta;

        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = (json_last_error() === JSON_ERROR_NONE) ? $decoded : [];
        }

        $pid = data_get($meta, 'patient_id');

        return $pid !== null ? (int) $pid : null;
    }

    /**
     * Helper to check if this booking represents "Lost Revenue"
     */
    public function isLostRevenue(): bool
    {
        return ! is_null($this->cancelled_at) || ! is_null($this->no_show_at);
    }

    public function getPatientDisplayAttribute(): string
    {
        return (string) ($this->patient?->name ?? $this->contact?->name ?? 'Valued Patient');
    }
}
