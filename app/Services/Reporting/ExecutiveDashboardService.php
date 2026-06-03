<?php

namespace App\Services\Reporting;

use App\Models\Booking;
use App\Models\ClinicItem;
use App\Models\Visit;
use App\Models\VisitItem;
use App\Models\VisitPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Executive dashboard aggregations — extracted verbatim from the Filament
 * ExecutiveDashboard page so the v2 Inertia page produces identical numbers.
 * Each section is independently guarded: a failure logs + returns an empty
 * shape rather than breaking the whole dashboard.
 */
class ExecutiveDashboardService
{
    private const REVENUE_EXPR = 'SUM(COALESCE(fees_total,0) + COALESCE(packages_price_total,0) + COALESCE(items_price_total,0) - COALESCE(discount_total,0))';

    public function build(string $period, ?string $startRaw, ?string $endRaw, ?int $branchId): array
    {
        [$start, $end] = $this->dateRange($period, $startRaw, $endRaw);

        return [
            'range' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'kpis' => $this->guard('kpis', fn () => $this->getKPIs($start, $end, $branchId), []),
            'revenue_trend' => $this->guard('revenue_trend', fn () => $this->getRevenueTrend($start, $end, $branchId), []),
            'payment_mix' => $this->guard('payment_mix', fn () => $this->getPaymentMix($start, $end, $branchId), []),
            'booking_sources' => $this->guard('booking_sources', fn () => $this->getBookingSources($start, $end, $branchId), []),
            'branch_performance' => $this->guard('branch_performance', fn () => $this->getBranchPerformance($start, $end), []),
            'doctor_performance' => $this->guard('doctor_performance', fn () => $this->getDoctorPerformance($start, $end, $branchId), []),
            'item_profitability' => $this->guard('item_profitability', fn () => $this->getItemProfitability($start, $end, $branchId), []),
            'cancellation_analysis' => $this->guard('cancellation', fn () => $this->getCancellationAnalysis($start, $end, $branchId), []),
            'follow_up_funnel' => $this->guard('funnel', fn () => $this->getFollowUpFunnel($start, $end, $branchId), []),
        ];
    }

    public function dateRange(string $period, ?string $startRaw = null, ?string $endRaw = null): array
    {
        try {
            if ($period === 'custom') {
                $start = $startRaw ? Carbon::parse($startRaw)->startOfDay() : now()->startOfMonth();
                $end = $endRaw ? Carbon::parse($endRaw)->endOfDay() : now()->endOfDay();
                return [$start, $end];
            }
            return match ($period) {
                'today' => [now()->startOfDay(), now()->endOfDay()],
                'week' => [now()->startOfWeek(), now()->endOfWeek()],
                'quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
                'year' => [now()->startOfYear(), now()->endOfYear()],
                'month' => [now()->startOfMonth(), now()->endOfMonth()],
                default => [now()->startOfMonth(), now()->endOfMonth()],
            };
        } catch (\Throwable $e) {
            return [now()->startOfMonth(), now()->endOfMonth()];
        }
    }

