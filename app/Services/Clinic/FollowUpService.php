<?php

namespace App\Services\Clinic;

use App\Models\Booking;
use App\Models\FollowUpPlan;
use App\Models\Visit;
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
            $plan = FollowUpPlan::query()->updateOrCreate(
                ['source_visit_id' => $visit->id],
                [
                    'patient_id' => $patientId,
                    'doctor_id' => $visit->doctor_id ?? null,
                    'branch_id' => $visit->branch_id ?? null,
                    'suggested_at' => $suggestedAt,
                    'auto_create_booking' => $auto,
                    'status' => 'open',
                ]
            );

            // Auto-create booking only if requested and not already linked
            if ($auto && ! $plan->booking_id) {
                $bookingId = $this->tryCreateDraftBooking($visit, $plan, $actorUserId);

                if ($bookingId) {
                    $plan->forceFill([
                        'booking_id' => $bookingId,
                        'status' => 'booked',
                    ])->save();
                }
            }

            return $plan->refresh();
        });
    }

    protected function tryCreateDraftBooking(Visit $visit, FollowUpPlan $plan, ?int $actorUserId = null): ?int
    {
        try {
            $suggested = $plan->suggested_at;
            if (! $suggested) {
                return null;
            }

            $branchId = (int) ($plan->branch_id ?? $visit->branch_id ?? 0);
            if (! $branchId) {
                return null;
            }

            // bookings.msisdn is NOT NULL in your schema
            // try best sources (patient first, then original booking)
            $msisdn = (string) (
                $visit->patient?->phone
                ?? $visit->patient?->msisdn
                ?? $visit->booking?->msisdn
                ?? ''
            );

            // If we cannot satisfy a NOT NULL required field, do not create garbage bookings.
            if ($msisdn === '') {
                return null;
            }

            // party_size is NOT NULL (use original booking if exists; else 1)
            $partySize = (int) ($visit->booking?->party_size ?? 1);
            if ($partySize <= 0) {
                $partySize = 1;
            }

            $payload = [
                'branch_id' => $branchId,
                'doctor_id' => $visit->doctor_id ?? null,
                'patient_id' => $plan->patient_id,

                'msisdn' => $msisdn,
                'party_size' => $partySize,

                'res_date' => $suggested->toDateString(),
                'res_time' => $suggested->format('H:i:s'),
                'res_start' => $suggested, // nullable, but helpful

                // keep legacy enum values; default is pending anyway
                'status' => config('clinic.follow_up_booking_status', 'pending'),

                // attribution
                'source' => 'follow_up',
                'source_ref' => 'follow_up_plan:'.$plan->id,

                'notes' => 'Follow-up from visit #'.$visit->id,

                // bookings.booking_code is NOT NULL + unique + varchar(16)
                'booking_code' => $this->generateBookingCode16(),
            ];

            $booking = \App\Models\Booking::query()->create($payload);

            return (int) $booking->id;
        } catch (\Throwable $e) {
            \Log::warning('[CLINIC] Follow-up auto booking create failed', [
                'visit_id' => $visit->id,
                'follow_up_plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);
            report($e);

            return null;
        }
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
