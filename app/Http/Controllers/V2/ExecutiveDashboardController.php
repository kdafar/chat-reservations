<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Reporting\ExecutiveDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Executive Dashboard — v2 replacement for the Filament ExecutiveDashboard page.
 * Aggregations live in ExecutiveDashboardService (extracted from the page) so the
 * numbers match exactly.
 */
class ExecutiveDashboardController extends Controller
{
    public function __construct(protected ExecutiveDashboardService $svc) {}

    public function index(Request $request): Response
    {
        if (! $request->user() || ! $request->user()->can('view_executive-dashboard')) {
            abort(403, 'Not authorized to view the executive dashboard.');
        }

        $filters = [
            'period' => $request->input('period', 'month'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'branch_id' => $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null,
        ];

        return Inertia::render('Reports/ExecutiveDashboard', [
            'filters' => $filters,
            // Streamed after the shell renders (heavy aggregation).
            'data' => Inertia::defer(fn () => $this->svc->build(
                $filters['period'], $filters['start_date'], $filters['end_date'], $filters['branch_id']
            )),
            'periods' => ['today', 'week', 'month', 'quarter', 'year', 'custom'],
            'branches' => Branch::query()->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('#'.$b->id)])->all(),
        ]);
    }
}
