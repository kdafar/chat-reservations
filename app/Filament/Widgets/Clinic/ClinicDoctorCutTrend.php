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

    protected static ?string $heading = 'Doctor Cut (Today)';

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

        // Force "today" (use app timezone)
        $today = now()->timezone(config('app.timezone', 'Asia/Kuwait'))->toDateString();

        // Keep existing scoping logic
        [, , $branchId, $doctorId] = $this->resolvedFilters();
        $branchId = $this->effectiveBranchId($branchId);
        $doctorId = $this->effectiveDoctorId($doctorId);

        $userId = Filament::auth()->id() ?? auth()->id();
        $cacheKey = 'doctor_cut_today:'.md5(json_encode([$today, $branchId, $doctorId, $userId]));

        $cut = ClinicReportCache::remember($cacheKey, 60, function () use ($today, $branchId, $doctorId) {
            $q = DB::table('doctor_compensation_ledgers')
                ->whereDate('created_at', '=', $today);

            // If branchId is -1, return empty safely.
            if ($branchId === -1) {
                $q->whereRaw('1=0');
            } else {
                $q->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId));
            }

            $q->when($doctorId, fn ($qq) => $qq->where('doctor_id', $doctorId));

            return (float) ($q->sum('doctor_cut_amount') ?? 0);
        });

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
                ['name' => 'Doctor Cut', 'data' => [(float) $cut]],
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
                    'formatter' => 'function (val) { return (val ?? 0).toFixed(3); }',
                ],
            ],
        ];
    }

    protected function resolvedFilters(): array
    {
        // We keep parsing filters so branch/doctor filters still work,
        // but "from/to" are irrelevant for this widget now.
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
