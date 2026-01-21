<?php

declare(strict_types=1);

namespace App\Services\Clinic;

use App\Models\Booking;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class DailyClosingReportService
{
    /**
     * Build a daily closing report snapshot with enhanced chart data support.
     */
    public function build(Carbon $day, array $branchIds = []): array
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $date = $day->copy()->timezone($tz)->toDateString();

        // --- BOOKINGS BASE ---
        $bookingsBase = Booking::query()
            ->whereDate('res_date', $date)
            ->when(! empty($branchIds), fn ($q) => $q->whereIn('branch_id', $branchIds));

        $bookingsTotal = (clone $bookingsBase)->count();

        $bookingsByStatus = (clone $bookingsBase)
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status')
            ->map(fn ($v) => (int) $v)
            ->all();

        $bookingsBySource = (clone $bookingsBase)
            ->select(DB::raw("COALESCE(source, 'unknown') as source_key"), DB::raw('COUNT(*) as c'))
            ->groupBy('source_key')
            ->pluck('c', 'source_key')
            ->map(fn ($v) => (int) $v)
            ->all();

        $checkedInCount = (clone $bookingsBase)->whereNotNull('checked_in_at')->count();

        $noShowAutoCount = (clone $bookingsBase)
            ->where('status', 'confirmed')
            ->whereNull('checked_in_at')
            ->whereNotNull('res_end')
            ->where('res_end', '<', now($tz))
            ->count();

        // --- VISITS BASE ---
        $visitsBase = Visit::query()
            ->when(! empty($branchIds), fn ($q) => $q->whereIn('visits.branch_id', $branchIds))
            ->where(function ($q) use ($date) {
                $q->whereDate('visits.checked_in_at', $date)
                    ->orWhereDate('visits.completed_at', $date);
            });

        $visitsTotal = (clone $visitsBase)->count();

        $visitsByStatus = (clone $visitsBase)
            ->select('visits.status', DB::raw('COUNT(*) as c'))
            ->groupBy('visits.status')
            ->pluck('c', 'status')
            ->map(fn ($v) => (int) $v)
            ->all();

        // --- COMPLETED VISITS (Revenue Scoping) ---
        $completedVisits = Visit::query()
            ->whereDate('visits.completed_at', $date)
            ->when(! empty($branchIds), fn ($q) => $q->whereIn('visits.branch_id', $branchIds))
            ->where('visits.status', 'completed');

        $completedCount = (clone $completedVisits)->count();

        $financialRow = (clone $completedVisits)
            ->selectRaw('
                COALESCE(SUM(fees_total), 0) as fees_total,
                COALESCE(SUM(discount_total), 0) as discount_total,
                COALESCE(SUM(items_cost_total), 0) as items_cost_total,
                COALESCE(SUM(items_price_total), 0) as items_price_total,
                COALESCE(SUM(profit_total), 0) as profit_total
            ')
            ->first();

        $financials = [
            'fees_total' => (string) ($financialRow?->fees_total ?? '0'),
            'discount_total' => (string) ($financialRow?->discount_total ?? '0'),
            'items_cost_total' => (string) ($financialRow?->items_cost_total ?? '0'),
            'items_price_total' => (string) ($financialRow?->items_price_total ?? '0'),
            'profit_total' => (string) ($financialRow?->profit_total ?? '0'),
        ];

        // --- DOCTOR BREAKDOWN ---
        $doctors = (clone $completedVisits)
            ->leftJoin('doctors', 'doctors.id', '=', 'visits.doctor_id')
            ->selectRaw('
                visits.doctor_id as doctor_id,
                doctors.name as doctor_name,
                COUNT(*) as visits_completed,
                COALESCE(SUM(visits.fees_total), 0) as fees_total,
                COALESCE(SUM(visits.profit_total), 0) as profit_total
            ')
            ->groupBy('visits.doctor_id', 'doctors.name')
            ->orderByDesc(DB::raw('SUM(visits.profit_total)'))
            ->get()
            ->mapWithKeys(function ($r) {
                $id = (int) ($r->doctor_id ?? 0);

                return [$id => [
                    'doctor_id' => $id,
                    'doctor_name' => $r->doctor_name,
                    'visits_completed' => (int) $r->visits_completed,
                    'fees_total' => (string) $r->fees_total,
                    'profit_total' => (string) $r->profit_total,
                ]];
            })
            ->all();

        // --- NEW: CHART DATA STRUCTURES (V2 IMPROVEMENT) ---

        // 1. Hourly Distribution (Peak Hours)
        $hourlyRaw = (clone $bookingsBase)
            ->selectRaw('HOUR(res_time) as hour_key, COUNT(*) as count')
            ->groupBy('hour_key')
            ->pluck('count', 'hour_key')
            ->all();

        // Normalize to 24 hours to prevent chart gaps
        $hourlyDistribution = [];
        for ($i = 0; $i < 24; $i++) {
            $hourlyDistribution[$i] = (int) ($hourlyRaw[$i] ?? 0);
        }

        // 2. Financial Composition Series (For Donut/Pie)
        $financialSeries = [
            (float) ($financialRow?->fees_total ?? 0),
            (float) ($financialRow?->items_price_total ?? 0),
            (float) ($financialRow?->discount_total ?? 0),
        ];

        return [
            'date' => $date,
            'tz' => $tz,
            'filters' => [
                'branch_ids' => array_map('intval', $branchIds),
            ],
            'bookings' => [
                'total' => $bookingsTotal,
                'by_status' => $bookingsByStatus,
                'by_source' => $bookingsBySource,
                'checked_in' => $checkedInCount,
                'no_show_auto' => $noShowAutoCount,
            ],
            'visits' => [
                'total' => $visitsTotal,
                'by_status' => $visitsByStatus,
                'completed_count' => $completedCount,
                'financials' => $financials,
            ],
            'doctors' => $doctors,
            // ADDED: High-performance chart payloads
            'charts' => [
                'hourly_bookings' => [
                    'labels' => array_map(fn ($h) => sprintf('%02d:00', $h), array_keys($hourlyDistribution)),
                    'data' => array_values($hourlyDistribution),
                ],
                'financial_composition' => [
                    'labels' => ['Service Fees', 'Pharmacy/Items', 'Discounts'],
                    'series' => $financialSeries,
                ],
                'status_distribution' => [
                    'labels' => array_keys($bookingsByStatus),
                    'series' => array_values($bookingsByStatus),
                ],
            ],
        ];
    }
}
