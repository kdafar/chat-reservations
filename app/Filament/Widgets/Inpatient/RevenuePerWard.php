<?php

namespace App\Filament\Widgets\Inpatient;

use App\Models\Inpatient\AdmissionCharge;
use Illuminate\Support\Facades\DB;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

/**
 * Bed-day revenue by ward, this calendar month. Joins charges -> bed_stay
 * -> ward so the rollup correctly reflects rate at time of stay (since
 * each charge ties to its bed_stay snapshot).
 */
class RevenuePerWard extends ApexChartWidget
{
    protected static ?string $chartId = 'inpatientRevenuePerWard';

    protected static ?string $heading = 'Bed-day revenue by ward — this month';

    protected static ?int $contentHeight = 280;

    protected static ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return \App\Models\Inpatient\Ward::query()->exists();
    }

    protected function getOptions(): array
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $monthStart = now($tz)->startOfMonth()->toDateString();

        $rows = DB::table('admission_charges as c')
            ->leftJoin('admission_bed_stays as s', 's.id', '=', 'c.bed_stay_id')
            ->leftJoin('wards as w', 'w.id', '=', 's.ward_id')
            ->where('c.source', AdmissionCharge::SOURCE_BED_DAY)
            ->where('c.charge_date', '>=', $monthStart)
            ->groupBy('w.id', 'w.name')
            ->selectRaw('COALESCE(w.name, "(no ward)") as ward_name, SUM(c.amount) as revenue')
            ->orderByDesc('revenue')
            ->get();

        $labels = $rows->pluck('ward_name')->all();
        $data = $rows->pluck('revenue')->map(fn ($n) => round((float) $n, 3))->all();

        if (empty($data)) {
            $labels = ['No revenue yet'];
            $data = [0];
        }

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 280,
                'toolbar' => ['show' => false],
            ],
            'plotOptions' => [
                'bar' => ['horizontal' => true, 'borderRadius' => 4, 'barHeight' => '60%'],
            ],
            'dataLabels' => [
                'enabled' => true,
                'formatter' => 'function (val) { return val.toFixed(3); }',
            ],
            'series' => [
                ['name' => 'KWD', 'data' => $data],
            ],
            'xaxis' => [
                'categories' => $labels,
                'labels' => ['style' => ['fontFamily' => 'inherit']],
            ],
            'yaxis' => ['labels' => ['style' => ['fontFamily' => 'inherit']]],
            'colors' => ['#f59e0b'],
            'tooltip' => [
                'y' => ['formatter' => 'function (val) { return val.toFixed(3) + " KWD"; }'],
            ],
        ];
    }
}