    protected function guard(string $label, \Closure $fn, $fallback)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            Log::warning("[ExecutiveDashboardService::{$label}] ".$e->getMessage());
            return $fallback;
        }
    }

    protected function getKPIs(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $base = fn () => Visit::query()->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $revenue = (float) (clone $base())->selectRaw(self::REVENUE_EXPR.' as r')->value('r');
        $profit = (float) (clone $base())->sum('profit_total');
        $visits = (int) (clone $base())->count();
        $avgTx = $visits > 0 ? $revenue / $visits : 0;

        // Previous period of equal length for comparison.
        $len = $start->diffInSeconds($end) + 1;
        $prevEnd = (clone $start)->subSecond();
        $prevStart = (clone $prevEnd)->subSeconds($len - 1);
        $pBase = fn () => Visit::query()->where('status', 'completed')
            ->whereBetween('completed_at', [$prevStart, $prevEnd])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $prevRevenue = (float) (clone $pBase())->selectRaw(self::REVENUE_EXPR.' as r')->value('r');
        $prevProfit = (float) (clone $pBase())->sum('profit_total');
        $prevVisits = (int) (clone $pBase())->count();

        // Show rate over bookings in range.
        $bk = Booking::query()->whereBetween('res_date', [$start->toDateString(), $end->toDateString()])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $totalBookings = (int) (clone $bk)->count();
        $completedBookings = (int) (clone $bk)->whereIn('status', ['completed', 'checked_in'])->count();
        $showRate = $totalBookings > 0 ? ($completedBookings / $totalBookings) * 100 : 0;

        $pct = fn ($cur, $prev) => $prev > 0 ? (($cur - $prev) / $prev) * 100 : 0;
        $trend = fn ($cur, $prev) => $cur >= $prev ? 'up' : 'down';

        return [
            'revenue' => ['value' => $revenue, 'change' => $pct($revenue, $prevRevenue), 'trend' => $trend($revenue, $prevRevenue)],
            'profit' => ['value' => $profit, 'change' => $pct($profit, $prevProfit), 'trend' => $trend($profit, $prevProfit)],
            'margin' => ['value' => $revenue > 0 ? ($profit / $revenue) * 100 : 0, 'change' => 0, 'trend' => 'neutral'],
            'avg_transaction' => ['value' => $avgTx, 'change' => 0, 'trend' => 'neutral'],
            'visits' => ['value' => $visits, 'change' => $pct($visits, $prevVisits), 'trend' => $trend($visits, $prevVisits)],
            'show_rate' => ['value' => $showRate, 'change' => 0, 'trend' => 'neutral'],
        ];
    }

    protected function getRevenueTrend(Carbon $start, Carbon $end, ?int $branchId): array
    {
        return Visit::query()->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('DATE(completed_at) as date')
            ->selectRaw(self::REVENUE_EXPR.' as revenue')
            ->selectRaw('SUM(profit_total) as profit')
            ->groupBy('date')->orderBy('date')->get()
            ->map(fn ($r) => ['date' => Carbon::parse($r->date)->format('d M'), 'revenue' => (float) $r->revenue, 'profit' => (float) $r->profit])
            ->values()->toArray();
    }

    protected function getPaymentMix(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $rows = VisitPayment::query()->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->whereHas('visit', fn ($v) => $v->where('branch_id', $branchId)))
            ->selectRaw('method, SUM(amount) as total')->groupBy('method')->get();
        $sum = (float) $rows->sum('total');
        return $rows->map(fn ($r) => [
            'name' => strtoupper((string) ($r->method ?: 'Unknown')),
            'value' => (float) $r->total,
            'percentage' => $sum > 0 ? ((float) $r->total / $sum) * 100 : 0,
        ])->values()->toArray();
    }

    protected function getBookingSources(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $rows = Booking::query()->whereBetween('res_date', [$start->toDateString(), $end->toDateString()])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('source, count(*) as total')->groupBy('source')->get();
        $total = (int) $rows->sum('total');
        return $rows->map(fn ($r) => [
            'name' => ucfirst((string) ($r->source ?: 'Unknown')),
            'value' => (int) $r->total,
            'percentage' => $total > 0 ? ((int) $r->total / $total) * 100 : 0,
        ])->values()->toArray();
    }

    protected function getBranchPerformance(Carbon $start, Carbon $end): array
    {
        $rows = Visit::query()->where('status', 'completed')->whereBetween('completed_at', [$start, $end])
            ->selectRaw('branch_id')
            ->selectRaw(self::REVENUE_EXPR.' as revenue')
            ->selectRaw('SUM(profit_total) as profit')
            ->selectRaw('COUNT(*) as visits')
            ->groupBy('branch_id')->get();

        return $rows->map(function ($r) use ($start, $end) {
            $branch = \App\Models\Branch::find($r->branch_id);
            $bk = Booking::query()->where('branch_id', $r->branch_id)
                ->whereBetween('res_date', [$start->toDateString(), $end->toDateString()]);
            $total = (int) (clone $bk)->count();
            $shown = (int) (clone $bk)->whereIn('status', ['completed', 'checked_in'])->count();
            $revenue = (float) $r->revenue;
            return [
                'branch' => $branch?->localized_name ?? ('#'.$r->branch_id),
                'revenue' => $revenue,
                'profit' => (float) $r->profit,
                'margin' => $revenue > 0 ? ((float) $r->profit / $revenue) * 100 : 0,
                'visits' => (int) $r->visits,
                'avg_tx' => (int) $r->visits > 0 ? $revenue / (int) $r->visits : 0,
                'show_rate' => $total > 0 ? ($shown / $total) * 100 : 0,
            ];
        })->sortByDesc('revenue')->values()->toArray();
    }

    protected function getDoctorPerformance(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $rows = Visit::query()->where('status', 'completed')->whereBetween('completed_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('doctor_id')
            ->selectRaw(self::REVENUE_EXPR.' as revenue')
            ->selectRaw('COUNT(*) as visits')
            ->groupBy('doctor_id')->get();

        return $rows->map(function ($r) use ($start, $end, $branchId) {
            $doctor = \App\Models\Doctor::find($r->doctor_id);
            $comp = (float) \App\Models\DoctorCompensationLedger::query()
                ->where('doctor_id', $r->doctor_id)
                ->whereBetween('created_at', [$start, $end])
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->sum('doctor_cut_amount');
            $revenue = (float) $r->revenue;
            return [
                'name' => $doctor?->name ?? ('#'.$r->doctor_id),
                'visits' => (int) $r->visits,
                'revenue' => $revenue,
                'compensation' => $comp,
                'net_profit' => $revenue - $comp,
            ];
        })->sortByDesc('revenue')->values()->toArray();
    }

    protected function getItemProfitability(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $rows = VisitItem::query()->whereBetween('created_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('clinic_item_id')
            ->selectRaw('SUM(qty) as units_sold')
            ->selectRaw('SUM(line_price_total) as revenue')
            ->selectRaw('SUM(line_cost_total) as cost')
            ->groupBy('clinic_item_id')->get();

        return $rows->map(function ($r) {
            $item = ClinicItem::find($r->clinic_item_id);
            $name = $item?->localized_name ?? 'Unknown';
            $profit = (float) $r->revenue - (float) $r->cost;
            return [
                'type' => (string) ($item?->type ?? 'Unknown'),
                'name' => (string) $name,
                'revenue' => (float) $r->revenue,
                'cost' => (float) $r->cost,
                'profit' => $profit,
                'margin' => (float) $r->revenue > 0 ? ($profit / (float) $r->revenue) * 100 : 0,
                'units_sold' => (float) $r->units_sold,
            ];
        })->sortByDesc('profit')->values()->take(10)->toArray();
    }

    protected function getCancellationAnalysis(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $rows = Booking::query()->whereNotNull('cancelled_at')->whereBetween('cancelled_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('cancellation_reason_code, COUNT(*) as count')->groupBy('cancellation_reason_code')->get();
        $total = (int) $rows->sum('count');
        return $rows->map(fn ($r) => [
            'reason' => (string) ($r->cancellation_reason_code ?? 'No Reason'),
            'count' => (int) $r->count,
            'percentage' => $total > 0 ? ((int) $r->count / $total) * 100 : 0,
        ])->sortByDesc('count')->values()->toArray();
    }

    protected function getFollowUpFunnel(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $q = \App\Models\FollowUpPlan::query()->whereBetween('suggested_at', [$start, $end])
            ->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId));
        $suggested = (int) (clone $q)->count();
        $booked = (int) (clone $q)->whereNotNull('booking_id')->count();
        $completed = (int) (clone $q)->where('status', 'completed')->count();
        return [
            ['stage' => 'Suggested', 'count' => $suggested, 'percentage' => 100],
            ['stage' => 'Booked', 'count' => $booked, 'percentage' => $suggested > 0 ? ($booked / $suggested) * 100 : 0],
            ['stage' => 'Completed', 'count' => $completed, 'percentage' => $suggested > 0 ? ($completed / $suggested) * 100 : 0],
        ];
    }
}
