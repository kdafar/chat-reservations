<?php

namespace App\Services\Clinic;

use App\Models\ClinicItem;
use Illuminate\Support\Collection;

/**
 * Expands clinic items into the stock requirements they imply.
 *
 * A SERVICE explodes into its bill-of-materials components (the consumables it
 * uses). A consumable/product stands for itself. This is the single place that
 * turns "what was added to a visit" into "what stock to deduct", used by both
 * the visit item flow and the package flow so the rules never diverge.
 *
 * Requirement shape (matches VisitStockRequestService): [['clinic_item_id' => int, 'qty_base' => float], ...]
 */
class ServiceBomService
{
    /**
     * Requirements implied by a single clinic item at the given quantity.
     * - service  → its non-optional components × qty
     * - other    → itself × qty
     */
    public function requirementsForItem(int $clinicItemId, float $qty = 1.0): array
    {
        if ($clinicItemId <= 0 || $qty <= 0) {
            return [];
        }

        $item = ClinicItem::query()->with('components.component')->find($clinicItemId);
        if (! $item) {
            return [];
        }

        // Non-service: it stands for itself, but only stock-tracked items can be
        // deducted (a non-stockable item has no inventory to move).
        if (! $item->isService()) {
            return $item->is_stockable
                ? [['clinic_item_id' => $item->id, 'qty_base' => $qty]]
                : [];
        }

        // Service: its non-optional, stock-tracked components.
        return $item->components
            ->filter(fn ($c) => ! $c->is_optional
                && (float) $c->qty_base > 0
                && $c->component
                && $c->component->is_stockable)
            ->map(fn ($c) => [
                'clinic_item_id' => (int) $c->component_item_id,
                'qty_base' => (float) $c->qty_base * $qty,
            ])
            ->values()
            ->all();
    }

    /**
     * Per-unit material cost of a service from its bill of materials — the sum
     * of each non-optional component's catalog cost × quantity. Used to fold
     * materials into the service line's cost so visit profit reflects them.
     */
    public function materialCost(int $serviceItemId, float $qty = 1.0): float
    {
        if ($serviceItemId <= 0 || $qty <= 0) {
            return 0.0;
        }

        $item = ClinicItem::query()->with('components.component')->find($serviceItemId);
        if (! $item || ! $item->isService()) {
            return 0.0;
        }

        $perUnit = $item->components
            ->filter(fn ($c) => ! $c->is_optional && (float) $c->qty_base > 0 && $c->component)
            ->sum(fn ($c) => (float) $c->qty_base * (float) ($c->component->default_cost ?? 0));

        return round($perUnit * $qty, 3);
    }

    /**
     * Expand many lines into merged requirements (summed per component id).
     *
     * @param  iterable  $lines  each: ['clinic_item_id' => int, 'qty'|'qty_base' => float]
     */
    public function explode(iterable $lines): array
    {
        $acc = [];

        foreach ($lines as $ln) {
            $id = (int) ($ln['clinic_item_id'] ?? 0);
            $qty = (float) ($ln['qty'] ?? $ln['qty_base'] ?? 0);

            foreach ($this->requirementsForItem($id, $qty) as $req) {
                $cid = (int) $req['clinic_item_id'];
                $acc[$cid] = (float) ($acc[$cid] ?? 0) + (float) $req['qty_base'];
            }
        }

        return collect($acc)
            ->map(fn ($qty, $cid) => ['clinic_item_id' => (int) $cid, 'qty_base' => (float) $qty])
            ->values()
            ->all();
    }

    /** True when the item is a service that has at least one auto-deducted component. */
    public function hasAutoComponents(ClinicItem $item): bool
    {
        if (! $item->isService()) {
            return false;
        }

        return $item->components()->where('is_optional', false)->exists();
    }
}
