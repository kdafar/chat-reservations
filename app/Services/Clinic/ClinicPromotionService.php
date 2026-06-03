<?php

namespace App\Services\Clinic;

use App\Models\ClinicItem;
use App\Models\ClinicPackage;
use App\Models\ClinicPromotion;

/**
 * Resolves the active time-bound promotion for a clinic item or a package and
 * returns the per-unit discount it yields. Used by the visit flow to auto-fill
 * a line's discount so staff don't discount each thing by hand.
 *
 * Item match precedence (most specific first): a promotion targeting the exact
 * item ('item') or a hand-picked set containing it ('items') beats one
 * targeting its type ('type'), which beats an "all items" promotion ('all').
 * Package precedence: a hand-picked set ('packages') beats "all packages".
 * Ties break on higher priority.
 */
class ClinicPromotionService
{
    private function activeQuery(?int $branchId)
    {
        $today = now()->toDateString();

        return ClinicPromotion::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $today))
            ->when($branchId, fn ($q) => $q->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $branchId)));
    }

    public function bestPromotion(ClinicItem $item, ?int $branchId = null): ?ClinicPromotion
    {
        $promos = $this->activeQuery($branchId)
            ->where(function ($q) use ($item) {
                $q->where(fn ($w) => $w->where('scope', 'item')->where('clinic_item_id', $item->id))
                    ->orWhere(fn ($w) => $w->where('scope', 'type')->where('item_type', $item->type))
                    ->orWhere('scope', 'all')
                    ->orWhere(fn ($w) => $w->where('scope', 'items')
                        ->whereHas('items', fn ($x) => $x->where('clinic_items.id', $item->id)));
            })
            ->get();

        $rank = ['item' => 4, 'items' => 3, 'type' => 2, 'all' => 1];

        return $this->pickBest($promos, $rank);
    }

    public function bestPackagePromotion(ClinicPackage $package, ?int $branchId = null): ?ClinicPromotion
    {
        $promos = $this->activeQuery($branchId)
            ->where(function ($q) use ($package) {
                $q->where('scope', 'all_packages')
                    ->orWhere(fn ($w) => $w->where('scope', 'packages')
                        ->whereHas('packages', fn ($x) => $x->where('clinic_packages.id', $package->id)));
            })
            ->get();

        $rank = ['packages' => 2, 'all_packages' => 1];

        return $this->pickBest($promos, $rank);
    }

    private function pickBest($promos, array $rank): ?ClinicPromotion
    {
        if ($promos->isEmpty()) {
            return null;
        }

        return $promos
            ->sortByDesc(fn ($p) => ($rank[$p->scope] ?? 0) * 1_000_000 + (int) $p->priority)
            ->first();
    }

    /** Per-unit discount (KWD) for the item at the given unit price, 0 if none. */
    public function discountForItem(ClinicItem $item, float $unitPrice, ?int $branchId = null): float
    {
        $promo = $this->bestPromotion($item, $branchId);

        return $promo ? $promo->discountPerUnit($unitPrice) : 0.0;
    }

    /** Per-unit discount (KWD) for a package at the given unit price, 0 if none. */
    public function discountForPackage(ClinicPackage $package, float $unitPrice, ?int $branchId = null): float
    {
        $promo = $this->bestPackagePromotion($package, $branchId);

        return $promo ? $promo->discountPerUnit($unitPrice) : 0.0;
    }
}
