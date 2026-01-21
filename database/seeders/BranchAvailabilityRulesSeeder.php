<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchAvailabilityRulesSeeder extends Seeder
{
    /**
     * Configure once, reuse forever.
     *
     * - Times are local branch time (strings).
     * - close_at '00:00:00' + open_at later means "cross-midnight" (next day).
     * - Only updates the scheduling fields; preserves capacity_map & UI images.
     */
    public function run(): void
    {
        // Edit this list or provide AVAILABILITY_BRANCH_IDS="4,5" in .env
        $branchIds = collect(
            explode(',', env('AVAILABILITY_BRANCH_IDS', '4,5'))
        )->map(fn ($v) => (int) trim($v))->filter()->values()->all();

        if (empty($branchIds)) {
            $this->command->warn('No branch IDs provided. Set AVAILABILITY_BRANCH_IDS in .env or edit seeder.');

            return;
        }

        // Global schedule config
        $slotLength = (int) env('AVAILABILITY_SLOT_LENGTH', 90); // minutes
        $slotStep = (int) env('AVAILABILITY_SLOT_STEP', 90); // minutes
        $maxParty = (int) env('AVAILABILITY_MAX_PARTY', 6);
        $leadMinutes = (int) env('AVAILABILITY_LEAD_TIME', 60);

        // Default capacity map used **only** when creating a missing row
        $defaultCapacityMap = json_encode([
            '2' => 6,
            '4' => 6,
        ], JSON_UNESCAPED_UNICODE);

        // Schedule by weekday (0=Sun .. 6=Sat)
        $isOpen = [
            0 => 0, // Sun closed
            1 => 1, // Mon
            2 => 1, // Tue
            3 => 1, // Wed
            4 => 1, // Thu
            5 => 1, // Fri
            6 => 1, // Sat
        ];

        $openAt = [
            0 => '00:00:00',    // Sun (unused)
            1 => '17:00:00',    // Mon–Wed 5 PM
            2 => '17:00:00',
            3 => '17:00:00',
            4 => '14:00:00',    // Thu–Sat 2 PM
            5 => '14:00:00',
            6 => '14:00:00',
        ];

        // “12 am” (midnight) — interpreted as next day if open_at > close_at
        $closeAt = [
            0 => '23:59:00',
            1 => '23:59:00',
            2 => '23:59:00',
            3 => '23:59:00',
            4 => '23:59:00',
            5 => '23:59:00',
            6 => '23:59:00',
        ];

        // Fetch existing to preserve capacity_map and UI fields
        $existing = DB::table('branch_availability_rules')
            ->whereIn('branch_id', $branchIds)
            ->get()
            ->keyBy(fn ($r) => $r->branch_id.'-'.$r->day_of_week);

        $now = now();
        $rows = [];

        foreach ($branchIds as $branchId) {
            for ($dow = 0; $dow <= 6; $dow++) {
                $key = $branchId.'-'.$dow;
                $has = $existing->get($key);

                $rows[] = [
                    'branch_id' => $branchId,
                    'day_of_week' => $dow,
                    'is_open' => $isOpen[$dow],
                    'open_at' => $openAt[$dow],
                    'close_at' => $closeAt[$dow],
                    'slot_length_minutes' => $slotLength,
                    'slot_step_minutes' => $slotStep,
                    'max_party_size' => $maxParty,
                    'lead_time_minutes' => $leadMinutes,
                    // only set capacity_map/ui_* when creating new rows
                    'capacity_map' => $has?->capacity_map ?? $defaultCapacityMap,
                    'ui_party_images' => $has?->ui_party_images ?? null,
                    'ui_time_image' => $has?->ui_time_image ?? null,
                    'created_at' => $has?->created_at ?? $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Upsert by (branch_id, day_of_week). Update only scheduling fields.
        DB::table('branch_availability_rules')->upsert(
            $rows,
            ['branch_id', 'day_of_week'],
            [
                'is_open',
                'open_at',
                'close_at',
                'slot_length_minutes',
                'slot_step_minutes',
                'max_party_size',
                'lead_time_minutes',
                'updated_at',
                // capacity_map and UI fields are intentionally NOT overwritten
            ]
        );

        $this->command->info('Branch availability rules upserted for branches: '.implode(',', $branchIds));
    }
}
