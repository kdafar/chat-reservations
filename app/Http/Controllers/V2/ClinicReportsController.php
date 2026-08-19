<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Support\ResolvesAccessibleClinics;
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
    use ResolvesAccessibleClinics;

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

        // A doctor carried over from a different branch would silently produce
        // an all-zero report, so drop the selection when it doesn't belong to
        // the chosen branch.
        if ($filters['branch_id'] && $filters['doctor_id']
            && ! Doctor::query()->whereKey($filters['doctor_id'])->where('branch_id', $filters['branch_id'])->exists()) {
            $filters['doctor_id'] = null;
        }

        $from = Carbon::parse($filters['from'], $tz)->startOfDay();
        $to = Carbon::parse($filters['to'], $tz)->endOfDay();

        // Previous window of equal length, immediately before, for comparisons.
        $spanDays = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $prevTo = $from->copy()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->subDays($spanDays - 1)->startOfDay();

        // Heavy aggregation built lazily + memoised, then streamed via deferred
        // props (one follow-up request via the shared 'reports' group) so the
        // page shell + filters render instantly.
        $payload = function () use ($filters, $from, $to, $prevFrom, $prevTo) {
            $visits = Visit::query()
                ->whereBetween('computed_at', [$from, $to])
                ->when($filters['branch_id'], fn ($q) => $q->where('branch_id', $filters['branch_id']))
                ->when($filters['doctor_id'], fn ($q) => $q->where('doctor_id', $filters['doctor_id']));

            // Window aggregate, reused for current + previous period.
            $aggregate = function (Carbon $wFrom, Carbon $wTo) use ($filters) {
                $row = Visit::query()->whereBetween('computed_at', [$wFrom, $wTo])
                    ->when($filters['branch_id'], fn ($q) => $q->where('branch_id', $filters['branch_id']))
                    ->when($filters['doctor_id'], fn ($q) => $q->where('doctor_id', $filters['doctor_id']))
                    ->selectRaw('
                        COUNT(*) as visits_count,
                        SUM(COALESCE(fees_total,0)) as fees_total,
                        SUM(COALESCE(packages_price_total,0)) as packages_total,
                        SUM(COALESCE(items_price_total,0)) as items_price_total,
                        SUM(COALESCE(items_cost_total,0)) as items_cost_total,
                        SUM(COALESCE(discount_total,0)) as discount_total,
                        SUM(COALESCE(profit_total,0)) as profit_total
                    ')->first();
                $cut = (float) DoctorCompensationLedger::query()
                    ->whereBetween('created_at', [$wFrom, $wTo])
                    ->when($filters['branch_id'], fn ($q) => $q->where('branch_id', $filters['branch_id']))
                    ->when($filters['doctor_id'], fn ($q) => $q->where('doctor_id', $filters['doctor_id']))
                    ->sum('doctor_cut_amount');
                $revenue = (float) $row->fees_total + (float) $row->packages_total + (float) $row->items_price_total - (float) $row->discount_total;
                return [
                    'visits_count' => (int) $row->visits_count,
                    'fees_total' => (float) $row->fees_total,
                    'items_cost_total' => (float) $row->items_cost_total,
                    'discount_total' => (float) $row->discount_total,
                    'profit_total' => (float) $row->profit_total,
                    'doctor_cut' => $cut,
                    'revenue' => $revenue,
                ];
            };

            $cur = $aggregate($from, $to);
            $prev = $aggregate($prevFrom, $prevTo);
            $pct = fn ($c, $p) => $p > 0.0001 ? round((($c - $p) / $p) * 100, 1) : null;

            $overview = (object) [
                'visits_count' => $cur['visits_count'],
                'fees_total' => $cur['fees_total'],
                'items_cost_total' => $cur['items_cost_total'],
                'profit_total' => $cur['profit_total'],
            ];
            $doctorCut = $cur['doctor_cut'];

            $comparison = [
                'prev_label' => $prevFrom->format('d M').' – '.$prevTo->format('d M'),
                'visits' => $pct($cur['visits_count'], $prev['visits_count']),
                'revenue' => $pct($cur['revenue'], $prev['revenue']),
                'fees' => $pct($cur['fees_total'], $prev['fees_total']),
                'profit' => $pct($cur['profit_total'], $prev['profit_total']),
                'doctor_cut' => $pct($cur['doctor_cut'], $prev['doctor_cut']),
                'discount' => $pct($cur['discount_total'], $prev['discount_total']),
            ];

            $revenue = $cur['revenue'];
            $extra = [
                'revenue' => $revenue,
                'discount_total' => $cur['discount_total'],
                'avg_visit_value' => $cur['visits_count'] > 0 ? $revenue / $cur['visits_count'] : 0,
                'discount_pct' => ($revenue + $cur['discount_total']) > 0 ? round(($cur['discount_total'] / ($revenue + $cur['discount_total'])) * 100, 1) : 0,
            ];

            // Payment mix (how revenue was actually collected).
            $paymentMix = DB::table('visit_payments')
                ->join('visits', 'visits.id', '=', 'visit_payments.visit_id')
                ->whereBetween('visits.computed_at', [$from, $to])
                ->where('visit_payments.status', 'paid')
                ->when($filters['branch_id'], fn ($q) => $q->where('visits.branch_id', $filters['branch_id']))
                ->when($filters['doctor_id'], fn ($q) => $q->where('visits.doctor_id', $filters['doctor_id']))
                ->groupBy('visit_payments.method')
                ->selectRaw("COALESCE(visit_payments.method,'unknown') as method, COUNT(*) as c, COALESCE(SUM(visit_payments.amount),0) as amount")
                ->get()
                ->map(fn ($r) => ['method' => (string) $r->method, 'count' => (int) $r->c, 'amount' => (float) $r->amount])
                ->sortByDesc('amount')->values()->all();

            // Outstanding (billed but not yet collected) for visits in the window.
            $collected = (float) DB::table('visit_payments')
                ->join('visits', 'visits.id', '=', 'visit_payments.visit_id')
                ->whereBetween('visits.computed_at', [$from, $to])
                ->where('visit_payments.status', 'paid')
                ->when($filters['branch_id'], fn ($q) => $q->where('visits.branch_id', $filters['branch_id']))
                ->when($filters['doctor_id'], fn ($q) => $q->where('visits.doctor_id', $filters['doctor_id']))
                ->sum('visit_payments.amount');
            $unpaidCount = (clone $visits)
                ->whereRaw("(COALESCE(fees_total,0)+COALESCE(packages_price_total,0)+COALESCE(items_price_total,0)-COALESCE(discount_total,0)) - COALESCE((SELECT SUM(amount) FROM visit_payments WHERE visit_payments.visit_id = visits.id AND visit_payments.status = 'paid'),0) > 0.005")
                ->count();
            $outstanding = ['total' => max(0, round($revenue - $collected, 3)), 'collected' => round($collected, 3), 'unpaid_count' => (int) $unpaidCount];

            // New vs returning patients (new = no visit before this window, anywhere).
            $patientIds = (clone $visits)->whereNotNull('patient_id')->distinct()->pluck('patient_id')->all();
            $totalPatients = count($patientIds);
            $returningPatients = $totalPatients > 0
                ? (int) Visit::query()->whereIn('patient_id', $patientIds)->where('computed_at', '<', $from)->distinct()->count('patient_id')
                : 0;
            $patients = [
                'total' => $totalPatients,
                'new' => max(0, $totalPatients - $returningPatients),
                'returning' => $returningPatients,
            ];

            // Busiest weekday (1=Sun … 7=Sat per MySQL DAYOFWEEK).
            $wdRaw = (clone $visits)->selectRaw('DAYOFWEEK(computed_at) as wd, COUNT(*) as c')->groupBy('wd')->pluck('c', 'wd')->all();
            $byWeekday = [];
            for ($i = 1; $i <= 7; $i++) { $byWeekday[] = (int) ($wdRaw[$i] ?? 0); }

            // Peak hours (clinic day 8:00–21:00).
            $hrRaw = (clone $visits)->selectRaw('HOUR(computed_at) as h, COUNT(*) as c')->groupBy('h')->pluck('c', 'h')->all();
            $byHour = [];
            for ($h = 8; $h <= 21; $h++) { $byHour[] = ['hour' => $h, 'count' => (int) ($hrRaw[$h] ?? 0)]; }

            // Revenue composition: consultations (fees) vs products (items) vs
            // packages, less discounts — where the money actually comes from.
            $bd = (clone $visits)->selectRaw('
                SUM(COALESCE(fees_total,0)) as fees,
                SUM(COALESCE(items_price_total,0)) as items,
                SUM(COALESCE(packages_price_total,0)) as packages,
                SUM(COALESCE(discount_total,0)) as discount')->first();
            $revenueBreakdown = [
                'fees' => (float) ($bd->fees ?? 0),
                'items' => (float) ($bd->items ?? 0),
                'packages' => (float) ($bd->packages ?? 0),
                'discount' => (float) ($bd->discount ?? 0),
            ];

            $ledger = DoctorCompensationLedger::query()
                ->whereBetween('created_at', [$from, $to])
                ->when($filters['branch_id'], fn ($q) => $q->where('branch_id', $filters['branch_id']))
                ->when($filters['doctor_id'], fn ($q) => $q->where('doctor_id', $filters['doctor_id']));

            $trend = (clone $visits)->selectRaw('DATE(computed_at) as date,
                    SUM(COALESCE(profit_total,0)) as profit,
                    SUM(COALESCE(fees_total,0)) as fees,
                    SUM(COALESCE(fees_total,0)+COALESCE(packages_price_total,0)+COALESCE(items_price_total,0)-COALESCE(discount_total,0)) as revenue')
                ->groupBy('date')->orderBy('date')->get()
                ->map(fn ($r) => ['date' => Carbon::parse($r->date)->format('d M'), 'profit' => (float) $r->profit, 'fees' => (float) $r->fees, 'revenue' => (float) $r->revenue])->all();

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
                    'revenue' => $extra['revenue'],
                    'discount_total' => $extra['discount_total'],
                    'avg_visit_value' => $extra['avg_visit_value'],
                    'discount_pct' => $extra['discount_pct'],
                ],
                'comparison' => $comparison,
                'payment_mix' => $paymentMix,
                'outstanding' => $outstanding,
                'patients' => $patients,
                'by_weekday' => $byWeekday,
                'by_hour' => $byHour,
                'revenue_breakdown' => $revenueBreakdown,
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
            'comparison' => Inertia::defer(fn () => $get('comparison'), 'reports'),
            'payment_mix' => Inertia::defer(fn () => $get('payment_mix'), 'reports'),
            'outstanding' => Inertia::defer(fn () => $get('outstanding'), 'reports'),
            'patients' => Inertia::defer(fn () => $get('patients'), 'reports'),
            'by_weekday' => Inertia::defer(fn () => $get('by_weekday'), 'reports'),
            'by_hour' => Inertia::defer(fn () => $get('by_hour'), 'reports'),
            'revenue_breakdown' => Inertia::defer(fn () => $get('revenue_breakdown'), 'reports'),
            'trend' => Inertia::defer(fn () => $get('trend'), 'reports'),
            'top_doctors' => Inertia::defer(fn () => $get('top_doctors'), 'reports'),
            'top_items' => Inertia::defer(fn () => $get('top_items'), 'reports'),
            'branches' => Branch::query()->when($this->accessibleBranchIds() !== null, fn ($q) => $q->whereIn('id', $this->accessibleBranchIds() ?: [0]))->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('#'.$b->id)])->all(),
            // Only the chosen branch's doctors — the dropdown sits next to the
            // branch picker and offering the whole group was misleading.
            'doctors' => Doctor::query()->atBranch($filters['branch_id'])->orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name ?? ('#'.$d->id)])->all(),
        ]);
    }
}
