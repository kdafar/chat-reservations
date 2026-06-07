<?php

namespace App\Wa\Services;

use App\Wa\Hub\Models\PointPurchase;
use App\Wa\Models\PointUsage;
use Illuminate\Support\Facades\Log;

class GlobalPointService
{
    /**
     * Statuses that should be treated as "successful / usable points".
     * Adjust here if you add more gateways/statuses later.
     */
    private const SUCCESS_STATUSES = ['paid', 'completed'];

    /**
     * Calculate the Global System Balance.
     * Logic: (Sum of ALL Successful Purchases) - (Sum of ALL Usage)
     */
    public function getSystemBalance(): int
    {
        // 1) Total Points Purchased (global pool) from SUCCESS purchases
        $totalPurchased = PointPurchase::query()
            ->whereIn('status', self::SUCCESS_STATUSES)
            ->where('points_purchased', '>', 0)
            ->sum('points_purchased');

        // 2) Total Points Used (global usage)
        $totalUsed = PointUsage::query()
            ->where('points', '>', 0)
            ->sum('points');

        return max(0, (int) $totalPurchased - (int) $totalUsed);
    }

    /**
     * Check if the Global System has enough points.
     */
    public function hasSystemBalance(int $cost = 1): bool
    {
        if ($cost <= 0) {
            return true;
        }

        return $this->getSystemBalance() >= $cost;
    }

    /**
     * Deduct points from the Global System.
     *
     * @param  int|null  $triggerUserId  The user ID triggering this (optional, for logging only)
     * @param  int  $cost  Points to deduct
     * @param  string  $eventType  Type of event (e.g., 'template_message', 'marketing_campaign')
     * @param  array  $meta  Additional metadata
     */
    public function deductSystemPoints(?int $triggerUserId, int $cost, string $eventType, array $meta = []): void
    {
        // Only record usage if there is a cost (Free messages = 0 cost = no record)
        if ($cost <= 0) {
            return;
        }

        // Optional hard check before deduct (prevents going negative logically)
        // If you don't want this exception, change it to "return;" or log warning.
        if (! $this->hasSystemBalance($cost)) {
            Log::warning("GlobalPointService: Insufficient system balance to deduct {$cost} points.", [
                'event_type' => $eventType,
                'trigger_user_id' => $triggerUserId,
                'meta' => $meta,
                'balance' => $this->getSystemBalance(),
            ]);

            // throw new \RuntimeException('Insufficient system points balance.');
            return;
        }

        PointUsage::create([
            'user_id' => $triggerUserId,
            'points' => $cost,
            'event_type' => $eventType,
            'meta' => $meta,
        ]);

        Log::info("GlobalPointService: Deducted {$cost} points for {$eventType}. New System Balance: ".$this->getSystemBalance());
    }
}
