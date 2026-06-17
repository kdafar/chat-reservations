<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Visit;
use App\Models\VisitPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daily Reconciliation — v2 replacement for the Filament DailyReconciliationReport.
 *
 * Read-only cash-up: every confirmed payment on a given day, totalled and split
 * by payment method (cash / card / link) and by who collected it. Branch-scoped
 * via Branch::forUser, and doctor-scoped when the viewer is a doctor.
 *
 * The heavy aggregation is deferred so the filter shell paints instantly.
 */
class DailyReconciliationController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        $u = $request->user();
        if (! $u || ! $u->can('view_daily_reconciliation')) {
            abort(403, 'Not authorized to view the reconciliation report.');
        }
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $tz = config('app.timezone', 'Asia/Kuwait');
        $date = $request->input('date') ?: Carbon::now($tz)->toDateString();
        $branchId = $request->input('branch_id') ? (int) $request->input('branch_id') : null;

        return Inertia::render('Reports/DailyReconciliation', [
            'filters' => ['date' => $date, 'branch_id' => $branchId],
            'branches' => Branch::forUser($request->user())->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name])->all(),
            'report' => Inertia::defer(fn () => $this->build($request, $date, $branchId)),
        ]);
    }

    protected function build(Request $request, string $date, ?int $branchId): array
    {
        $user = $request->user();

        $allowedBranchIds = Branch::forUser($user)->pluck('id');

        $doctorProfileId = null;
        if (! $user->hasRole(['admin', 'super_admin'])) {
            $doctorProfileId = Doctor::where('user_id', $user->id)->value('id');
        }

        $payments = VisitPayment::query()
            ->with(['visit:id,branch_id,patient_id,doctor_id,booking_code', 'visit.patient:id,name', 'visit.doctor:id,name', 'collectedBy:id,name'])
            ->whereDate('paid_at', $date)
            ->where('status', 'paid')
            ->whereHas('visit', function ($q) use ($allowedBranchIds, $branchId, $doctorProfileId) {
                $q->whereIn('branch_id', $allowedBranchIds);
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
                if ($doctorProfileId) {
                    $q->where('doctor_id', $doctorProfileId);
                }
            })
            ->orderByDesc('paid_at')
            ->get();

        $byMethod = $payments->groupBy(fn ($p) => strtolower($p->method ?: 'unknown'))
            ->map(fn ($rows) => ['count' => $rows->count(), 'amount' => (float) $rows->sum('amount')])
            ->sortByDesc('amount');

        $byCollector = $payments->groupBy(fn ($p) => $p->collectedBy->name ?? 'System (online)')
            ->map(fn ($rows) => ['count' => $rows->count(), 'amount' => (float) $rows->sum('amount')])
            ->sortByDesc('amount');

        // Refunds / voids on the day (shown separately from collections).
        $refunds = VisitPayment::query()
            ->whereDate('paid_at', $date)
            ->whereIn('status', ['refunded', 'void'])
            ->whereHas('visit', function ($q) use ($allowedBranchIds, $branchId, $doctorProfileId) {
                $q->whereIn('branch_id', $allowedBranchIds);
                if ($branchId) $q->where('branch_id', $branchId);
                if ($doctorProfileId) $q->where('doctor_id', $doctorProfileId);
            })->get();

        // Outstanding: today's completed visits billed vs paid.
        $visitScope = Visit::query()->whereDate('completed_at', $date)->where('status', 'completed')
            ->whereIn('branch_id', $allowedBranchIds)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($doctorProfileId, fn ($q) => $q->where('doctor_id', $doctorProfileId));
        $billed = (float) (clone $visitScope)
            ->selectRaw('COALESCE(SUM(COALESCE(fees_total,0)+COALESCE(packages_price_total,0)+COALESCE(items_price_total,0)-COALESCE(discount_total,0)),0) as v')->value('v');
        $completedIds = (clone $visitScope)->pluck('id');
        $paidForCompleted = $completedIds->isEmpty() ? 0.0 : (float) VisitPayment::whereIn('visit_id', $completedIds)->where('status', 'paid')->sum('amount');
        $unpaidCount = (clone $visitScope)
            ->whereRaw("(COALESCE(fees_total,0)+COALESCE(packages_price_total,0)+COALESCE(items_price_total,0)-COALESCE(discount_total,0)) - COALESCE((SELECT SUM(amount) FROM visit_payments WHERE visit_payments.visit_id = visits.id AND visit_payments.status = 'paid'),0) > 0.005")
            ->count();

        $total = (float) $payments->sum('amount');
        $count = $payments->count();

        return [
            'total_collected' => $total,
            'count' => $count,
            'avg_transaction' => $count > 0 ? round($total / $count, 3) : 0,
            'refunds' => ['count' => $refunds->count(), 'amount' => (float) $refunds->sum('amount')],
            'outstanding' => ['total' => max(0.0, round($billed - $paidForCompleted, 3)), 'unpaid_count' => (int) $unpaidCount],
            'by_method' => $byMethod->map(fn ($v, $k) => ['method' => $k, 'count' => $v['count'], 'amount' => $v['amount']])->values()->all(),
            'by_collector' => $byCollector->map(fn ($v, $k) => ['collector' => $k, 'count' => $v['count'], 'amount' => $v['amount']])->values()->all(),
            'rows' => $payments->map(fn (VisitPayment $p) => [
                'id' => $p->id,
                'time' => optional($p->paid_at)->format('H:i'),
                'visit' => $p->visit?->booking_code,
                'patient' => $p->visit?->patient?->name,
                'doctor' => $p->visit?->doctor?->name,
                'method' => $p->method,
                'kind' => $p->kind,
                'reference' => $p->reference_no,
                'collector' => $p->collectedBy->name ?? 'System (online)',
                'amount' => (float) $p->amount,
            ])->all(),
        ];
    }
}
