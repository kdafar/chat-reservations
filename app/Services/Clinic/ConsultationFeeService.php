<?php

namespace App\Services\Clinic;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Visit;
use App\Models\VisitCharge;
use Illuminate\Support\Collection;

/**
 * Answers "what is the consultation fee for this visit?" and keeps the
 * 'Consultation Fee' charge in step with the visit's doctor.
 *
 * A doctor's consultation_fee may be 0 (the doctor works free of charge), and
 * it can change after a patient is booked. So once a 'Consultation Fee'
 * charge has been raised on the visit, that charge — what the patient was
 * actually billed — is the fee. The doctor's current fee only applies while
 * nothing has been billed yet.
 */
class ConsultationFeeService
{
    public const LABEL = 'Consultation Fee';

    public function __construct(private VisitBalanceService $balance) {}

    /** Fee for a visit: its consultation charge, else the doctor's current fee. */
    public function forVisit(Visit $visit): float
    {
        return $this->chargedByVisit([$visit->id])[$visit->id] ?? $this->doctorFee($visit->doctor);
    }

    /** Fee for a booking: its visit's consultation charge, else the doctor's current fee. */
    public function forBooking(Booking $booking): float
    {
        $visit = Visit::query()->where('booking_id', $booking->id)->first();

        return $visit ? $this->forVisit($visit->setRelation('doctor', $booking->doctor)) : $this->doctorFee($booking->doctor);
    }

    /**
     * Bulk forVisit() for a screen of visits — one query, no N+1.
     *
     * @param  Collection<int, Visit>  $visits  with `doctor` loaded
     * @return array<int, float> keyed by visit id
     */
    public function forVisits(Collection $visits): array
    {
        $charged = $this->chargedByVisit($visits->pluck('id')->all());

        return $visits->mapWithKeys(fn (Visit $v) => [
            $v->id => $charged[$v->id] ?? $this->doctorFee($v->doctor),
        ])->all();
    }

    /**
     * Bulk forBooking() for bookings not yet checked in.
     *
     * @param  Collection<int, Booking>  $bookings  with `doctor` loaded
     * @return array<int, float> keyed by booking id
     */
    public function forBookings(Collection $bookings): array
    {
        $visitIdByBooking = Visit::query()
            ->whereIn('booking_id', $bookings->pluck('id')->all())
            ->pluck('id', 'booking_id')->all();
        $charged = $this->chargedByVisit(array_values($visitIdByBooking));

        return $bookings->mapWithKeys(function (Booking $b) use ($visitIdByBooking, $charged) {
            $visitId = $visitIdByBooking[$b->id] ?? null;

            return [$b->id => ($visitId && isset($charged[$visitId])) ? $charged[$visitId] : $this->doctorFee($b->doctor)];
        })->all();
    }

    /**
     * Re-price the visit's consultation charge for a new doctor: raise it,
     * change it, or remove it when the new doctor works free of charge.
     *
     * Refuses (RuntimeException) when the new fee would leave the visit
     * billed for less than has already been collected — that money has to be
     * voided or refunded first, not silently turned into an overpayment.
     */
    public function repriceForDoctor(Visit $visit, Doctor $doctor): void
    {
        $newFee = $this->doctorFee($doctor);
        $charge = VisitCharge::query()
            ->where('visit_id', $visit->id)
            ->where('label', self::LABEL)
            ->first();

        $oldNet = $charge ? (float) $charge->net_total : 0.0;
        $oldFee = $charge ? (float) $charge->line_total : 0.0;
        if (abs($newFee - $oldFee) < VisitBalanceService::TOLERANCE) {
            return;
        }

        // Keep an existing line discount, but never beyond the new fee.
        $discount = $charge ? min((float) $charge->discount_amount, $newFee) : 0.0;
        $newNet = max(0.0, $newFee - $discount);

        $billedAfter = $this->balance->billed($visit) - $oldNet + $newNet;
        $paid = $this->balance->paid($visit);
        if ($billedAfter + VisitBalanceService::TOLERANCE < $paid) {
            throw new \RuntimeException(sprintf(
                "The patient has already paid %s KWD, more than this visit would cost with the new doctor (%s KWD). Void or refund the payment first.",
                number_format($paid, 3),
                number_format(max(0, $billedAfter), 3),
            ));
        }

        if ($newFee <= 0) {
            $charge?->delete();

            return;
        }

        if ($charge) {
            $charge->update([
                'qty' => 1,
                'unit_price_snapshot' => $newFee,
                'line_total' => $newFee,
                'discount_amount' => $discount,
            ]);

            return;
        }

        VisitCharge::create([
            'visit_id' => $visit->id,
            'branch_id' => (int) $visit->branch_id,
            'label' => self::LABEL,
            'qty' => 1,
            'unit_price_snapshot' => $newFee,
            'line_total' => $newFee,
            'added_by_user_id' => auth()->id(),
        ]);
    }

    public function doctorFee(?Doctor $doctor): float
    {
        return round(max(0.0, (float) ($doctor?->consultation_fee ?? 0)), 3);
    }

    /** @return array<int, float> consultation charge total keyed by visit id (only visits that have one) */
    private function chargedByVisit(array $visitIds): array
    {
        if ($visitIds === []) {
            return [];
        }

        return VisitCharge::query()
            ->whereIn('visit_id', $visitIds)
            ->where('label', self::LABEL)
            ->groupBy('visit_id')
            ->selectRaw('visit_id, SUM(line_total) as t')
            ->pluck('t', 'visit_id')
            ->map(fn ($t) => round((float) $t, 3))
            ->all();
    }
}
