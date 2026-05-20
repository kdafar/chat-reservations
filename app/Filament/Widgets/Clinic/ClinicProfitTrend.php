<?php

namespace App\Filament\Widgets\Clinic;

use App\Services\Clinic\ClinicReportCache;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class ClinicProfitTrend extends ApexChartWidget
{
    public ?array $filters = [];

    protected static ?string $chartId = 'clinicProfitTrend';

    protected static ?string $heading = 'Profit Trend (Today)';

    protected static ?int $contentHeight = 280;

    protected static ?string $pollingInterval = null;

    protected function getOptions(): array
    {
        if (! config('clinic.visit_financials_enabled', true)) {
            return [
                'chart' => ['type' => 'line', 'height' => 280],
                'series' => [],
                'xaxis' => ['categories' => []],
            ];
        }

        // Force today (app timezone for label consistency)
        $tz = config('app.timezone', 'Asia/Kuwait');
        $today = now()->timezone($tz)->toDateString();

        // Keep filters only for branch/doctor scope
        [, , $branchId, $doctorId] = $this->resolvedFilters();

        $branchId = $this->effectiveBranchId($branchId);
        $doctorId = $this->effectiveDoctorId($doctorId);

        $userId = Filament::auth()->id() ?? auth()->id();
        $cacheKey = 'profit_trend_today_hourly:'.md5(json_encode([$today, $branchId, $doctorId, $userId]));

        $rows = ClinicReportCache::remember($cacheKey, 60, function () use ($today, $branchId, $doctorId) {
            $q = DB::table('visits')
                ->whereNotNull('computed_at')
                ->whereDate('computed_at', '=', $today);

            if ($branchId === -1) {
                $q->whereRaw('1=0');
            } else {
                $q->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId));
            }

            if ($doctorId === -1) {
                $q->whereRaw('1=0');
            } else {
                $q->when($doctorId, fn ($qq) => $qq->where('doctor_id', $doctorId));
            }

            // Group by hour of computed_at (0..23)
            return $q->groupByRaw('HOUR(computed_at)')
                ->orderByRaw('HOUR(computed_at)')
                ->selectRaw('HOUR(computed_at) as h, COALESCE(SUM(profit_total),0) as profit')
                ->get();
        });

        // Build 24 buckets so the chart doesn't "jump" hours
        $profitByHour = [];
        foreach ($rows as $r) {
            $h = (int) ($r->h ?? 0);
            $profitByHour[$h] = (float) ($r->profit ?? 0);
        }

        $labels = [];
        $data = [];
        for ($h = 0; $h <= 23; $h++) {
            $labels[] = str_pad((string) $h, 2, '0', STR_PAD_LEFT).':00';
            $data[] = (float) ($profitByHour[$h] ?? 0.0);
        }

        return [
            'chart' => [
                'type' => 'line',
                'height' => 280,
                'toolbar' => ['show' => false],
                'zoom' => ['enabled' => false],
            ],
            'stroke' => ['curve' => 'smooth', 'width' => 3],
            'dataLabels' => ['enabled' => false],
            'series' => [
                ['name' => 'Profit', 'data' => $data],
            ],
            'xaxis' => [
                'categories' => $labels,
                'labels' => ['style' => ['fontFamily' => 'inherit']],
            ],
            'yaxis' => [
                'labels' => ['style' => ['fontFamily' => 'inherit']],
            ],
            'tooltip' => [
                'y' => [
                    'formatter' => 'function (val) { return (val ?? 0).toFixed(3); }',
                ],
            ],
        ];
    }

    protected function resolvedFilters(): array
    {
        // Keep parsing for branch/doctor UI filters, but from/to are not used here.
        $from = (string) ($this->filters['from'] ?? now()->startOfMonth()->toDateString());
        $to = (string) ($this->filters['to'] ?? now()->toDateString());

        $branchId = $this->filters['branch_id'] ?? null;
        $branchId = ($branchId !== '' && $branchId !== null) ? (int) $branchId : null;

        $doctorId = $this->filters['doctor_id'] ?? null;
        $doctorId = ($doctorId !== '' && $doctorId !== null) ? (int) $doctorId : null;

        return [$from, $to, $branchId, $doctorId];
    }

    protected function effectiveBranchId(?int $requestedBranchId): ?int
    {
        $user = Filament::auth()->user() ?? auth()->user();

        if (! $user) {
            return $requestedBranchId;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole(['admin', 'super_admin'])) {
            return $requestedBranchId;
        }

        $branchId = (int) DB::table('branch_user')
            ->where('user_id', $user->id)
            ->value('branch_id');

        return $branchId > 0 ? $branchId : -1;
    }

    protected function effectiveDoctorId(?int $requestedDoctorId): ?int
    {
        if ($requestedDoctorId) {
            return $requestedDoctorId;
        }

        $user = Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return null;
        }

        if (! (method_exists($user, 'hasRole') && $user->hasRole('doctor'))) {
            return null;
        }

        $doctorId = (int) DB::table('doctors')
            ->where('user_id', $user->id)
            ->value('id');

        return $doctorId > 0 ? $doctorId : -1;
    }
}
