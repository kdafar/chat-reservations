<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Support\ResolvesAccessibleClinics;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Packages Report — what the package catalogue actually sells, and whether
 * selling it is worth the discount it costs.
 *
 * Packages are the clinic's main upsell, but nothing in v2 answered the two
 * questions that decide whether to keep running them: which packages carry the
 * revenue, and does a visit that includes a package really bill more than one
 * that doesn't. Both are here, alongside the offer pricing so the savings handed
 * to patients are visible next to the revenue they bought.
 */
class PackageReportsController extends Controller
{
    use ResolvesAccessibleClinics;

    public function index(Request $request): Response
    {
        $u = $request->user();
        if (! $u || ! $u->can('view_package_reports')) {
            abort(403, 'Not authorized to view package reports.');
        }

        $tz = config('app.timezone', 'Asia/Kuwait');
        $filters = [
            'from' => $request->input('from') ?: Carbon::now($tz)->subMonths(6)->startOfDay()->toDateString(),
            'to' => $request->input('to') ?: Carbon::now($tz)->toDateString(),
            'branch_id' => $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null,
        ];
        $from = Carbon::parse($filters['from'], $tz)->startOfDay();
        $to = Carbon::parse($filters['to'], $tz)->endOfDay();

        $branchIds = $this->accessibleBranchIds();

        $payload = fn () => $this->build($filters, $from, $to, $branchIds);
        $memo = null;
        $get = function (string $key) use (&$memo, $payload) {
            $memo ??= $payload();

            return $memo[$key];
        };

        return Inertia::render('Reports/PackageReports', [
            'filters' => $filters,
            'kpis' => Inertia::defer(fn () => $get('kpis'), 'packages'),
            'by_package' => Inertia::defer(fn () => $get('by_package'), 'packages'),
            'revenue_trend' => Inertia::defer(fn () => $get('revenue_trend'), 'packages'),
            'basket' => Inertia::defer(fn () => $get('basket'), 'packages'),
            'by_branch' => Inertia::defer(fn () => $get('by_branch'), 'packages'),
            'offers' => Inertia::defer(fn () => $get('offers'), 'packages'),
            'branches' => Branch::query()
                ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds ?: [0]))
                ->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('#'.$b->id)])->all(),
        ]);
    }

    protected function build(array $filters, Carbon $from, Carbon $to, ?array $branchIds): array
    {
        // Branch scope is applied on visits (not visit_packages) so package rows
        // and the whole-clinic revenue they are compared against are filtered
        // by exactly the same set of visits.
        $scopeVisits = function ($q) use ($filters, $branchIds) {
            if ($filters['branch_id']) {
                $q->where('visits.branch_id', $filters['branch_id']);
            } elseif ($branchIds !== null) {
                $q->whereIn('visits.branch_id', $branchIds ?: [0]);
            }

            return $q;
        };

        $visitsBase = fn () => $scopeVisits(DB::table('visits'))
            ->where('visits.status', 'completed')
            ->whereBetween('visits.computed_at', [$from, $to]);

        // visit_packages.line_total is gross; the visit's packages_price_total is
        // already net of discount_amount, so package revenue must subtract it too.
        $pkgBase = fn () => $scopeVisits(
            DB::table('visit_packages')->join('visits', 'visits.id', '=', 'visit_packages.visit_id')
        )
            ->where('visits.status', 'completed')
            ->whereBetween('visits.computed_at', [$from, $to]);

        // ---- Headline numbers ------------------------------------------------
        $totals = $pkgBase()->selectRaw('
            COALESCE(SUM(visit_packages.qty),0) as qty_sold,
            COALESCE(SUM(visit_packages.line_total - visit_packages.discount_amount),0) as revenue,
            COALESCE(SUM(visit_packages.discount_amount),0) as savings,
            COUNT(DISTINCT visit_packages.clinic_package_id) as package_count,
            COUNT(DISTINCT visits.patient_id) as patient_count,
            COUNT(DISTINCT visits.id) as visit_count
        ')->first();

        $clinicRevenue = (float) $visitsBase()->sum(DB::raw(
            'COALESCE(fees_total,0) + COALESCE(packages_price_total,0) + COALESCE(items_price_total,0) - COALESCE(discount_total,0)'
        ));

        $qtySold = (float) ($totals->qty_sold ?? 0);
        $pkgRevenue = (float) ($totals->revenue ?? 0);

        $kpis = [
            'qty_sold' => round($qtySold, 2),
            'revenue' => round($pkgRevenue, 3),
            'avg_value' => $qtySold > 0 ? round($pkgRevenue / $qtySold, 3) : 0.0,
            'revenue_share' => $clinicRevenue > 0 ? round(($pkgRevenue / $clinicRevenue) * 100, 1) : 0.0,
            'clinic_revenue' => round($clinicRevenue, 3),
            'package_count' => (int) ($totals->package_count ?? 0),
            'patient_count' => (int) ($totals->patient_count ?? 0),
            'visit_count' => (int) ($totals->visit_count ?? 0),
            'savings' => round((float) ($totals->savings ?? 0), 3),
        ];

        // ---- Sales by package -------------------------------------------------
        $byPackage = $pkgBase()
            ->join('clinic_packages', 'clinic_packages.id', '=', 'visit_packages.clinic_package_id')
            ->groupBy('clinic_packages.id', 'clinic_packages.name')
            ->selectRaw('clinic_packages.name as pkg_name,
                SUM(visit_packages.qty) as qty,
                SUM(visit_packages.line_total - visit_packages.discount_amount) as revenue,
                SUM(visit_packages.discount_amount) as discount_given,
                COUNT(DISTINCT visits.patient_id) as patients')
            ->orderByDesc('revenue')->get()
            ->map(function ($r) use ($pkgRevenue) {
                $qty = (float) $r->qty;
                $rev = (float) $r->revenue;

                return [
                    'package' => $this->name($r->pkg_name),
                    'qty' => round($qty, 2),
                    'revenue' => round($rev, 3),
                    'avg_price' => $qty > 0 ? round($rev / $qty, 3) : 0.0,
                    'discount_given' => round((float) $r->discount_given, 3),
                    'patients' => (int) $r->patients,
                    'share' => $pkgRevenue > 0 ? round(($rev / $pkgRevenue) * 100, 1) : 0.0,
                ];
            })->all();

        // ---- Revenue trend by month -------------------------------------------
        $trend = $pkgBase()
            ->groupBy(DB::raw("DATE_FORMAT(visits.computed_at, '%Y-%m')"))
            ->selectRaw("DATE_FORMAT(visits.computed_at, '%Y-%m') as ym,
                SUM(visit_packages.line_total - visit_packages.discount_amount) as revenue,
                SUM(visit_packages.qty) as qty")
            ->orderBy('ym')->get()
            ->map(fn ($r) => [
                'month' => Carbon::createFromFormat('Y-m-d', $r->ym.'-01')->format('M Y'),
                'revenue' => round((float) $r->revenue, 3),
                'qty' => round((float) $r->qty, 2),
            ])->all();

        // ---- Does a package actually lift the basket? --------------------------
        // Net visit value split by whether the visit carried a package at all.
        $basketRows = $visitsBase()
            ->selectRaw('CASE WHEN COALESCE(visits.packages_price_total,0) > 0 THEN 1 ELSE 0 END as has_package,
                COUNT(*) as visit_count,
                SUM(COALESCE(visits.fees_total,0) + COALESCE(visits.packages_price_total,0) + COALESCE(visits.items_price_total,0) - COALESCE(visits.discount_total,0)) as net_value,
                SUM(COALESCE(visits.profit_total,0)) as profit')
            ->groupBy(DB::raw('CASE WHEN COALESCE(visits.packages_price_total,0) > 0 THEN 1 ELSE 0 END'))
            ->get()->keyBy('has_package');

        $slice = function ($row) {
            $row ??= (object) [];
            $count = (int) ($row->visit_count ?? 0);
            $value = (float) ($row->net_value ?? 0);

            return [
                'visits' => $count,
                'total' => round($value, 3),
                'avg' => $count > 0 ? round($value / $count, 3) : 0.0,
                'avg_profit' => $count > 0 ? round((float) ($row->profit ?? 0) / $count, 3) : 0.0,
            ];
        };
        $withPkg = $slice($basketRows->get(1));
        $withoutPkg = $slice($basketRows->get(0));

        $basket = [
            'with' => $withPkg,
            'without' => $withoutPkg,
            'lift' => $withoutPkg['avg'] > 0 ? round((($withPkg['avg'] - $withoutPkg['avg']) / $withoutPkg['avg']) * 100, 1) : null,
            'lift_amount' => round($withPkg['avg'] - $withoutPkg['avg'], 3),
            'attach_rate' => ($withPkg['visits'] + $withoutPkg['visits']) > 0
                ? round(($withPkg['visits'] / ($withPkg['visits'] + $withoutPkg['visits'])) * 100, 1)
                : 0.0,
        ];

        // ---- Branch split -------------------------------------------------------
        $byBranch = $pkgBase()
            ->join('branches', 'branches.id', '=', 'visits.branch_id')
            ->groupBy('branches.id', 'branches.name')
            ->selectRaw('branches.name as branch_name,
                SUM(visit_packages.qty) as qty,
                SUM(visit_packages.line_total - visit_packages.discount_amount) as revenue,
                SUM(visit_packages.discount_amount) as discount_given,
                COUNT(DISTINCT visit_packages.clinic_package_id) as package_count')
            ->orderByDesc('revenue')->get()
            ->map(fn ($r) => [
                'branch' => $this->name($r->branch_name),
                'qty' => round((float) $r->qty, 2),
                'revenue' => round((float) $r->revenue, 3),
                'discount_given' => round((float) $r->discount_given, 3),
                'packages' => (int) $r->package_count,
                'share' => $pkgRevenue > 0 ? round(((float) $r->revenue / $pkgRevenue) * 100, 1) : 0.0,
            ])->all();

        // ---- Offer effectiveness -------------------------------------------------
        // The catalogue is global (partner_id / branch_id are NULL), so it is never
        // branch-filtered; only the sold quantities beside it are.
        $sold = collect($pkgBase()
            ->groupBy('visit_packages.clinic_package_id')
            ->selectRaw('visit_packages.clinic_package_id as pkg_id,
                SUM(visit_packages.qty) as qty,
                SUM(visit_packages.discount_amount) as savings')
            ->get())->keyBy('pkg_id');

        $today = Carbon::now(config('app.timezone', 'Asia/Kuwait'))->startOfDay();
        $offers = DB::table('clinic_packages')
            ->orderByDesc('is_active')->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name', 'default_price', 'discount_price', 'offer_starts_at', 'offer_ends_at', 'is_active'])
            ->map(function ($p) use ($sold, $today) {
                $list = (float) $p->default_price;
                $offer = $p->discount_price !== null ? (float) $p->discount_price : null;
                $starts = $p->offer_starts_at ? Carbon::parse($p->offer_starts_at)->startOfDay() : null;
                $ends = $p->offer_ends_at ? Carbon::parse($p->offer_ends_at)->endOfDay() : null;
                $inWindow = (! $starts || $starts->lte($today)) && (! $ends || $ends->gte($today));
                $row = $sold->get($p->id);

                return [
                    'package' => $this->name($p->name),
                    'list_price' => round($list, 3),
                    'offer_price' => $offer !== null ? round($offer, 3) : null,
                    'unit_saving' => $offer !== null ? round($list - $offer, 3) : null,
                    'saving_pct' => ($offer !== null && $list > 0) ? round((($list - $offer) / $list) * 100, 1) : null,
                    'live' => (bool) $p->is_active && $offer !== null && $inWindow,
                    'is_active' => (bool) $p->is_active,
                    'starts_at' => $p->offer_starts_at ? Carbon::parse($p->offer_starts_at)->toDateString() : null,
                    'ends_at' => $p->offer_ends_at ? Carbon::parse($p->offer_ends_at)->toDateString() : null,
                    'qty_sold' => $row ? round((float) $row->qty, 2) : 0.0,
                    'savings_passed' => $row ? round((float) $row->savings, 3) : 0.0,
                ];
            })->all();

        return [
            'kpis' => $kpis,
            'by_package' => $byPackage,
            'revenue_trend' => $trend,
            'basket' => $basket,
            'by_branch' => $byBranch,
            'offers' => $offers,
        ];
    }

    /** Package and branch names are stored as {en,ar} JSON blobs. */
    protected function name($value): string
    {
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $d = json_decode($value, true);
            if (is_array($d)) {
                return $d[app()->getLocale()] ?? $d['en'] ?? (array_values($d)[0] ?? '—');
            }
        }

        return (string) ($value ?? '—');
    }
}
