<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorCompensationLedger;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Clinic Reports — v2 replacement for the Filament ClinicReports page + its widgets.
 * The Filament widgets force "today"; the v2 page honours the page's own from/to/
 * branch/doctor filters (more useful) over the same visit/ledger columns.
 */
class ClinicReportsController extends Controller
{
    public function index(Request $request): Response
    {
        if (! $request->user() || ! $request->user()->can('view_clinic_reports')) {
            abort(403, 'Not authorized to view clinic reports.');
        }

        $tz = config('app.timezone', 'Asia/Kuwait');
        $filters = [
            'from' => $request->input('from') ?: Carbon::now($tz)->startOfMonth()->toDateString(),
            'to' => $request->input('to') ?: Carbon::now($tz)->toDateString(),
            'branch_id' => $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null,
            'doctor_id' => $request->input('doctor_id', '') !== '' ? (int) $request->input('doctor_id') : null,
        ];
        $from = Carbon::parse($filters['from'], $tz)->startOfDay();
        $to = Carbon::parse($filters['to'], $tz)->endOfDay();

        // Heavy aggregation built lazily + memoised, then streamed via deferred
        // props (one follow-up request via the shared 'reports' group) so the
        // page shell + filters render instantly.
        $payload = function () use ($filters, $from, $to) {
            $visits = Visit::query()
                ->whereBetween('computed_at', [$from, $to])
                ->when($filters['branch_id'], fn ($q) => $q->where('branch_id', $filters['branch_id']))
                ->when($filters['doctor_id'], fn ($q) => $q->where('doctor_id', $filters['doctor_id']));

            $overview = (clone $visits)->selectRaw('
                COUNT(*) as visits_count,
                SUM(COALESCE(fees_total,0)) as fees_total,
                SUM(COALESCE(items_cost_total,0)) as items_cost_total,
                SUM(COALESCE(profit_total,0)) as profit_total
            ')->first();

            $ledger = DoctorCompensationLedger::query()
                ->whereBetween('created_at', [$from, $to])
                ->when($filters['branch_id'], fn ($q) => $q->where('branch_id', $filters['branch_id']))
                ->when($filters['doctor_id'], fn ($q) => $q->where('doctor_id', $filters['doctor_id']));
            $doctorCut = (float) (clone $ledger)->sum('doctor_cut_amount');

            $trend = (clone $visits)->selectRaw('DATE(computed_at) as date, SUM(COALESCE(profit_total,0)) as profit, SUM(COALESCE(fees_total,0)) as fees')
                ->groupBy('date')->orderBy('date')->get()
                ->map(fn ($r) => ['date' => Carbon::parse($r->date)->format('d M'), 'profit' => (float) $r->profit, 'fees' => (float) $r->fees])->all();

            $topDoctors = (clone $ledger)->selectRaw('doctor_id, SUM(doctor_cut_amount) as cut_total, COUNT(*) as visits_count')
                ->groupBy('doctor_id')->orderByDesc('cut_total')->limit(10)->get()
                ->map(fn ($r) => [
                    'doctor' => Doctor::find($r->doctor_id)?->name ?? ('#'.$r->doctor_id),
                    'visits_count' => (int) $r->visits_count,
                    'cut_total' => (float) $r->cut_total,
                ])->all();

            $topItems = DB::table('visit_items')
                ->join('visits', 'visits.id', '=', 'visit_items.visit_id')
                ->join('clinic_items', 'clinic_items.id', '=', 'visit_items.clinic_item_id')
                ->whereBetween('visits.computed_at', [$from, $to])
                ->when($filters['branch_id'], fn ($q) => $q->where('visits.branch_id', $filters['branch_id']))
                ->when($filters['doctor_id'], fn ($q) => $q->where('visits.doctor_id', $filters['doctor_id']))
                ->groupBy('visit_items.clinic_item_id', 'clinic_items.name', 'clinic_items.type')
                ->selectRaw('clinic_items.name as name, clinic_items.type as type,
                    SUM(visit_items.qty) as qty_total,
                    SUM(visit_items.line_price_total) as revenue_total,
                    SUM(visit_items.line_cost_total) as cost_total,
                    SUM(visit_items.line_price_total - visit_items.line_cost_total) as profit_total')
                ->orderByDesc('profit_total')->limit(10)->get()
                ->map(function ($r) {
                    $name = $r->name;
                    $decoded = is_string($name) && str_starts_with(trim($name), '{') ? json_decode($name, true) : null;
                    if (is_array($decoded)) $name = $decoded[app()->getLocale()] ?? $decoded['en'] ?? array_values($decoded)[0] ?? 'Unknown';
                    return [
                        'name' => (string) $name,
                        'type' => (string) $r->type,
                        'qty_total' => (float) $r->qty_total,
                        'revenue_total' => (float) $r->revenue_total,
                        'cost_total' => (float) $r->cost_total,
                        'profit_total' => (float) $r->profit_total,
                    ];
                })->all();

            return [
                'overview' => [
                    'visits_count' => (int) ($overview->visits_count ?? 0),
                    'fees_total' => (float) ($overview->fees_total ?? 0),
                    'items_cost_total' => (float) ($overview->items_cost_total ?? 0),
                    'profit_total' => (float) ($overview->profit_total ?? 0),
                    'doctor_cut' => $doctorCut,
                ],
                'trend' => $trend,
                'top_doctors' => $topDoctors,
                'top_items' => $topItems,
            ];
        };
        $memo = null;
        $get = function (string $key) use (&$memo, $payload) {
            if ($memo === null) $memo = $payload();
            return $memo[$key];
        };

        return Inertia::render('Reports/ClinicReports', [
            'filters' => $filters,
            'overview' => Inertia::defer(fn () => $get('overview'), 'reports'),
            'trend' => Inertia::defer(fn () => $get('trend'), 'reports'),
            'top_doctors' => Inertia::defer(fn () => $get('top_doctors'), 'reports'),
            'top_items' => Inertia::defer(fn () => $get('top_items'), 'reports'),
            'branches' => Branch::query()->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('#'.$b->id)])->all(),
            'doctors' => Doctor::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name ?? ('#'.$d->id)])->all(),
        ]);
    }
}
