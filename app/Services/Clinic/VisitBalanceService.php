<?php

namespace App\Services\Clinic;

use App\Models\Visit;
use App\Models\VisitCharge;
use App\Models\VisitItem;
use App\Models\VisitPackage;
use App\Models\VisitPayment;

/**
 * The one place that answers "how much does this visit still owe?".
 *
 * Every money-in path has to agree on that number: the discharge guard refuses
 * to close a visit while it is positive, and the payment endpoints refuse to
 * collect more than it. Two implementations of the same sum drift, and a
 * drifted balance is either a blocked discharge or an over-collection.
 *
 * Line totals are clamped at zero individually — a line whose discount exceeds
 * its own total must not bankroll the rest of the bill.
 */
class VisitBalanceService
{
    /** Rounding slack, in KWD. Below this a difference isn't real money. */
    public const TOLERANCE = 0.005;

    /** Everything charged on the visit, net of line and visit-level discounts. */
    public function billed(Visit $visit): float
    {
        $charges = (float) VisitCharge::query()->where('visit_id', $visit->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN line_total - discount_amount > 0 THEN line_total - discount_amount ELSE 0 END), 0) as t')
            ->value('t');

        $packages = (float) VisitPackage::query()->where('visit_id', $visit->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN line_total - discount_amount > 0 THEN line_total - discount_amount ELSE 0 END), 0) as t')
            ->value('t');

        $items = (float) VisitItem::query()->where('visit_id', $visit->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN line_price_total - discount_amount > 0 THEN line_price_total - discount_amount ELSE 0 END), 0) as t')
            ->value('t');

        return round($charges + $packages + $items - (float) ($visit->discount_total ?? 0), 3);
    }

    /** Money already collected (patient + insurer portions). */
    public function paid(Visit $visit): float
    {
        return round((float) VisitPayment::query()
            ->where('visit_id', $visit->id)
            ->where('status', 'paid')
            ->sum('amount'), 3);
    }

    /** What is still owed. Never negative — an overpaid visit owes nothing. */
    public function outstanding(Visit $visit): float
    {
        return max(0.0, round($this->billed($visit) - $this->paid($visit), 3));
    }

    /**
     * Why this payment can't be taken, or null when it can. Callers turn the
     * message into a 422 — the amount is operator input, not a programming
     * error, so it deserves a readable answer rather than an exception.
     */
    public function rejectPayment(Visit $visit, float $amount): ?string
    {
        if ($amount <= 0) {
            return 'Enter a payment amount greater than zero.';
        }

        $outstanding = $this->outstanding($visit);

        if ($outstanding <= self::TOLERANCE) {
            return 'This visit is already settled — nothing left to collect.';
        }

        if ($amount > $outstanding + self::TOLERANCE) {
            return 'Payment exceeds the outstanding balance of '
                .number_format($outstanding, 3).' KWD. Enter that amount or less.';
        }

        return null;
    }
}
