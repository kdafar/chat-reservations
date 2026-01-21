<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchAvailabilityRule;
use Illuminate\Database\Seeder;

class BranchAvailabilityUpdateSeeder extends Seeder
{
    public function run(): void
    {
        // Settings (edit if needed)
        $OPEN = '16:00:00';
        $CLOSE = '01:00:00';           // overnight
        $LEN = 90;                    // minutes
        $STEP = 30;                    // minutes
        $MAX = 6;                     // people
        $LEAD = 60;                    // minutes lead time
        $DEFAULT_CAP = ['2' => 6, '4' => 3, '6' => 2];

        // Optional: limit to specific branches via env, e.g. BRANCH_IDS="5,7"
        $branchIds = collect(explode(',', (string) env('BRANCH_IDS', '')))
            ->filter()->map(fn ($v) => (int) trim($v))->filter()->all();

        $branches = empty($branchIds)
            ? Branch::query()->pluck('id')
            : Branch::query()->whereIn('id', $branchIds)->pluck('id');

        foreach ($branches as $branchId) {
            for ($dow = 0; $dow <= 6; $dow++) {
                $isOpen = $dow !== 0; // 0 = Sunday off

                /** @var BranchAvailabilityRule $rule */
                $rule = BranchAvailabilityRule::firstOrNew([
                    'branch_id' => $branchId,
                    'day_of_week' => $dow,
                ]);

                // Preserve capacity_map if present, clean sizes > MAX; else use default
                $cap = $rule->capacity_map ?? $DEFAULT_CAP;
                $cap = collect($cap ?? [])
                    ->mapWithKeys(fn ($v, $k) => [(string) ((int) $k) => (int) $v])
                    ->filter(fn ($v, $k) => (int) $k <= $MAX && $v > 0)
                    ->all();
                if (empty($cap)) {
                    $cap = $DEFAULT_CAP;
                }

                // open_at/close_at are NOT NULL, so put 00:00:00 on closed days
                $rule->fill([
                    'is_open' => $isOpen,
                    'open_at' => $isOpen ? $OPEN : '00:00:00',
                    'close_at' => $isOpen ? $CLOSE : '00:00:00',
                    'slot_length_minutes' => $LEN,
                    'slot_step_minutes' => $STEP,
                    'max_party_size' => $MAX,
                    'lead_time_minutes' => $LEAD,
                    'capacity_map' => $cap,
                ])->save();
            }
        }
    }
}
