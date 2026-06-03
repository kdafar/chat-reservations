<?php

namespace App\Filament\Widgets\Inpatient;

use App\Models\Inpatient\Admission;
use App\Models\Inpatient\AdmissionBedStay;
use Illuminate\Support\Facades\DB;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

/**
 * Donut chart: how active admissions are distributed across wards right
 * now. Reads the ward of each admission's current bed stay.
 */
class AdmissionsByWard extends ApexChartWidget
{
    protected static ?string $chartId = 'inpatientAdmissionsByWard';

    protected static ?string $heading = 'Active admissions by ward';

    protected static ?int $contentHeight = 280;

    protected static ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return \App\Models\Inpatient\Ward::query()->exists();
    }

    protected function getOptions(): array
    {
        $rows = DB::table('admission_bed_stays as s')
            ->join('admissions as a', 'a.id', '=', 's.admission_id')
            ->join('wards as w', 'w.id', '=', 's.ward_id')
            ->where('a.status', Admission::STATUS_ACTIVE)
            ->whereNull('s.released_at')
            ->groupBy('w.id', 'w.name')
            ->selectRaw('w.name as ward_name, COUNT(*) as cnt')
            ->orderByDesc('cnt')
            ->get();

        $labels = $rows->pluck('ward_name')->all();
        $series = $rows->pluck('cnt')->map(fn ($n) => (int) $n)->all();

        if (empty($series)) {
            $labels = ['No active admissions'];
            $series = [1];
        }

        return [
            'chart' => ['type' => 'donut', 'height' => 280],
            'labels' => $labels,
            'series' => $series,
            'legend' => ['position' => 'right', 'fontFamily' => 'inherit'],
            'colors' => ['#0d9488', '#dc2626', '#2563eb', '#9333ea', '#f59e0b', '#64748b'],
            'plotOptions' => ['pie' => ['donut' => ['size' => '65%']]],
            'dataLabels' => ['enabled' => true, 'formatter' => 'function (val, opts) { return opts.w.config.series[opts.seriesIndex]; }'],
        ];
    }
}
