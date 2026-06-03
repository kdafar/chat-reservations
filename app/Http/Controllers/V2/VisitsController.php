<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Visit;
use App\Services\Clinic\VisitCostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visits list — v2 replacement for the Filament VisitResource table. Rows open the
 * existing v2 visit console (v2.visits.show). The only mutation here is "Recompute
 * financials" (completed visits, when the financials flag is on) via VisitCostingService.
 * Branch + doctor scoping is handled by the model global scope.
 */
class VisitsController extends Controller
{
    private const STATUSES = ['created', 'checked_in', 'awaiting_doctor', 'awaiting_stock', 'in_progress', 'awaiting_payment', 'completed', 'cancelled', 'no_show'];

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_visits')) {
            abort(403, 'Not authorized to view visits.');
        }
    }

    protected function financialsEnabled(): bool
    {
        return (bool) config('clinic.visit_financials_enabled', false);
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'doctor_id' => $request->input('doctor_id', '') !== '' ? (int) $request->input('doctor_id') : null,
            'branch_id' => $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null,
            'status' => $request->input('status', 'all'),
            'accepted' => $request->input('accepted', 'all'),
            'from' => $request->input('from'),
            'until' => $request->input('until'),
        ];

        $query = Visit::query()->with(['patient:id,name,phone', 'doctor:id,name', 'branch:id,name', 'room:id,name']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->whereHas('patient', fn ($p) => $p->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"));
        }
        if ($filters['doctor_id']) $query->where('doctor_id', $filters['doctor_id']);
        if ($filters['branch_id']) $query->where('branch_id', $filters['branch_id']);
        if (in_array($filters['status'], self::STATUSES, true)) $query->where('status', $filters['status']);
        if ($filters['accepted'] === 'yes') $query->whereNotNull('accepted_at');
        elseif ($filters['accepted'] === 'no') $query->whereNull('accepted_at');
        if ($filters['from']) $query->whereDate('checked_in_at', '>=', $filters['from']);
        if ($filters['until']) $query->whereDate('checked_in_at', '<=', $filters['until']);

        $page = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $page->getCollection()->transform(function (Visit $v) {
            $v->setAttribute('branch_name', $v->branch?->localized_name);
            return $v;
        });

        return Inertia::render('Visits/Index', [
            'filters' => $filters,
            'page' => $page,
            'doctors' => Doctor::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name ?? ('#'.$d->id)])->all(),
            'branches' => Branch::forUser($request->user())->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('#'.$b->id)])->all(),
            'statuses' => self::STATUSES,
            'financials_enabled' => $this->financialsEnabled(),
            'counts' => [
                'total' => Visit::query()->count(),
                'completed' => Visit::query()->where('status', 'completed')->count(),
            ],
            'can_recompute' => $this->financialsEnabled() && (bool) $request->user()->can('update_visits'),
        ]);
    }

    /** Stream selected visits as CSV (bulk export). Not an Inertia response. */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));
        $query = Visit::query()->with(['patient:id,name,phone', 'doctor:id,name', 'branch:id,name'])
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))->orderByDesc('id');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query,
                ['ID', 'Checked in', 'Patient', 'Phone', 'Doctor', 'Branch', 'Status', 'Fees'],
                fn ($v) => [
                    $v->id,
                    optional($v->checked_in_at)->format('Y-m-d H:i'),
                    $v->patient?->name, $v->patient?->phone,
                    $v->doctor?->name, $v->branch?->localized_name,
                    $v->status, number_format((float) $v->fees_total, 3, '.', ''),
                ],
                'Visits',
                app()->getLocale() === 'ar',
            ),
            'visits-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    public function recompute(Request $request, Visit $visit): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->financialsEnabled() || ! $request->user()->can('update_visits')) abort(403);
        if ($visit->status !== 'completed') {
            return back()->with('flash', ['type' => 'error', 'message' => 'Only completed visits can be recomputed.']);
        }
        app(VisitCostingService::class)->compute($visit, (int) $request->user()->id);
        return back()->with('flash', ['type' => 'success', 'message' => 'Financial snapshot recomputed.']);
    }
}
