<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Support\ResolvesAccessibleClinics;
use App\Models\Branch;
use App\Services\Clinic\DailyClosingReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daily Closing Report — v2 replacement for the Filament DailyClosingReport page.
 * Thin wrapper: delegates all aggregation to DailyClosingReportService::build().
 */
class DailyClosingController extends Controller
{
    use ResolvesAccessibleClinics;

    public function __construct(protected DailyClosingReportService $svc) {}

    public function index(Request $request): Response
    {
        if (! $request->user() || ! $request->user()->can('view_clinic_closing_reports')) {
            abort(403, 'Not authorized to view the daily closing report.');
        }

        $tz = config('app.timezone', 'Asia/Kuwait');
        $date = $request->input('date') ?: Carbon::now($tz)->toDateString();
        $branchIds = array_values(array_filter(array_map('intval', (array) $request->input('branch_ids', []))));

        return Inertia::render('Reports/DailyClosing', [
            'filters' => ['date' => $date, 'branch_ids' => $branchIds],
            // Streamed after the shell renders.
            'report' => Inertia::defer(fn () => $this->svc->build(Carbon::parse((string) $date, $tz), $branchIds)),
            'branches' => Branch::query()->when($this->accessibleBranchIds() !== null, fn ($q) => $q->whereIn('id', $this->accessibleBranchIds() ?: [0]))->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('#'.$b->id)])->all(),
        ]);
    }
}
