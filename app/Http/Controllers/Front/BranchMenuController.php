<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Promotion;
use App\Models\Service;

class BranchMenuController extends Controller
{
    public function show(Service $service, Branch $branch)
    {
        abort_unless($branch->services()->whereKey($service->id)->exists(), 404);

        // 1. Get all active promotions for this branch that have item or section conditions
        $activePromotions = Promotion::query()
            ->where('branch_id', $branch->id)
            ->active()
            ->with(['conditions', 'actions'])
            ->whereHas('conditions', function ($query) {
                $query->whereIn('condition_type', ['bxgy_same_item', 'has_items_set', 'in_section']);
            })
            ->get();

        // 2. Create TWO maps: one for item-specific promotions and one for section-wide promotions
        $itemPromotionMap = [];
        $sectionPromotionMap = [];

        foreach ($activePromotions as $promo) {
            foreach ($promo->conditions as $condition) {
                switch ($condition->condition_type) {
                    // Handle conditions based on a SINGLE item ID
                    case 'bxgy_same_item':
                        $itemId = $condition->payload['item_id'] ?? null;
                        if ($itemId) {
                            if (! isset($itemPromotionMap[$itemId])) {
                                $itemPromotionMap[$itemId] = [];
                            }
                            $itemPromotionMap[$itemId][] = $promo;
                        }
                        break;

                        // Handle conditions based on a LIST of item IDs
                    case 'has_items_set':
                        $itemIds = array_column($condition->payload['items'] ?? [], 'item_id');
                        foreach ($itemIds as $itemId) {
                            if (! isset($itemPromotionMap[$itemId])) {
                                $itemPromotionMap[$itemId] = [];
                            }
                            $itemPromotionMap[$itemId][] = $promo;
                        }
                        break;

                        // Handle conditions based on a SECTION ID
                    case 'in_section':
                        $sectionId = $condition->payload['section_id'] ?? null;
                        if ($sectionId) {
                            if (! isset($sectionPromotionMap[$sectionId])) {
                                $sectionPromotionMap[$sectionId] = [];
                            }
                            $sectionPromotionMap[$sectionId][] = $promo;
                        }
                        break;
                }
            }
        }

        // 3. Fetch the menus and their items
        $menus = $branch->menus()
            ->where('is_active', true)
            ->whereHas('sections.items', fn ($q) => $q->where('is_available', true))
            ->with(['sections.items' => function ($query) {
                $query->where('is_available', true)->with(['modifierGroups.options']);
            }])
            ->get();

        // 4. Attach promotions from BOTH maps to each item
        $menus->each(function ($menu) use ($itemPromotionMap, $sectionPromotionMap) {
            $menu->sections->each(function ($section) use ($itemPromotionMap, $sectionPromotionMap) {
                $section->items->each(function ($item) use ($itemPromotionMap, $sectionPromotionMap) {
                    // Get promotions that target this specific item
                    $itemPromos = $itemPromotionMap[$item->id] ?? [];

                    // Get promotions that target this item's section
                    $sectionPromos = $sectionPromotionMap[$item->menu_section_id] ?? [];

                    // Merge them and remove any duplicates
                    $allPromos = array_merge($itemPromos, $sectionPromos);
                    $item->setRelation('promotions', collect($allPromos)->unique('id'));
                });
            });
        });

        return view('front.branch-menu', compact('service', 'branch', 'menus'));
    }
}
