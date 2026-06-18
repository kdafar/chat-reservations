<?php

namespace App\Services\Accounting;

use App\Models\Accounting\PrepaidSchedule;
use Carbon\Carbon;

/**
 * Drives the monthly prepaid-expense amortization run. Posting + idempotency
 * live in AccountingService::recordPrepaymentAmortization.
 */
class PrepaymentService
{
    public function __construct(protected AccountingService $accounting) {}

    /**
     * Amortize every active schedule that has started, for the month of $month.
     *
     * @return array{period:string, schedules:int, posted:int, total:float}
     */
    public function runForMonth(Carbon $month, ?int $userId = null): array
    {
        $monthEnd = $month->copy()->endOfMonth();

        $schedules = PrepaidSchedule::query()
            ->where('status', PrepaidSchedule::STATUS_ACTIVE)
            ->whereDate('start_date', '<=', $monthEnd->toDateString())
            ->orderBy('id')
            ->get();

        $posted = 0;
        $total = 0.0;
        foreach ($schedules as $schedule) {
            $entry = $this->accounting->recordPrepaymentAmortization($schedule, $monthEnd, $userId);
            if ($entry) {
                $posted++;
                $total = round($total + (float) $schedule->amortizations()->where('period_code', $monthEnd->format('Y-m'))->value('amount'), 3);
            }
        }

        return [
            'period' => $monthEnd->format('Y-m'),
            'schedules' => $schedules->count(),
            'posted' => $posted,
            'total' => $total,
        ];
    }
}
