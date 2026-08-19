<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Branch;
use App\Models\BranchAvailabilityRule;
use App\Models\BranchBlackout;
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

        $rules = BranchAvailabilityRule::withoutGlobalScopes()->where('branch_id', $branchId)->get()->keyBy('day_of_week');
        $blackouts = BranchBlackout::withoutGlobalScopes()->where('branch_id', $branchId)
            ->where('date', '>=', $today->toDateString())
            ->pluck('date')->map(fn ($d) => (string) $d)->all();

        // The version suffix lets a working-hours edit invalidate this instantly
        // instead of waiting out the 5-minute TTL.
        $cacheKey = sprintf(
            'avail:nextDates:%d:%d:%s:%d:v%d',
            $branchId, $partySize, $today->toDateString(), $days,
            \App\Services\Clinic\WorkingHoursService::scheduleVersion($branchId),
        );

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

        // 0) A branch that's switched off takes no bookings on any channel.
        // Only the public branch list used to check this, so deep links, v2,
        // WhatsApp and follow-up auto-booking all walked straight past it.
        if (! app(\App\Services\Clinic\WorkingHoursService::class)->branchIsBookable($branchId)) {
            return [];
        }

        // 1) Branch rule
        //
        // withoutGlobalScopes throughout this method: BelongsToBranchScope
        // narrows these tables to the caller's own branch/doctor, which turns
        // a colleague's schedule into an empty grid and — worse, on bookings —
        // hides the clashes that are supposed to block a slot.
        $rule = BranchAvailabilityRule::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('day_of_week', $dowZeroBased)
            ->first();

        if (! $rule || ! $rule->is_open) {
            return [];
        }

        // 2) Blackout
        if (BranchBlackout::withoutGlobalScopes()->where('branch_id', $branchId)->whereDate('date', $day->toDateString())->exists()) {
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

            // A doctor with their own appointment length overrides the branch's
            // — a 60-minute laser session and a 10-minute follow-up can't share
            // one branch-wide number. Their slots then run back-to-back at that
            // length, so recompute the last usable start from the new duration.
            $ownLength = app(\App\Services\Clinic\WorkingHoursService::class)->doctorSlotMinutes($doctorId);
            if ($ownLength) {
                $effectiveClose->addMinutes($duration - $ownLength);
                $duration = $ownLength;
                $step = $ownLength;
            }

            // Intersect windows. $effectiveClose is the last time a slot may
            // START, so the doctor's end has to lose the appointment length
            // too — otherwise the final slot runs past the end of their shift.
            if ($docStart->gt($start)) {
                $start = $docStart->copy()->ceilMinutes($step);
            }

            $docLastStart = $docEnd->copy()->subMinutes($duration);
            if ($docLastStart->lt($effectiveClose)) {
                $effectiveClose = $docLastStart;
            }

            if ($start->gte($effectiveClose)) {
                return [];
            }
        }

        // The doctor's booked slots for this window, fetched once. This used to
        // be an exists() per slot, which made nextDates — 25 dates × 32 slots
        // per doctor — run hundreds of queries to answer one date list.
        //
        // No branch filter: the guard (bookingProblem) blocks on any overlapping
        // booking for this doctor, so filtering by branch here would offer a
        // slot the guard then rejects.
        $taken = collect();
        if ($doctorId) {
            $taken = Booking::query()->withoutGlobalScopes()
                ->where('doctor_id', $doctorId)
                ->whereIn('status', ['confirmed', 'pending'])
                ->when($ignoreBookingId, fn ($q) => $q->whereKeyNot($ignoreBookingId))
                ->whereNotNull('res_start')
                ->where('res_start', '<', $effectiveClose->copy()->addMinutes($duration)->toDateTimeString())
                ->where('res_end', '>', $start->toDateTimeString())
                ->get(['res_start', 'res_end']);
        }

        // 5) Generate slots
        $slots = [];
        for ($t = $start->copy(); $t->lt($effectiveClose->copy()->addSecond()); $t->addMinutes($step)) {
            $time = $t->format('H:i:00');
            $isAvailable = false;

            if ($doctorId) {
                $slotEnd = $t->copy()->addMinutes($duration);

                // res_start/res_end are cast to Carbon, so these compare as
                // instants, not strings. A booking with no end is treated as a
                // point in time — the same reading slotsFor() uses.
                $isAvailable = ! $taken->contains(function ($b) use ($t, $slotEnd) {
                    $bookedEnd = $b->res_end ?: $b->res_start->copy()->addMinute();

                    return $b->res_start->lt($slotEnd) && $bookedEnd->gt($t);
                });
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
     *
     * WorkingHoursService owns this answer. There used to be a second copy of
     * the rules here, and the two drifted: this one honoured an overnight
     * window while the booking guard silently dropped it, so the grid offered
     * times the guard then refused. One implementation, one answer.
     */
    protected function doctorWindowForDate(int $doctorId, int $branchId, Carbon $day, string $tz): array
    {
        [$startMin, $endMin] = app(\App\Services\Clinic\WorkingHoursService::class)
            ->doctorWindowForDate($doctorId, $branchId, $day->toDateString());

        if ($startMin === null || $endMin === null) {
            return [null, null];
        }

        // Minutes past this date's midnight — an overnight window comes back
        // over 1440 and lands on the next day, which is what we want.
        return [
            $day->copy()->startOfDay()->addMinutes($startMin),
            $day->copy()->startOfDay()->addMinutes($endMin),
        ];
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
                // Advance along the grid the branch already uses rather than
                // ceilMinutes(), which snaps to absolute clock boundaries: with
                // an open_at of 09:10 that hands today a 09:15/09:30 grid while
                // every other day runs 09:10/09:25, and the two disagree about
                // which times exist.
                $skip = (int) ceil($open->diffInMinutes($minStart) / $step);
                $open = $open->copy()->addMinutes($skip * $step);
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

    /**
     * Is any slot bookable on this date?
     *
     * A branch with doctors is a clinic, and a clinic's calendar is decided by
     * who is on shift. This used to always fall through to the table-capacity
     * path, which tied the appointment calendar to `restaurant_tables` rows:
     * party sizes above the largest table emptied the whole date list, and a
     * clinic with no table rows at all had no bookable dates.
     */
    protected function hasAnyTimesForDate(int $branchId, string $date, int $partySize, BranchAvailabilityRule $rule): bool
    {
        $doctorIds = app(\App\Services\Clinic\WorkingHoursService::class)->bookableDoctorIds($branchId);

        if ($doctorIds !== []) {
            foreach ($doctorIds as $doctorId) {
                if ($this->timesFor($branchId, $date, $partySize, $doctorId) !== []) {
                    return true; // one free doctor is enough to offer the date
                }
            }

            return false;
        }

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
