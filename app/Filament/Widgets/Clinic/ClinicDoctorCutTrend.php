<?php

namespace App\Filament\Widgets\Clinic;

use App\Services\Clinic\ClinicReportCache;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class ClinicDoctorCutTrend extends ApexChartWidget
{
    public ?array $filters = [];

    protected static ?string $chartId = 'clinicDoctorCutTrend';

    protected static ?string $heading = 'Doctor Cut Trend (Ledger Snapshot)';

    protected static ?int $contentHeight = 280;

    protected static ?string $pollingInterval = null;

    protected function getOptions(): array
    {
        if (! config('clinic.doctor_cut_enabled', true)) {
            return [
                'chart' => ['type' => 'bar', 'height' => 280],
                'series' => [],
                'xaxis' => ['categories' => []],
            ];
        }

        [$from, $to, $branchId, $doctorId] = $this->resolvedFilters();

        // Force branch scope for non-admin users even if UI filter is empty.
        $branchId = $this->effectiveBranchId($branchId);

        // Optional: also force doctor scope if current user is a doctor and filter is empty.
        $doctorId = $this->effectiveDoctorId($doctorId);

        // Cache key must include effective scope + user (prevents cross-branch cache leakage).
        $userId = Filament::auth()->id() ?? auth()->id();
        $cacheKey = 'doctor_cut_trend:'.md5(json_encode([$from, $to, $branchId, $doctorId, $userId]));

        $rows = ClinicReportCache::remember($cacheKey, 60, function () use ($from, $to, $branchId, $doctorId) {
            $q = DB::table('doctor_compensation_ledgers')
                ->whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to);

            // If branchId is -1, return empty safely.
            if ($branchId === -1) {
                $q->whereRaw('1=0');
            } else {
                $q->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId));
            }

            $q->when($doctorId, fn ($qq) => $qq->where('doctor_id', $doctorId));

            return $q->groupByRaw('DATE(created_at)')
                ->orderByRaw('DATE(created_at)')
                ->selectRaw('DATE(created_at) as d, COALESCE(SUM(doctor_cut_amount),0) as cut')
                ->get();
        });

        $labels = $rows->pluck('d')->all();
        $data = $rows->pluck('cut')->map(fn ($v) => (float) $v)->all();

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 280,
                'toolbar' => ['show' => false],
            ],
            'plotOptions' => [
                'bar' => [
                    'borderRadius' => 8,
                    'columnWidth' => '55%',
                ],
            ],
            'dataLabels' => ['enabled' => false],
            'series' => [
                ['name' => 'Doctor Cut', 'data' => $data],
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
            // Admin can see all branches, and can optionally filter.
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
