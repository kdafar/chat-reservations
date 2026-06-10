<?php

namespace App\Services\Clinic;

use App\Models\ClinicItem;
use App\Models\ClinicItemStock;
use App\Models\ClinicStockMovement;
use Illuminate\Support\Facades\DB;

class ClinicStockService
{
    public function enabled(): bool
    {
        // Default matches config/clinic.php (true). The second arg only kicks in
        // if the key is missing entirely.
        return (bool) config('clinic.inventory_enabled', true);
    }

    /**
     * Restock by stock-units (e.g. 5 vials) OR by base qty.
     * Provide either $qtyStockUnits or $qtyBase.
     */
    public function restock(
        int $branchId,
        ClinicItem $item,
        ?float $qtyStockUnits,
        ?float $qtyBase,
        int $performedBy = 0,
        ?string $notes = null,
        ?object $related = null,
        string $type = 'restock',
    ): ClinicItemStock {
        if (! $this->enabled()) {
            // Do nothing if disabled; but still allow calling safely.
            return $this->getOrCreateStockRow($branchId, $item->id);
        }

        $deltaBase = $this->toBaseQty($item, $qtyStockUnits, $qtyBase);

        return DB::transaction(function () use ($branchId, $item, $deltaBase, $performedBy, $notes, $related, $type) {
            $stock = $this->lockStockRow($branchId, $item->id);

            $before = (float) $stock->qty_on_hand_base;
            $after = $before + $deltaBase;

            $stock->qty_on_hand_base = $after;
            $stock->save();

            $mv = new ClinicStockMovement;
            $mv->branch_id = $branchId;
            $mv->clinic_item_id = $item->id;
            $mv->clinic_item_stock_id = $stock->id;
            $mv->performed_by = $performedBy ?: null;
            $mv->type = $type ?: 'restock';
            $mv->qty_change_base = $deltaBase;
            $mv->before_qty_base = $before;
            $mv->after_qty_base = $after;
            $mv->notes = $notes;

            if ($related) {
                $mv->related()->associate($related);
            }

            $mv->save();

            return $stock;
        });
    }

    public function consume(
        int $branchId,
        ClinicItem $item,
        float $qtyBaseToConsume,
        int $performedBy = 0,
        ?string $notes = null,
        ?object $related = null,
        string $type = 'consume',
    ): ClinicItemStock {
        if (! $this->enabled()) {
            return $this->getOrCreateStockRow($branchId, $item->id);
        }

        if ($qtyBaseToConsume <= 0) {
            return $this->getOrCreateStockRow($branchId, $item->id);
        }

        return DB::transaction(function () use ($branchId, $item, $qtyBaseToConsume, $performedBy, $notes, $related, $type) {
            $stock = $this->lockStockRow($branchId, $item->id);

            $before = (float) $stock->qty_on_hand_base;

            // Enforce non-negative stock
            if ($before < $qtyBaseToConsume) {
                throw new \RuntimeException("Insufficient stock for item #{$item->id}: available {$before}, required {$qtyBaseToConsume}");
            }

            $after = $before - $qtyBaseToConsume;

            $stock->qty_on_hand_base = $after;
            $stock->save();

            $mv = new ClinicStockMovement;
            $mv->branch_id = $branchId;
            $mv->clinic_item_id = $item->id;
            $mv->clinic_item_stock_id = $stock->id;
            $mv->performed_by = $performedBy ?: null;
            $mv->type = $type ?: 'consume';
            $mv->qty_change_base = 0 - $qtyBaseToConsume;
            $mv->before_qty_base = $before;
            $mv->after_qty_base = $after;
            $mv->notes = $notes;

            if ($related) {
                $mv->related()->associate($related);
            }

            $mv->save();

            return $stock;
        });
    }

    private function toBaseQty(ClinicItem $item, ?float $qtyStockUnits, ?float $qtyBase): float
    {
        if ($qtyBase !== null) {
            return (float) $qtyBase;
        }

        $units = (float) ($qtyStockUnits ?? 0);
        $factor = (float) ($item->conversion_factor ?? 0);

        if ($units > 0 && $factor > 0) {
            return $units * $factor;
        }

        // If no conversion is set, treat stock units as base units to avoid crashing
        return $units;
    }

    private function getOrCreateStockRow(int $branchId, int $clinicItemId): ClinicItemStock
    {
        return ClinicItemStock::query()->firstOrCreate(
            ['branch_id' => $branchId, 'clinic_item_id' => $clinicItemId],
            ['qty_on_hand_base' => 0]
        );
    }

    private function lockStockRow(int $branchId, int $clinicItemId): ClinicItemStock
    {
        // Ensure row exists, then lock it
        $this->getOrCreateStockRow($branchId, $clinicItemId);

        return ClinicItemStock::query()
            ->where('branch_id', $branchId)
            ->where('clinic_item_id', $clinicItemId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function availableBase(int $branchId, int $clinicItemId): float
    {
        $row = \App\Models\ClinicItemStock::query()
            ->where('branch_id', $branchId)
            ->where('clinic_item_id', $clinicItemId)
            ->first();

        return (float) ($row?->qty_on_hand_base ?? 0);
    }

    /**
     * Returns an array of shortages:
     * [
     *   ['clinic_item_id' => 12, 'required' => 2.5, 'available' => 1.0, 'missing' => 1.5],
     * ]
     */
    public function shortagesForRequirements(int $branchId, array $requirements): array
    {
        $shortages = [];

        foreach ($requirements as $r) {
            $itemId = (int) ($r['clinic_item_id'] ?? 0);
            $req = (float) ($r['qty_base'] ?? 0);

            if ($itemId <= 0 || $req <= 0) {
                continue;
            }

            $avail = $this->availableBase($branchId, $itemId);

            if ($avail + 1e-9 < $req) {
                $shortages[] = [
                    'clinic_item_id' => $itemId,
                    'required' => $req,
                    'available' => $avail,
                    'missing' => max(0.0, $req - $avail),
                ];
            }
        }

        return $shortages;
    }
}
