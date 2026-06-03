<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Visit;
use App\Models\VisitPayment;
use Illuminate\Http\Request;

class BookingReceiptController extends Controller
{
    public function show(Request $request, Booking $booking)
    {
        if (! auth()->user()) {
            abort(403);
        }

        $booking->load(['branch.partner', 'doctor', 'patient', 'contact']);

        $visit = Visit::where('booking_id', $booking->id)
            ->with(['visitCharges', 'visitPackages.package', 'visitItems.clinicItem'])
            ->first();

        if (! $visit) {
            abort(404, 'No visit record found for this booking.');
        }

        // Full itemised bill. Each line is shown at its GROSS amount; per-line
        // promotions/offers are summed and shown as a single deduction, then the
        // visit-level discount (manual + coupon). Mirrors the v2 console math:
        // grand = gross − line offers − visit discount.
        $lines = [];
        $lineDiscounts = 0.0;

        foreach ($visit->visitCharges as $c) {
            $lineDiscounts += (float) $c->discount_amount;
            $lines[] = [
                'label' => $c->label ?: 'Consultation Fee',
                'hint' => $booking->doctor->name ?? null,
                'qty' => (float) ($c->qty ?? 1),
                'amount' => (float) $c->line_total,
            ];
        }
        foreach ($visit->visitPackages as $vp) {
            $lineDiscounts += (float) $vp->discount_amount;
            $lines[] = [
                'label' => $this->resolveName($vp->package->name ?? null) ?: ('Service #'.$vp->clinic_package_id),
                'hint' => 'Service',
                'qty' => (float) ($vp->qty ?? 1),
                'amount' => (float) $vp->line_total,
            ];
        }
        foreach ($visit->visitItems as $it) {
            $lineDiscounts += (float) $it->discount_amount;
            $lines[] = [
                'label' => $this->resolveName($it->clinicItem->name ?? null) ?: ('Item #'.$it->clinic_item_id),
                'hint' => 'Item',
                'qty' => (float) ($it->qty ?? 1),
                'amount' => (float) $it->line_price_total,
            ];
        }

        $subtotal = round(array_sum(array_column($lines, 'amount')), 3);   // gross
        $lineDiscounts = round($lineDiscounts, 3);                          // offers / promotions
        $visitDiscount = round((float) ($visit->discount_total ?? 0), 3);   // manual + coupon
        $couponCode = $visit->coupon_code;
        $grandTotal = round(max(0, $subtotal - $lineDiscounts - $visitDiscount), 3);

        // All settled payments on this visit (voided ones are soft-deleted out).
        $payments = VisitPayment::where('visit_id', $visit->id)
            ->where('status', 'paid')
            ->orderBy('paid_at')
            ->get();

        $paid = round((float) $payments->sum('amount'), 3);
        $balance = round(max(0, $grandTotal - $paid), 3);

        // Insurance (informational note only — does not change the balance).
        $insurance = $this->insuranceNote($visit);

        return view('bookings.receipt-print', [
            'booking' => $booking,
            'visit' => $visit,
            'partner' => $booking->branch->partner ?? null,
            'doctor' => $booking->doctor,
            'patient' => $booking->patient ?? $booking->contact,
            'lines' => $lines,
            'payments' => $payments,
            'subtotal' => $subtotal,
            'lineDiscounts' => $lineDiscounts,
            'visitDiscount' => $visitDiscount,
            'couponCode' => $couponCode,
            'grandTotal' => $grandTotal,
            'paid' => $paid,
            'balance' => $balance,
            'insurance' => $insurance,
        ]);
    }

    /**
     * Build the informational insurance note for the receipt, or null when the
     * module is absent / there is no active (non-void) claim for the visit.
     */
    private function insuranceNote(Visit $visit): ?array
    {
        $claimClass = \App\Models\Insurance\InsuranceClaim::class;
        if (! class_exists($claimClass)) {
            return null;
        }

        $claim = $claimClass::where('visit_id', $visit->id)
            ->where('status', '!=', $claimClass::STATUS_VOID)
            ->with(['patientPolicy.insurer', 'patientPolicy.plan'])
            ->latest('id')
            ->first();

        if (! $claim) {
            return null;
        }

        $policy = $claim->patientPolicy;

        return [
            'claim_number' => $claim->claim_number,
            'status' => $claim->status,
            'insurer' => $policy?->insurer?->name,
            'plan' => $this->resolveName($policy?->plan?->name ?? null),
            'policy_number' => $policy?->policy_number,
            'total_charged' => (float) $claim->total_charged,
            'insurer_payable' => (float) $claim->insurer_payable,
            'patient_copay' => (float) $claim->patient_copay,
        ];
    }

    /** Resolve a translatable name (array or string) to the current locale. */
    private function resolveName($raw): string
    {
        if (is_string($raw)) {
            return $raw;
        }
        if (! is_array($raw)) {
            return '';
        }
        $locale = app()->getLocale();

        return (string) ($raw[$locale] ?? $raw['en'] ?? $raw['ar'] ?? reset($raw) ?? '');
    }
}
