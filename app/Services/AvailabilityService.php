<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Branch;
use App\Models\BranchAvailabilityRule;
use App\Models\BranchBlackout;
use App\Models\Doctor;
use App\Models\DoctorShift;
use App\Models\RestaurantTable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AvailabilityService
{
    /** Return associative array: ['2025-11-03' => 'Mon, Nov 3', ...] */
    public function nextDates(int $branchId, ?int $days = null, int $partySize = 2): array
    {
        $branchDays = Branch::find($branchId)?->max_booking_days;
        $days = max(1, (int) ($days ?? $branchDays ?? config('booking.dates_forward_days', 60)));

        $tz = config('app.timezone');
        $today = now($tz)->startOfDay();

        $rules = BranchAvailabilityRule::where('branch_id', $branchId)->get()->keyBy('day_of_week');
        $blackouts = BranchBlackout::where('branch_id', $branchId)
            ->where('date', '>=', $today->toDateString())
            ->pluck('date')->map(fn ($d) => (string) $d)->all();

        $cacheKey = sprintf('avail:nextDates:%d:%d:%s:%d', $branchId, $partySize, $today->toDateString(), $days);

        return Cache::remember($cacheKey, 300, function () use ($today, $days, $rules, $blackouts, $partySize, $branchId) {
            $out = [];

            for ($i = 0; $i < $days; $i++) {
                $d = $today->copy()->addDays($i);
                $dow = (int) $d->dayOfWeekIso;         // 1..7
                $dowZeroBased = $dow === 7 ? 0 : $dow; // Sun=0 .. Sat=6

                if (in_array($d->toDateString(), $blackouts, true)) {
                    continue;
                }

                $rule = $rules->get($dowZeroBased);
                if (! $rule || ! $rule->is_open) {
                    continue;
                }

                if (! $this->hasAnyTimesForDate($branchId, $d->toDateString(), $partySize, $rule)) {
                    continue;
                }

                $out[$d->toDateString()] = $d->isoFormat('ddd, MMM D');
            }

            return $out;
        });
    }

    /** Return array of times: [['value'=>'19:30:00','label'=>'7:30 PM'], ...] */
    public function timesFor(int $branchId, string $date, int $partySize, ?int $doctorId = null, ?int $ignoreBookingId = null): array
    {
        $tz = config('app.timezone');
        $day = Carbon::parse($date, $tz);

        $dow = (int) $day->dayOfWeekIso;            // 1..7
        $dowZeroBased = $dow === 7 ? 0 : $dow;      // Sun=0, Mon=1..Sat=6

        // 1) Branch rule
        $rule = BranchAvailabilityRule::where('branch_id', $branchId)
            ->where('day_of_week', $dowZeroBased)
            ->first();

        if (! $rule || ! $rule->is_open) {
            return [];
        }

        // 2) Blackout
        if (BranchBlackout::where('branch_id', $branchId)->whereDate('date', $day->toDateString())->exists()) {
            return [];
        }

        // 3) Branch window (today lead time + overnight safe)
        [$start, $effectiveClose, $step, $duration] = $this->windowFor($day, $rule);

        if ($start->gte($effectiveClose)) {
            return [];
        }

        // 4) Doctor constraints (Daily override first, then weekly)
        if ($doctorId) {
            [$docStart, $docEnd] = $this->doctorWindowForDate($doctorId, $branchId, $day, $tz);

            if (! $docStart || ! $docEnd) {
                return []; // Doctor not working today or cancelled/invalid shift
            }

            // Intersect windows
            if ($docStart->gt($start)) {
                $start = $docStart->copy()->ceilMinutes($step);
            }

            if ($docEnd->lt($effectiveClose)) {
                $effectiveClose = $docEnd->copy();
            }

            if ($start->gte($effectiveClose)) {
                return [];
            }
        }

        // 5) Generate slots
        $slots = [];
        for ($t = $start->copy(); $t->lt($effectiveClose->copy()->addSecond()); $t->addMinutes($step)) {
            $time = $t->format('H:i:00');
            $isAvailable = false;

            if ($doctorId) {
                $slotEnd = $t->copy()->addMinutes($duration);
                $blockingStatuses = ['confirmed', 'pending'];

                $q = Booking::query()
                    ->where('doctor_id', $doctorId)
                    ->where('branch_id', $branchId)
                    ->whereIn('status', $blockingStatuses);

                if ($ignoreBookingId) {
                    $q->whereKeyNot($ignoreBookingId);
                }

                $isBooked = $q->where(function ($q) use ($t, $slotEnd) {
                    $q->where('res_start', '<', $slotEnd->toDateTimeString())
                        ->where('res_end', '>', $t->toDateTimeString());
                })
                    ->exists();

                $isAvailable = ! $isBooked;
            } else {
                // Restaurant/generic mode (tables)
                if ($partySize > $this->maxAllowedSize($branchId, $rule)) {
                    return [];
                }

                $isAvailable = $this->capacityLeft($branchId, $day->toDateString(), $time, $partySize, $rule) > 0;
            }

            if ($isAvailable) {
                $slots[] = ['value' => $time, 'label' => $t->format('g:i A')];
            }
        }

        return $slots;
    }

    /* ---------------- Doctor window helpers ---------------- */

    /**
     * Returns [docStart, docEnd] or [null, null] if doctor not available.
     * Priority: doctor_shifts (daily overrides) -> doctor.working_hours (weekly).
     */
    protected function doctorWindowForDate(int $doctorId, int $branchId, Carbon $day, string $tz): array
    {
        // 1) Daily override (doctor_shifts)
        $shift = DoctorShift::query()
            ->where('doctor_id', $doctorId)
            ->where('branch_id', $branchId)
            ->whereDate('shift_date', $day->toDateString())
            ->where('is_cancelled', 0)
            ->first();

        if ($shift) {
            $startStr = (string) ($shift->start_time ?? '');
            $endStr = (string) ($shift->end_time ?? '');

            if ($startStr === '' || $endStr === '') {
                return [null, null];
            }

            $docStart = Carbon::parse($day->toDateString().' '.$startStr, $tz);
            $docEnd = Carbon::parse($day->toDateString().' '.$endStr, $tz);

            if ($docEnd->lte($docStart)) {
                $docEnd->addDay();
            }

            return [$docStart, $docEnd];
        }

        // 2) Weekly fallback (doctor.working_hours)
        $doctor = Doctor::find($doctorId);
        if (! $doctor || empty($doctor->working_hours)) {
            return [null, null];
        }

        $dow = (int) $day->dayOfWeekIso;           // 1..7
        $dowZeroBased = $dow === 7 ? 0 : $dow;     // 0..6

        $schedule = collect($doctor->working_hours);
        $wh = $schedule->firstWhere('day', $dowZeroBased);

        if (! $wh || ! is_array($wh)) {
            return [null, null];
        }

        $startStr = (string) ($wh['start'] ?? '');
        $endStr = (string) ($wh['end'] ?? '');

        if ($startStr === '' || $endStr === '') {
            return [null, null];
        }

        $docStart = Carbon::parse($day->toDateString().' '.$startStr, $tz);
        $docEnd = Carbon::parse($day->toDateString().' '.$endStr, $tz);

        if ($docEnd->lte($docStart)) {
            $docEnd->addDay();
        }

        return [$docStart, $docEnd];
    }

    /* ---------------- Branch helpers ---------------- */

    protected function windowFor(Carbon $day, BranchAvailabilityRule $rule): array
    {
        $tz = config('app.timezone');

        $open = Carbon::parse($day->toDateString().' '.$rule->open_at, $tz);
        $close = Carbon::parse($day->toDateString().' '.$rule->close_at, $tz);

        // Overnight close -> push to next day
        if ($close->lte($open)) {
            $close->addDay();
        }

        // STEP = slot_step_minutes (fallback config)
        $step = (int) ($rule->slot_step_minutes ?: config('booking.slot_interval', 30));
        $step = max(5, $step);

        // DURATION = slot_length_minutes (fallback to step then config)
        $duration = (int) ($rule->slot_length_minutes ?: $rule->slot_step_minutes ?: config('booking.slot_interval', 30));
        $duration = max(5, $duration);

        // Last slot must start before (close - duration)
        $effectiveClose = $close->copy()->subMinutes($duration);

        // Today lead time + rounding to step
        $now = now($tz);
        if ($day->isSameDay($now)) {
            $lead = (int) ($rule->lead_time_minutes ?? 0);
            $minStart = $lead > 0 ? $now->copy()->addMinutes($lead) : $now;

            if ($minStart->gt($open)) {
                $open = $minStart->ceilMinutes($step);
            } elseif ($open->lt($now)) {
                $open = $now->copy()->ceilMinutes($step);
            }
        }

        return [$open, $effectiveClose, $step, $duration];
    }

    protected function capacityLeft(int $branchId, string $date, string $time, int $partySize, BranchAvailabilityRule $rule): int
    {
        $tablesAble = $this->tablesAbleForSize($branchId, $partySize);
        if ($tablesAble <= 0) {
            return 0;
        }

        $confirmed = Booking::where('branch_id', $branchId)
            ->whereDate('res_date', $date)
            ->where('res_time', $time)
            ->whereIn('status', ['confirmed', 'pending'])
            ->count();

        $holds = BookingHold::where('branch_id', $branchId)
            ->whereDate('res_date', $date)
            ->where('res_time', $time)
            ->where('expires_at', '>', now())
            ->count();

        return max(0, $tablesAble - $confirmed - $holds);
    }

    protected function hasAnyTimesForDate(int $branchId, string $date, int $partySize, BranchAvailabilityRule $rule): bool
    {
        return count($this->timesFor($branchId, $date, $partySize)) > 0;
    }

    public function timeslots(int $branchId, Carbon|string $date, int $partySize): array
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : (string) $date;
        $rows = $this->timesFor($branchId, $dateStr, $partySize);

        return array_values(array_map(fn ($r) => $r['value'], $rows));
    }

    public function getSlots(int $branchId, string $dateYmd, int $partySize, int $take = 10): array
    {
        $all = $this->timeslots($branchId, $dateYmd, $partySize);

        return array_slice($all, 0, max(1, (int) $take));
    }

    protected function maxTableCapacity(int $branchId): int
    {
        return (int) (RestaurantTable::where('branch_id', $branchId)
            ->where('status', 'available')
            ->max('capacity') ?? 0);
    }

    /** Max party size allowed considering both rules and tables. */
    public function maxAllowedSize(int $branchId, BranchAvailabilityRule $rule): int
    {
        $ruleMax = (int) ($rule->max_party_size ?: PHP_INT_MAX);
        $tableMax = $this->maxTableCapacity($branchId);

        return min($ruleMax, $tableMax);
    }

    /** How many tables can seat this party size right now (status = available). */
    protected function tablesAbleForSize(int $branchId, int $partySize): int
    {
        return (int) RestaurantTable::where('branch_id', $branchId)
            ->where('status', 'available')
            ->where('capacity', '>=', $partySize)
            ->count();
    }

    /** Optional: branch-wide max party size across open rules (useful for UI clamping). */
    public function branchWideMaxAllowedSize(int $branchId): int
    {
        $ruleMax = (int) BranchAvailabilityRule::where('branch_id', $branchId)
            ->where('is_open', 1)
            ->max('max_party_size') ?: PHP_INT_MAX;

        return min($ruleMax, $this->maxTableCapacity($branchId));
    }
}
