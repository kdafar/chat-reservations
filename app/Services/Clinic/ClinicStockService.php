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
        return (bool) config('clinic.inventory_enabled', false);
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
    ): ClinicItemStock {
        if (! $this->enabled()) {
            // Do nothing if disabled; but still allow calling safely.
            return $this->getOrCreateStockRow($branchId, $item->id);
        }

        $deltaBase = $this->toBaseQty($item, $qtyStockUnits, $qtyBase);

        return DB::transaction(function () use ($branchId, $item, $deltaBase, $performedBy, $notes, $related) {
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
            $mv->type = 'restock';
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
    ): ClinicItemStock {
        if (! $this->enabled()) {
            return $this->getOrCreateStockRow($branchId, $item->id);
        }

        if ($qtyBaseToConsume <= 0) {
            return $this->getOrCreateStockRow($branchId, $item->id);
        }

        return DB::transaction(function () use ($branchId, $item, $qtyBaseToConsume, $performedBy, $notes, $related) {
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
            $mv->type = 'consume';
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
}
