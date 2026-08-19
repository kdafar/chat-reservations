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

        // Full itemised bill. Each line carries its GROSS amount and whatever
        // was taken off it (package offer / promotion), so the receipt can show
        // the patient what the line normally costs and what they paid. The
        // per-line deductions are also summed, then the visit-level discount
        // (manual + coupon) applies on top:
        //   grand = gross − line offers − visit discount.
        $lines = [];
        $lineDiscounts = 0.0;

        foreach ($visit->visitCharges as $c) {
            $lineDiscounts += (float) $c->discount_amount;
            $lines[] = $this->line(
                label: $c->label ?: 'Consultation Fee',
                hint: $booking->doctor->name ?? null,
                qty: (float) ($c->qty ?? 1),
                amount: (float) $c->line_total,
                discount: (float) $c->discount_amount,
                source: $c->discount_source,
            );
        }
        foreach ($visit->visitPackages as $vp) {
            $lineDiscounts += (float) $vp->discount_amount;
            $lines[] = $this->line(
                label: $this->resolveName($vp->package->name ?? null) ?: ('Service #'.$vp->clinic_package_id),
                hint: 'Package',
                qty: (float) ($vp->qty ?? 1),
                amount: (float) $vp->line_total,
                discount: (float) $vp->discount_amount,
                source: $vp->discount_source,
            );
        }
        foreach ($visit->visitItems as $it) {
            $lineDiscounts += (float) $it->discount_amount;
            $lines[] = $this->line(
                label: $this->resolveName($it->clinicItem->name ?? null) ?: ('Item #'.$it->clinic_item_id),
                hint: 'Item',
                qty: (float) ($it->qty ?? 1),
                amount: (float) $it->line_price_total,
                discount: (float) $it->discount_amount,
                source: $it->discount_source,
            );
        }

        $subtotal = round(array_sum(array_column($lines, 'amount')), 3);   // gross
        $lineDiscounts = round($lineDiscounts, 3);                          // offers / promotions
        $visitDiscount = round((float) ($visit->discount_total ?? 0), 3);   // manual + coupon
        $couponCode = $visit->coupon_code;
        $grandTotal = round(max(0, $subtotal - $lineDiscounts - $visitDiscount), 3);

        // Everything the patient did NOT pay: per-line offers plus the
        // visit-level discount. Printed as one headline figure.
        $totalSavings = round($lineDiscounts + $visitDiscount, 3);
        $savingsPercent = $subtotal > 0 ? (int) round(($totalSavings / $subtotal) * 100) : 0;

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
            'totalSavings' => $totalSavings,
            'savingsPercent' => $savingsPercent,
            'grandTotal' => $grandTotal,
            'paid' => $paid,
            'balance' => $balance,
            'insurance' => $insurance,
        ]);
    }

    /**
     * One printed bill line. `discount` is what came off this line (a package
     * offer price or a promotion) and `net` is what the patient is actually
     * charged for it — the receipt prints both so the saving is visible where
     * it was earned, not just as a lump sum at the bottom.
     */
    private function line(
        string $label,
        ?string $hint,
        float $qty,
        float $amount,
        float $discount,
        ?string $source = null,
    ): array {
        $discount = round(max(0, min($discount, $amount)), 3);

        return [
            'label' => $label,
            'hint' => $hint,
            'qty' => $qty,
            'amount' => round($amount, 3),
            'discount' => $discount,
            'net' => round($amount - $discount, 3),
            'saved_label' => $discount > 0 ? $this->savingLabel($source) : null,
        ];
    }

    /** How a line's saving is described to the patient. */
    private function savingLabel(?string $source): string
    {
        return match ($source) {
            'offer' => 'Package offer',
            'promo' => 'Promotion',
            'manual' => 'Discount',
            default => 'Saving',
        };
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
