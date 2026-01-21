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

    protected static ?string $heading = 'Profit Trend (Snapshot)';

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

        [$from, $to, $branchId, $doctorId] = $this->resolvedFilters();

        // Enforce branch scope for non-admin users, even when filter is empty.
        $branchId = $this->effectiveBranchId($branchId);

        // Optional: enforce doctor scope for doctor users, even when filter is empty.
        $doctorId = $this->effectiveDoctorId($doctorId);

        // Prevent cross-branch/user cache leakage.
        $userId = Filament::auth()->id() ?? auth()->id();
        $cacheKey = 'profit_trend:'.md5(json_encode([$from, $to, $branchId, $doctorId, $userId]));

        $rows = ClinicReportCache::remember($cacheKey, 60, function () use ($from, $to, $branchId, $doctorId) {
            $q = DB::table('visits')
                ->whereNotNull('computed_at')
                ->whereDate('computed_at', '>=', $from)
                ->whereDate('computed_at', '<=', $to);

            // If branchId is -1, return empty safely (user has no branch assigned).
            if ($branchId === -1) {
                $q->whereRaw('1=0');
            } else {
                $q->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId));
            }

            // If doctorId is -1 (doctor role but no doctor row), return empty.
            if ($doctorId === -1) {
                $q->whereRaw('1=0');
            } else {
                $q->when($doctorId, fn ($qq) => $qq->where('doctor_id', $doctorId));
            }

            return $q->groupByRaw('DATE(computed_at)')
                ->orderByRaw('DATE(computed_at)')
                ->selectRaw('DATE(computed_at) as d, COALESCE(SUM(profit_total),0) as profit')
                ->get();
        });

        $labels = $rows->pluck('d')->all();
        $data = $rows->pluck('profit')->map(fn ($v) => (float) $v)->all();

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
        $from = (string) ($this->filters['from'] ?? now()->startOfMonth()->toDateString());
        $to = (string) ($this->filters['to'] ?? now()->toDateString());

        $branchId = $this->filters['branch_id'] ?? null;
        $branchId = ($branchId !== '' && $branchId !== null) ? (int) $branchId : null;

        $doctorId = $this->filters['doctor_id'] ?? null;
        $doctorId = ($doctorId !== '' && $doctorId !== null) ? (int) $doctorId : null;

        return [$from, $to, $branchId, $doctorId];
    }

    /**
     * For non-admin users, always scope to their assigned branch (branch_user).
     * Returns:
     *  - null  => no branch constraint (admin/super_admin or no user)
     *  - int   => effective branch_id
     *  - -1    => user has no branch assigned; forces empty results safely
     */
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

    /**
     * If the current user is a doctor and no doctor filter is selected,
     * force scope to their doctor_id.
     */
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
