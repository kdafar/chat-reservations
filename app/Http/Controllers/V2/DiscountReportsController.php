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
 * Discounts & Promotions Report — margin control.
 *
 * Every discount is granted one visit at a time by whoever is at the desk, so
 * nobody sees the total until it has already been given away. This aggregates
 * it: how much was discounted, what share of billing that is, and — the part
 * that matters — which branches and doctors sit above the clinic average, plus
 * how much of the bill a discounted visit typically loses.
 *
 * Discount here means the visit-level discount_total. Package offer pricing is
 * not a visit discount (it is baked into packages_price_total) and lives in the
 * Packages report instead, so nothing is counted twice.
 */
class DiscountReportsController extends Controller
{
    use ResolvesAccessibleClinics;

    public function index(Request $request): Response
    {
        $u = $request->user();
        if (! $u || ! $u->can('view_discount_reports')) {
            abort(403, 'Not authorized to view discount reports.');
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

        return Inertia::render('Reports/DiscountReports', [
            'filters' => $filters,
            'kpis' => Inertia::defer(fn () => $get('kpis'), 'discounts'),
            'trend' => Inertia::defer(fn () => $get('trend'), 'discounts'),
            'by_branch' => Inertia::defer(fn () => $get('by_branch'), 'discounts'),
            'by_doctor' => Inertia::defer(fn () => $get('by_doctor'), 'discounts'),
            'bands' => Inertia::defer(fn () => $get('bands'), 'discounts'),
            'coupons' => Inertia::defer(fn () => $get('coupons'), 'discounts'),
            'promotions' => Inertia::defer(fn () => $get('promotions'), 'discounts'),
            'margin' => Inertia::defer(fn () => $get('margin'), 'discounts'),
            'branches' => Branch::query()
                ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds ?: [0]))
                ->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('#'.$b->id)])->all(),
        ]);
    }

    protected function build(array $filters, Carbon $from, Carbon $to, ?array $branchIds): array
    {
        $scopeVisits = function ($q) use ($filters, $branchIds) {
            if ($filters['branch_id']) {
                $q->where('visits.branch_id', $filters['branch_id']);
            } elseif ($branchIds !== null) {
                $q->whereIn('visits.branch_id', $branchIds ?: [0]);
            }

            return $q;
        };

        $visits = fn () => $scopeVisits(DB::table('visits'))
            ->where('visits.status', 'completed')
            ->whereBetween('visits.computed_at', [$from, $to]);

        $grossExpr = 'COALESCE(visits.fees_total,0) + COALESCE(visits.packages_price_total,0) + COALESCE(visits.items_price_total,0)';

        // ---- Headline numbers ------------------------------------------------
        $totals = $visits()->selectRaw("
            COUNT(*) as visit_count,
            COALESCE(SUM($grossExpr),0) as gross,
            COALESCE(SUM(visits.discount_total),0) as discount,
            COALESCE(SUM(visits.items_cost_total),0) as cost,
            COALESCE(SUM(visits.profit_total),0) as profit,
            SUM(CASE WHEN COALESCE(visits.discount_total,0) > 0 THEN 1 ELSE 0 END) as discounted_count,
            MAX(COALESCE(visits.discount_total,0)) as largest
        ")->first();

        $gross = (float) ($totals->gross ?? 0);
        $discount = (float) ($totals->discount ?? 0);
        $discountedCount = (int) ($totals->discounted_count ?? 0);
        $visitCount = (int) ($totals->visit_count ?? 0);

        $redemptions = DB::table('coupon_redemptions')
            ->whereBetween('coupon_redemptions.created_at', [$from, $to])
            ->selectRaw('COUNT(*) as redemption_count, COALESCE(SUM(discount_applied),0) as amount')
            ->first();

        $promoScope = fn ($q) => $filters['branch_id']
            ? $q->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $filters['branch_id']))
            : ($branchIds !== null
                ? $q->where(fn ($w) => $w->whereNull('branch_id')->orWhereIn('branch_id', $branchIds ?: [0]))
                : $q);

        $today = Carbon::now(config('app.timezone', 'Asia/Kuwait'))->startOfDay();

        $kpis = [
            'discount_total' => round($discount, 3),
            'gross' => round($gross, 3),
            'net' => round($gross - $discount, 3),
            'discount_pct' => $gross > 0 ? round(($discount / $gross) * 100, 2) : 0.0,
            'visit_count' => $visitCount,
            'discounted_count' => $discountedCount,
            'discounted_share' => $visitCount > 0 ? round(($discountedCount / $visitCount) * 100, 1) : 0.0,
            // Averaged over discounted visits only — averaging over every visit
            // would flatten the number into meaninglessness.
            'avg_discount' => $discountedCount > 0 ? round($discount / $discountedCount, 3) : 0.0,
            'largest_discount' => round((float) ($totals->largest ?? 0), 3),
            'coupon_redemptions' => (int) ($redemptions->redemption_count ?? 0),
            'coupon_discount' => round((float) ($redemptions->amount ?? 0), 3),
            'promotion_count' => (int) $promoScope(DB::table('clinic_promotions')->where('is_active', true))->count(),
        ];

        // ---- Trend by month ----------------------------------------------------
        $trend = $visits()
            ->groupBy(DB::raw("DATE_FORMAT(visits.computed_at, '%Y-%m')"))
            ->selectRaw("DATE_FORMAT(visits.computed_at, '%Y-%m') as ym,
                COALESCE(SUM($grossExpr),0) as gross,
                COALESCE(SUM(visits.discount_total),0) as discount,
                SUM(CASE WHEN COALESCE(visits.discount_total,0) > 0 THEN 1 ELSE 0 END) as discounted_count")
            ->orderBy('ym')->get()
            ->map(fn ($r) => [
                'month' => Carbon::createFromFormat('Y-m-d', $r->ym.'-01')->format('M Y'),
                'gross' => round((float) $r->gross, 3),
                'discount' => round((float) $r->discount, 3),
                'pct' => (float) $r->gross > 0 ? round(((float) $r->discount / (float) $r->gross) * 100, 2) : 0.0,
                'visits' => (int) $r->discounted_count,
            ])->all();

        $clinicPct = $gross > 0 ? ($discount / $gross) * 100 : 0.0;

        // ---- Who discounts ------------------------------------------------------
        $byBranch = $visits()
            ->join('branches', 'branches.id', '=', 'visits.branch_id')
            ->groupBy('branches.id', 'branches.name')
            ->selectRaw("branches.name as branch_name,
                COUNT(*) as visit_count,
                COALESCE(SUM($grossExpr),0) as gross,
                COALESCE(SUM(visits.discount_total),0) as discount,
                SUM(CASE WHEN COALESCE(visits.discount_total,0) > 0 THEN 1 ELSE 0 END) as discounted_count")
            ->orderByDesc('discount')->get()
            ->map(fn ($r) => $this->rateRow($this->name($r->branch_name), $r, $clinicPct))->all();

        // doctors is soft-deleted; a raw join keeps a departed doctor's discounts
        // visible instead of silently dropping the rows.
        $byDoctor = $visits()
            ->leftJoin('doctors', 'doctors.id', '=', 'visits.doctor_id')
            ->groupBy('doctors.id', 'doctors.name')
            ->selectRaw("COALESCE(doctors.name,'—') as doctor_name,
                COUNT(*) as visit_count,
                COALESCE(SUM($grossExpr),0) as gross,
                COALESCE(SUM(visits.discount_total),0) as discount,
                SUM(CASE WHEN COALESCE(visits.discount_total,0) > 0 THEN 1 ELSE 0 END) as discounted_count")
            ->havingRaw('SUM(COALESCE(visits.discount_total,0)) > 0')
            ->orderByDesc('discount')->limit(25)->get()
            ->map(fn ($r) => $this->rateRow($this->name($r->doctor_name), $r, $clinicPct))->all();

        // ---- How deep the discounts go -------------------------------------------
        // Bands are on discount ÷ that visit's own bill, not the absolute amount:
        // 20 KWD off a 500 KWD bill is a different decision from 20 off 60.
        $bandRaw = $visits()
            ->where('visits.discount_total', '>', 0)
            ->selectRaw("CASE
                    WHEN ($grossExpr) <= 0 THEN 4
                    WHEN visits.discount_total / ($grossExpr) < 0.05 THEN 0
                    WHEN visits.discount_total / ($grossExpr) < 0.10 THEN 1
                    WHEN visits.discount_total / ($grossExpr) < 0.20 THEN 2
                    ELSE 3 END as band_index,
                COUNT(*) as visit_count,
                COALESCE(SUM(visits.discount_total),0) as discount")
            ->groupBy(DB::raw("CASE
                    WHEN ($grossExpr) <= 0 THEN 4
                    WHEN visits.discount_total / ($grossExpr) < 0.05 THEN 0
                    WHEN visits.discount_total / ($grossExpr) < 0.10 THEN 1
                    WHEN visits.discount_total / ($grossExpr) < 0.20 THEN 2
                    ELSE 3 END"))
            ->get()->keyBy('band_index');

        $bandLabels = [
            0 => ['en' => '0–5%', 'ar' => '٠–٥٪'],
            1 => ['en' => '5–10%', 'ar' => '٥–١٠٪'],
            2 => ['en' => '10–20%', 'ar' => '١٠–٢٠٪'],
            3 => ['en' => '20%+', 'ar' => '٢٠٪+'],
            4 => ['en' => 'No billed amount', 'ar' => 'بدون فاتورة'],
        ];
        $bands = [];
        foreach ($bandLabels as $i => $label) {
            $row = $bandRaw->get($i);
            $count = (int) ($row->visit_count ?? 0);
            if ($i === 4 && $count === 0) {
                continue; // only surface the degenerate bucket when it has rows
            }
            $bands[] = [
                'band' => $label['en'],
                'band_ar' => $label['ar'],
                'visits' => $count,
                'discount' => round((float) ($row->discount ?? 0), 3),
                'share' => $discountedCount > 0 ? round(($count / $discountedCount) * 100, 1) : 0.0,
            ];
        }

        // ---- Coupons -------------------------------------------------------------
        // Redemptions are shown both for the window and lifetime: a coupon that
        // ran before the window would otherwise read as unused.
        $windowUse = DB::table('coupon_redemptions')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('coupon_id')
            ->selectRaw('coupon_id, COUNT(*) as use_count, COALESCE(SUM(discount_applied),0) as amount')
            ->get()->keyBy('coupon_id');
        $lifetimeUse = DB::table('coupon_redemptions')
            ->groupBy('coupon_id')
            ->selectRaw('coupon_id, COUNT(*) as use_count, COALESCE(SUM(discount_applied),0) as amount')
            ->get()->keyBy('coupon_id');

        $coupons = $promoScope(DB::table('clinic_coupons'))
            ->orderByDesc('is_active')->orderBy('code')
            ->get(['id', 'code', 'name', 'discount_type', 'discount_value', 'branch_id', 'starts_at', 'ends_at', 'max_uses', 'uses_count', 'is_active'])
            ->map(function ($c) use ($windowUse, $lifetimeUse, $today) {
                $w = $windowUse->get($c->id);
                $l = $lifetimeUse->get($c->id);
                $starts = $c->starts_at ? Carbon::parse($c->starts_at)->startOfDay() : null;
                $ends = $c->ends_at ? Carbon::parse($c->ends_at)->endOfDay() : null;

                return [
                    'code' => (string) $c->code,
                    'name' => $this->name($c->name),
                    'discount_type' => (string) $c->discount_type,
                    'discount_value' => round((float) $c->discount_value, 3),
                    'redemptions' => (int) ($w->use_count ?? 0),
                    'discount' => round((float) ($w->amount ?? 0), 3),
                    'lifetime_redemptions' => (int) ($l->use_count ?? $c->uses_count ?? 0),
                    'lifetime_discount' => round((float) ($l->amount ?? 0), 3),
                    'max_uses' => $c->max_uses !== null ? (int) $c->max_uses : null,
                    'starts_at' => $starts?->toDateString(),
                    'ends_at' => $ends?->toDateString(),
                    'live' => (bool) $c->is_active && (! $starts || $starts->lte($today)) && (! $ends || $ends->gte($today)),
                ];
            })->all();

        // ---- Promotions -----------------------------------------------------------
        $promotions = $promoScope(DB::table('clinic_promotions'))
            ->leftJoin('branches', 'branches.id', '=', 'clinic_promotions.branch_id')
            ->orderByDesc('clinic_promotions.is_active')
            ->orderBy('clinic_promotions.priority')
            ->get([
                'clinic_promotions.id', 'clinic_promotions.name', 'clinic_promotions.discount_type',
                'clinic_promotions.discount_value', 'clinic_promotions.scope', 'clinic_promotions.item_type',
                'clinic_promotions.starts_at', 'clinic_promotions.ends_at', 'clinic_promotions.priority',
                'clinic_promotions.is_active', 'branches.name as branch_name',
            ])
            ->map(function ($p) use ($today) {
                $starts = $p->starts_at ? Carbon::parse($p->starts_at)->startOfDay() : null;
                $ends = $p->ends_at ? Carbon::parse($p->ends_at)->endOfDay() : null;

                return [
                    'name' => $this->name($p->name),
                    'discount_type' => (string) $p->discount_type,
                    'discount_value' => round((float) $p->discount_value, 3),
                    'scope' => (string) ($p->scope ?: '—'),
                    'item_type' => $p->item_type ? (string) $p->item_type : null,
                    'branch' => $p->branch_name ? $this->name($p->branch_name) : null,
                    'starts_at' => $starts?->toDateString(),
                    'ends_at' => $ends?->toDateString(),
                    'priority' => (int) $p->priority,
                    'is_active' => (bool) $p->is_active,
                    'live' => (bool) $p->is_active && (! $starts || $starts->lte($today)) && (! $ends || $ends->gte($today)),
                ];
            })->all();

        // ---- What the discount costs -----------------------------------------------
        $cost = (float) ($totals->cost ?? 0);
        $profit = (float) ($totals->profit ?? 0);
        $net = $gross - $discount;
        $margin = [
            'gross' => round($gross, 3),
            'discount' => round($discount, 3),
            'net' => round($net, 3),
            'cost' => round($cost, 3),
            'profit' => round($profit, 3),
            'margin_pct' => $net > 0 ? round(($profit / $net) * 100, 1) : 0.0,
            // Margin the clinic would have carried had nothing been discounted —
            // the gap is what the discount policy costs in percentage points.
            'margin_pct_undiscounted' => $gross > 0 ? round((($profit + $discount) / $gross) * 100, 1) : 0.0,
        ];

        return [
            'kpis' => $kpis,
            'trend' => $trend,
            'by_branch' => $byBranch,
            'by_doctor' => $byDoctor,
            'bands' => $bands,
            'coupons' => $coupons,
            'promotions' => $promotions,
            'margin' => $margin,
        ];
    }

    /** Shared shape for the branch/doctor discount-rate tables. */
    protected function rateRow(string $label, $r, float $clinicPct): array
    {
        $gross = (float) $r->gross;
        $discount = (float) $r->discount;
        $pct = $gross > 0 ? round(($discount / $gross) * 100, 2) : 0.0;

        return [
            'label' => $label,
            'visits' => (int) $r->visit_count,
            'discounted_visits' => (int) $r->discounted_count,
            'gross' => round($gross, 3),
            'discount' => round($discount, 3),
            'pct' => $pct,
            // Flagged only when the rate runs half again above the clinic average
            // AND gives away at least 1% of billing — without the floor, a clinic
            // that barely discounts would flag every branch on rounding noise.
            'outlier' => $clinicPct > 0 && $pct > $clinicPct * 1.5 && $pct >= 1.0,
        ];
    }

    /** Branch and doctor names may be stored as {en,ar} JSON blobs. */
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
