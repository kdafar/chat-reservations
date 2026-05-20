<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\ClinicItem;
use App\Models\ClinicItemStock;
use App\Services\Clinic\ClinicStockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ClinicStockSeeder extends Seeder
{
    public function run(): void
    {
        $invEnabled = (bool) config('clinic.inventory_enabled', false);

        Log::info('[ClinicStockSeeder] start', [
            'inventory_enabled' => $invEnabled,
            'timezone' => config('app.timezone'),
            'env' => app()->environment(),
        ]);

        $branchIds = Branch::query()->orderBy('id')->pluck('id')->all();
        Log::info('[ClinicStockSeeder] branches loaded', [
            'branch_count' => count($branchIds),
            'branch_ids_sample' => array_slice($branchIds, 0, 10),
        ]);

        if (empty($branchIds)) {
            $this->command?->warn('No Branch found; skipping ClinicStockSeeder.');
            Log::warning('[ClinicStockSeeder] no branches -> exit');

            return;
        }

        // Keep your current filters as-is (so we can see if they are the issue)
        $itemsQ = ClinicItem::query()
            ->where('is_active', 1)
            ->where('is_stockable', 1)
            ->orderBy('id');

        $itemsTotal = (clone $itemsQ)->count();
        $items = (clone $itemsQ)->limit(50)->get();

        Log::info('[ClinicStockSeeder] items loaded', [
            'items_total_matching_filters' => $itemsTotal,
            'items_loaded' => $items->count(),
            'limit' => 50,
            'first_item_id' => $items->first()?->id,
            'last_item_id' => $items->last()?->id,
        ]);

        if ($items->isEmpty()) {
            $this->command?->warn('No stockable ClinicItem found; skipping ClinicStockSeeder.');
            Log::warning('[ClinicStockSeeder] no items -> exit', [
                'filters' => ['is_active' => 1, 'is_stockable' => 1],
            ]);

            return;
        }

        /** @var ClinicStockService $stockSvc */
        $stockSvc = app(ClinicStockService::class);

        // If you want CLI output while seeding:
        $this->command?->info('[ClinicStockSeeder] inventory_enabled='.($invEnabled ? 'true' : 'false'));
        $this->command?->info('[ClinicStockSeeder] branches='.count($branchIds).' items='.$items->count());

        $openingBase = 10.0000;

        $stats = [
            'pairs_seen' => 0,
            'rows_created' => 0,
            'rows_existing' => 0,
            'restock_attempted' => 0,
            'restock_skipped_nonzero' => 0,
            'restock_exceptions' => 0,
        ];

        foreach ($branchIds as $branchId) {
            Log::info('[ClinicStockSeeder] branch loop start', ['branch_id' => (int) $branchId]);

            foreach ($items as $it) {
                $stats['pairs_seen']++;

                // Ensure row exists
                $created = false;
                $stockRow = ClinicItemStock::query()->firstOrCreate(
                    ['branch_id' => (int) $branchId, 'clinic_item_id' => (int) $it->id],
                    ['qty_on_hand_base' => 0]
                );

                // firstOrCreate() doesn't directly tell you created vs existing without wasRecentlyCreated
                $created = (bool) ($stockRow->wasRecentlyCreated ?? false);

                if ($created) {
                    $stats['rows_created']++;
                } else {
                    $stats['rows_existing']++;
                }

                // Refresh to avoid any stale instance confusion
                $stockRow->refresh();

                $qty = (float) ($stockRow->qty_on_hand_base ?? 0);

                // Optional: opening stock only if currently zero
                if ($qty <= 0) {
                    $stats['restock_attempted']++;

                    Log::info('[ClinicStockSeeder] restock attempt', [
                        'branch_id' => (int) $branchId,
                        'clinic_item_id' => (int) $it->id,
                        'item_name' => $it->localized_name ?? null,
                        'qty_before' => $qty,
                        'opening_base' => $openingBase,
                        'inventory_enabled' => $invEnabled,
                    ]);

                    try {
                        $stockSvc->restock(
                            branchId: (int) $branchId,
                            item: $it,
                            qtyStockUnits: null,
                            qtyBase: $openingBase,
                            performedBy: 1,
                            notes: 'Seeder opening stock',
                            related: null
                        );

                        // Re-read after restock
                        $stockRow->refresh();
                        $qtyAfter = (float) ($stockRow->qty_on_hand_base ?? 0);

                        Log::info('[ClinicStockSeeder] restock done', [
                            'branch_id' => (int) $branchId,
                            'clinic_item_id' => (int) $it->id,
                            'qty_after' => $qtyAfter,
                            // If inventory is disabled, this will usually stay 0.0000
                            'note' => $invEnabled ? null : 'inventory disabled -> restock is no-op (movement will not be recorded)',
                        ]);
                    } catch (\Throwable $e) {
                        $stats['restock_exceptions']++;

                        Log::error('[ClinicStockSeeder] restock exception', [
                            'branch_id' => (int) $branchId,
                            'clinic_item_id' => (int) $it->id,
                            'message' => $e->getMessage(),
                        ]);

                        report($e);
                    }
                } else {
                    $stats['restock_skipped_nonzero']++;

                    Log::debug('[ClinicStockSeeder] restock skipped (already > 0)', [
                        'branch_id' => (int) $branchId,
                        'clinic_item_id' => (int) $it->id,
                        'qty_on_hand_base' => $qty,
                    ]);
                }
            }

            Log::info('[ClinicStockSeeder] branch loop end', ['branch_id' => (int) $branchId]);
        }

        Log::info('[ClinicStockSeeder] end', $stats);

        // CLI summary
        $this->command?->info('[ClinicStockSeeder] done: '.json_encode($stats));
        $this->command?->info('[ClinicStockSeeder] inventory_enabled='.($invEnabled ? 'true' : 'false').' (if false: movements will be empty)');
    }
}
