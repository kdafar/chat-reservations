<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\DoctorCompensationLedger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * My Earnings — a doctor's own daily earnings, for end-of-day closing.
 *
 * Self-service: the logged-in user must have a doctor profile; they only ever
 * see their OWN compensation rows (filtered by their doctor_id, and further
 * branch-scoped by the ledger's global scope). Defaults to today.
 */
class MyEarningsController extends Controller
{
    public function index(Request $request): Response
    {
        $doctor = $request->user()?->doctorProfile;
        if (! $doctor) {
            abort(403, 'Only doctors can view personal earnings.');
        }

        $date = $request->input('date') ?: now()->toDateString();

        // Filter by the VISIT's completion date (not the ledger row's created_at)
        // so a visit completed late evening always lands on its own day.
        $ledgers = DoctorCompensationLedger::query()
            ->with([
                'visit' => fn ($q) => $q->select('id', 'booking_code', 'patient_id', 'completed_at', 'fees_total', 'packages_price_total', 'items_price_total', 'discount_total')
                    ->withSum(['payments as paid_sum' => fn ($p) => $p->where('status', 'paid')], 'amount'),
                'visit.patient:id,name',
            ])
            ->where('doctor_id', $doctor->id)
            ->whereHas('visit', fn ($v) => $v->whereDate('completed_at', $date))
            ->get()
            ->sortBy(fn ($l) => $l->visit?->completed_at)
            ->values();

        $running = 0.0;
        $rows = $ledgers->map(function (DoctorCompensationLedger $l) use (&$running) {
            $running += (float) $l->doctor_cut_amount;
            $v = $l->visit;
            $billed = $v ? ((float) $v->fees_total + (float) $v->packages_price_total + (float) $v->items_price_total - (float) $v->discount_total) : 0.0;
            $paid = $v ? (float) ($v->paid_sum ?? 0) : 0.0;
            return [
                'id' => $l->id,
                'time' => optional($v?->completed_at)->format('H:i'),
                'visit' => $v?->booking_code ?? ('#'.$l->visit_id),
                'patient' => $v?->patient?->name,
                'type' => $l->type_snapshot,
                'fees' => (float) $l->fees_snapshot,
                'profit' => (float) $l->profit_snapshot,
                'cut' => (float) $l->doctor_cut_amount,
                'running' => round($running, 3),
                'unpaid' => ($billed - $paid) > 0.005,
            ];
        });

        $cutSum = round((float) $rows->sum('cut'), 3);
        $profitSum = round((float) $rows->sum('profit'), 3);
        $feesSum = round((float) $rows->sum('fees'), 3);
        $unpaidCount = $rows->where('unpaid', true)->count();

        return Inertia::render('Reports/MyEarnings', [
            'filters' => ['date' => $date],
            'doctor' => ['id' => $doctor->id, 'name' => $doctor->name],
            'rows' => $rows->values()->all(),
            'summary' => [
                'visits' => $rows->count(),
                'fees' => $feesSum,
                'profit' => $profitSum,
                'cut' => $cutSum,
                'avg_per_visit' => $rows->count() > 0 ? round($cutSum / $rows->count(), 3) : 0,
                'effective_rate' => $profitSum > 0.0001 ? round(($cutSum / $profitSum) * 100, 1) : 0,
                'unpaid_count' => $unpaidCount,
            ],
        ]);
    }
}
