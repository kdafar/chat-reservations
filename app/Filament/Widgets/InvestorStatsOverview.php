<?php

namespace App\Filament\Widgets;

use App\Models\DoctorCompensationLedger;
use App\Models\Visit;
use App\Models\VisitPayment;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvestorStatsOverview extends BaseWidget
{
    // Critical: This connects the widget to the DatePicker on the Dashboard page
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        // 1. Safe Date Range (Default to this month if filter is empty)
        $start = $this->filters['startDate'] ?? now()->startOfMonth();
        $end = $this->filters['endDate'] ?? now()->endOfMonth();

        // 2. Metric: Total Revenue (Invoiced Amount)
        // Source: Visits table (fees_total)
        $revenue = Visit::whereBetween('checked_in_at', [$start, $end])
            ->where('status', 'completed')
            ->sum('fees_total');

        // 3. Metric: Cash In Hand (Actual Collection)
        // Source: visit_payments (The new table we migrated)
        $cashCollected = VisitPayment::whereBetween('paid_at', [$start, $end])
            ->where('status', 'paid')
            ->sum('amount');

        // 4. Metric: Net Profit Estimation
        // Calculation: Revenue - Doctor Commission - Consumables Cost
        $doctorPay = DoctorCompensationLedger::whereBetween('created_at', [$start, $end])
            ->sum('doctor_cut_amount');

        $consumables = Visit::whereBetween('checked_in_at', [$start, $end])
            ->where('status', 'completed')
            ->sum('items_cost_total');

        $netProfit = $revenue - $doctorPay - $consumables;

        // Avoid division by zero
        $margin = $revenue > 0 ? ($netProfit / $revenue) * 100 : 0;

        return [
            Stat::make('Total Invoiced', number_format($revenue, 3).' KD')
                ->description('Gross Revenue (Services + Retail)')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Cash Collected', number_format($cashCollected, 3).' KD')
                ->description('Actual Bank/Drawer Entry')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Net Profit (Est.)', number_format($netProfit, 3).' KD')
                ->description('Margin: '.number_format($margin, 1).'%')
                ->descriptionIcon($netProfit > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($netProfit > 0 ? 'success' : 'danger'),
        ];
    }
}
