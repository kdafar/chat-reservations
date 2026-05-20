<?php

namespace App\Filament\Widgets\Clinic;

use App\Services\Clinic\ClinicReportCache;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class ClinicMarginTrend extends ApexChartWidget
{
    public ?array $filters = [];

    protected static ?string $chartId = 'clinicMarginTrend';

    protected static ?string $heading = 'Margin (Today)';

    protected static ?int $contentHeight = 280;

    protected static ?string $pollingInterval = null;

    protected function getOptions(): array
    {
        if (! config('clinic.visit_financials_enabled', true)) {
            return [
                'chart' => ['type' => 'area', 'height' => 280],
                'series' => [],
                'xaxis' => ['categories' => []],
            ];
        }

        // Force "today" using app timezone
        $today = now()
            ->timezone(config('app.timezone', 'Asia/Kuwait'))
            ->toDateString();

        // Keep filters only for branch/doctor scope
        [, , $branchId, $doctorId] = $this->resolvedFilters();

        // Enforce branch scope for non-admin users, even when filter is empty.
        $branchId = $this->effectiveBranchId($branchId);

        // Optional: enforce doctor scope for doctor users, even when filter is empty.
        $doctorId = $this->effectiveDoctorId($doctorId);

        // Prevent cross-branch/user cache leakage.
        $userId = Filament::auth()->id() ?? auth()->id();
        $cacheKey = 'margin_today:'.md5(json_encode([$today, $branchId, $doctorId, $userId]));

        $row = ClinicReportCache::remember($cacheKey, 60, function () use ($today, $branchId, $doctorId) {
            $q = DB::table('visits')
                ->whereNotNull('computed_at')
                ->whereDate('computed_at', '=', $today);

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

            return $q->selectRaw('
                    COALESCE(SUM(fees_total),0) as fees,
                    COALESCE(SUM(profit_total),0) as profit
                ')
                ->first();
        });

        $fees = (float) ($row->fees ?? 0);
        $profit = (float) ($row->profit ?? 0);

        $margin = 0.0;
        if ($fees > 0) {
            $margin = round(($profit / $fees) * 100, 3);
        }

        return [
            'chart' => [
                'type' => 'area',
                'height' => 280,
                'toolbar' => ['show' => false],
            ],
            'dataLabels' => ['enabled' => false],
            'stroke' => ['curve' => 'smooth', 'width' => 3],
            'series' => [
                ['name' => 'Margin %', 'data' => [$margin]],
            ],
            'xaxis' => [
                'categories' => ['Today'],
                'labels' => ['style' => ['fontFamily' => 'inherit']],
            ],
            'yaxis' => [
                'labels' => ['style' => ['fontFamily' => 'inherit']],
            ],
            'tooltip' => [
                'y' => [
                    'formatter' => "function (val) { return (val ?? 0).toFixed(3) + '%'; }",
                ],
            ],
        ];
    }

    protected function resolvedFilters(): array
    {
        // We keep parsing filters so branch/doctor filters still work,
        // but from/to are irrelevant for this widget now.
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
