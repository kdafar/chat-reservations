<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Doctor;
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
        if (! $u || ! $u->hasRole(['admin', 'super_admin', 'clinic_admin', 'accountant'])) {
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
            ->with(['visit:id,branch_id,patient_id,doctor_id', 'visit.patient:id,name', 'visit.doctor:id,name', 'collectedBy:id,name'])
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

        return [
            'total_collected' => (float) $payments->sum('amount'),
            'count' => $payments->count(),
            'by_method' => $byMethod->map(fn ($v, $k) => ['method' => $k, 'count' => $v['count'], 'amount' => $v['amount']])->values()->all(),
            'by_collector' => $byCollector->map(fn ($v, $k) => ['collector' => $k, 'count' => $v['count'], 'amount' => $v['amount']])->values()->all(),
            'rows' => $payments->map(fn (VisitPayment $p) => [
                'id' => $p->id,
                'time' => optional($p->paid_at)->format('h:i A'),
                'patient' => $p->visit?->patient?->name,
                'doctor' => $p->visit?->doctor?->name,
                'method' => $p->method,
                'kind' => $p->kind,
                'collector' => $p->collectedBy->name ?? 'System (online)',
                'amount' => (float) $p->amount,
            ])->all(),
        ];
    }
}
