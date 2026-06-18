<?php

namespace App\Services\Accounting;

use App\Models\Accounting\FixedAsset;
use Carbon\Carbon;

/**
 * Drives the monthly straight-line depreciation run across the fixed-asset
 * register. Posting + idempotency live in AccountingService::recordDepreciation;
 * this service just decides which assets to charge for a given month.
 */
class DepreciationService
{
    public function __construct(protected AccountingService $accounting) {}

    /**
     * Depreciate every active, in-service asset for the month containing $month.
     *
     * @return array{period:string, assets:int, posted:int, total:float}
     */
    public function runForMonth(Carbon $month, ?int $userId = null): array
    {
        $monthEnd = $month->copy()->endOfMonth();

        $assets = FixedAsset::query()
            ->where('status', FixedAsset::STATUS_ACTIVE)
            ->whereDate('in_service_date', '<=', $monthEnd->toDateString())
            ->orderBy('id')
            ->get();

        $posted = 0;
        $total = 0.0;
        foreach ($assets as $asset) {
            $entry = $this->accounting->recordDepreciation($asset, $monthEnd, $userId);
            if ($entry) {
                $posted++;
                $total = round($total + (float) $asset->depreciations()->where('period_code', $monthEnd->format('Y-m'))->value('amount'), 3);
            }
        }

        return [
            'period' => $monthEnd->format('Y-m'),
            'assets' => $assets->count(),
            'posted' => $posted,
            'total' => $total,
        ];
    }
}
