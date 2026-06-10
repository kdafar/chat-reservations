<?php

namespace App\Services\Clinic;

use App\Models\Booking;
use App\Models\FollowUpPlan;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FollowUpService
{
    /**
     * Create/update follow-up plan from a visit (idempotent by source_visit_id).
     * If auto_create_booking=true, attempts to create a draft/pending booking and link it.
     */
    public function syncFromVisit(Visit $visit, ?bool $autoCreateBooking = null, ?int $actorUserId = null): ?FollowUpPlan
    {
        if (! config('clinic.follow_up_enabled', true)) {
            return null;
        }

        $suggestedAt = $visit->follow_up_date ?? null;
        if (! $suggestedAt) {
            return null; // no plan if no follow-up date
        }

        $patientId = (int) ($visit->patient_id ?? 0);
        if (! $patientId) {
            return null;
        }

        $auto = $autoCreateBooking ?? (bool) config('clinic.follow_up_auto_create_booking_default', false);

        return DB::transaction(function () use ($visit, $suggestedAt, $patientId, $auto, $actorUserId) {
            $existing = FollowUpPlan::query()->where('source_visit_id', $visit->id)->first();

            // If a booking already exists, decide whether it's still valid for the
            // (possibly re-saved) visit: same day + same doctor + same branch. If
            // the schedule changed, release the stale booking so we can re-book.
            $scheduleChanged = false;
            if ($existing && $existing->booking_id) {
                $scheduleChanged =
                    optional($existing->suggested_at)->toDateString() !== Carbon::parse($suggestedAt)->toDateString()
                    || (int) $existing->doctor_id !== (int) ($visit->doctor_id ?? 0)
                    || (int) $existing->branch_id !== (int) ($visit->branch_id ?? 0);

                if ($scheduleChanged) {
                    $this->releaseFollowUpBooking($existing); // cancels old booking + clears booking_id
                    $existing->refresh();
                }
            }

            // Preserve a still-valid 'booked' plan; otherwise it's open until (re)booked.
            $keepBooked = $existing && $existing->booking_id && ! $scheduleChanged;

            $plan = FollowUpPlan::query()->updateOrCreate(
                ['source_visit_id' => $visit->id],
                [
                    'patient_id' => $patientId,
                    'doctor_id' => $visit->doctor_id ?? null,
                    'branch_id' => $visit->branch_id ?? null,
                    'suggested_at' => $suggestedAt,
                    'auto_create_booking' => $auto,
                    // Don't clobber a live booking back to 'open' (the bug this guards).
                    'status' => $keepBooked ? 'booked' : 'open',
                ]
            );

            // Auto-create booking only if requested and not already linked.
            if ($auto && ! $plan->booking_id) {
                $result = $this->tryCreateDraftBooking($visit, $plan, $actorUserId);

                if ($result['booking_id']) {
                    $plan->forceFill([
                        'booking_id' => $result['booking_id'],
                        'status' => 'booked',
                    ])->save();
                } elseif ($result['status'] === 'needs_scheduling') {
                    // Doctor is off that day or fully booked — don't create a bad
                    // booking; flag it so reception schedules the follow-up by hand.
                    $plan->forceFill(['status' => 'needs_scheduling'])->save();
                }
                // 'skipped' (missing phone/branch) leaves the plan 'open' as before.
            }

            return $plan->refresh();
        });
    }

    /**
     * Attempt to auto-book the follow-up at the FIRST free slot on the suggested
     * day (checking the doctor's real availability), rather than blindly stamping
     * the visit's follow_up time onto a booking.
     *
     * @return array{booking_id: ?int, status: string}  status ∈ booked | needs_scheduling | skipped
     */
    protected function tryCreateDraftBooking(Visit $visit, FollowUpPlan $plan, ?int $actorUserId = null): array
    {
        $skipped = ['booking_id' => null, 'status' => 'skipped'];

        try {
            $suggested = $plan->suggested_at;
            if (! $suggested) {
                return $skipped;
            }

            $branchId = (int) ($plan->branch_id ?? $visit->branch_id ?? 0);
            if (! $branchId) {
                return $skipped;
            }

            // bookings.msisdn is NOT NULL — try best sources (patient, then original booking).
            $msisdn = (string) (
                $visit->patient?->phone
                ?? $visit->patient?->msisdn
                ?? $visit->booking?->msisdn
                ?? ''
            );
            if ($msisdn === '') {
                return $skipped; // can't satisfy a required field — don't create garbage
            }

            // party_size is NOT NULL (use original booking if exists; else 1).
            $partySize = max(1, (int) ($visit->booking?->party_size ?? 1));

            $date = $suggested->toDateString();
            $doctorId = (int) ($visit->doctor_id ?? 0) ?: null;

            // Pick the first free slot that day from the real availability grid
            // (honours the branch rule, blackouts, the doctor's shift, and existing
            // bookings). Empty = doctor off / fully booked / branch closed.
            $slots = app(\App\Services\AvailabilityService::class)
                ->timesFor($branchId, $date, $partySize, $doctorId);

            if (empty($slots)) {
                return ['booking_id' => null, 'status' => 'needs_scheduling'];
            }

            $time = (string) $slots[0]['value']; // 'HH:MM:00'
            $resStart = \Illuminate\Support\Carbon::parse($date.' '.$time, config('app.timezone'));

            // res_end = start + the branch's slot length for that weekday (fallback 30m).
            $dow = $resStart->dayOfWeekIso;
            $dowZero = $dow === 7 ? 0 : $dow;
            $slotLen = (int) (\App\Models\BranchAvailabilityRule::query()
                ->where('branch_id', $branchId)->where('day_of_week', $dowZero)
                ->value('slot_length_minutes') ?: 30);

            $payload = [
                'branch_id' => $branchId,
                'doctor_id' => $visit->doctor_id ?? null,
                'patient_id' => $plan->patient_id,

                'msisdn' => $msisdn,
                'party_size' => $partySize,

                'res_date' => $date,
                'res_time' => $time,
                'res_start' => $resStart,
                'res_end' => $resStart->copy()->addMinutes($slotLen),

                'status' => config('clinic.follow_up_booking_status', 'pending'),

                'source' => 'follow_up',
                'source_ref' => 'follow_up_plan:'.$plan->id,
                'notes' => 'Follow-up from visit #'.$visit->id,

                // bookings.booking_code is NOT NULL + unique + varchar(16)
                'booking_code' => $this->generateBookingCode16(),
            ];

            $booking = \App\Models\Booking::query()->create($payload);

            return ['booking_id' => (int) $booking->id, 'status' => 'booked'];
        } catch (\Throwable $e) {
            \Log::warning('[CLINIC] Follow-up auto booking create failed', [
                'visit_id' => $visit->id,
                'follow_up_plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);
            report($e);

            return $skipped;
        }
    }

    /**
     * Release a stale follow-up booking when the plan is being rescheduled.
     * Only cancels a still-open, follow-up-sourced booking (never one the patient
     * already checked in to / completed), then unlinks it from the plan.
     */
    protected function releaseFollowUpBooking(FollowUpPlan $plan): void
    {
        if (! $plan->booking_id) {
            return;
        }

        $booking = Booking::find($plan->booking_id);
        if ($booking
            && $booking->source === 'follow_up'
            && empty($booking->checked_in_at)
            && in_array($booking->status, ['draft', 'pending', 'confirmed'], true)) {
            $booking->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
        }

        $plan->forceFill(['booking_id' => null])->save();
    }

    protected function generateBookingCode16(): string
    {
        // 16 chars max, avoid confusing chars
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 16; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $code;
    }
}
