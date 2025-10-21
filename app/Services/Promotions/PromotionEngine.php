<?php

namespace App\Services\Promotions;

use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Support\Collection;

class PromotionEngine
{
    /**
     * Evaluate promotions for a given cart snapshot.
     *
     * Expected $cart structure (flexible):
     * [
     *   'service_id'=>1,'partner_id'=>3,'branch_id'=>4,'order_type'=>'delivery','channel'=>'web','user_id'=>null,
     *   'items'=>[
     *      ['key'=>'i_10__a_1-2','item_id'=>10,'qty'=>3,'unit_price'=>2.500,'category_ids'=>[5,6],'addons'=>[...]],
     *      ...
     *   ],
     *   'subtotal'=>7.500,
     *   'delivery_fee'=>0.500
     * ]
     */
    public function evaluate(array $cart): array
    {
        $items = collect($cart['items'] ?? []);
        $totals = [
            'subtotal' => (float) ($cart['subtotal'] ?? $items->sum(fn ($i) => (float) $i['unit_price'] * (int) $i['qty'])),
            'delivery_fee' => (float) ($cart['delivery_fee'] ?? 0),
            'discount_total' => 0.0,
            'grand_total' => 0.0,
        ];

        $applied = [];
        $consumed = []; // key => qty consumed by exclusive promos

        // Load active promotions scoped to service/partner/branch/channel
        $promos = Promotion::query()
            ->active()
            ->when(isset($cart['service_id']), fn ($q) => $q->where(function ($qq) use ($cart) {
                $qq->whereNull('service_id')->orWhere('service_id', $cart['service_id']);
            }))
            ->when(isset($cart['partner_id']), fn ($q) => $q->where(function ($qq) use ($cart) {
                $qq->whereNull('partner_id')->orWhere('partner_id', $cart['partner_id']);
            }))
            ->when(isset($cart['branch_id']), fn ($q) => $q->where(function ($qq) use ($cart) {
                $qq->whereNull('branch_id')->orWhere('branch_id', $cart['branch_id']);
            }))
            ->orderByDesc('priority')
            ->with(['conditions', 'actions'])
            ->get();

        // Filter by channel/order_type early
        $promos = $promos->filter(function (Promotion $p) use ($cart) {
            $chs = collect($p->channels ?? ['web']);
            if ($cart['channel'] ?? 'web') {
                if ($chs->isNotEmpty() && ! $chs->contains($cart['channel'])) {
                    return false;
                }
            }
            // order_type condition pre-check
            foreach ($p->conditions as $c) {
                if ($c->condition_type === 'order_type') {
                    $allowed = collect($c->payload['allowed'] ?? []);
                    if ($allowed->isNotEmpty() && ! $allowed->contains($cart['order_type'] ?? 'delivery')) {
                        return false;
                    }
                }
            }

            return true;
        });

        // Evaluate
        foreach ($promos as $promo) {
            $result = $this->applyPromotion($promo, $items, $totals, $consumed);
            if ($result['applied']) {
                $applied[] = $result['detail'];
                $totals['discount_total'] += $result['discount'];
                if (($result['delivery_reduction'] ?? 0) > 0) {
                    $totals['delivery_fee'] = max(0, $totals['delivery_fee'] - $result['delivery_reduction']);
                }
            }
        }

        $totals['grand_total'] = max(0, $totals['subtotal'] + $totals['delivery_fee'] - $totals['discount_total']);

        return [
            'applied_promotions' => $applied,
            'totals' => $totals,
            'consumed' => $consumed, // debug
        ];
    }

    protected function applyPromotion(Promotion $promo, Collection $items, array $totals, array &$consumed): array
    {
        // Check common conditions
        foreach ($promo->conditions as $cond) {
            if ($cond->condition_type === 'cart_min_subtotal') {
                $min = (float) ($cond->payload['amount'] ?? 0);
                if ($min > $totals['subtotal'] + 1e-9) {
                    return ['applied' => false, 'discount' => 0, 'detail' => []];
                }
            }
            if ($cond->condition_type === 'time_window') {
                // future: day-of-week / hour checks
            }
        }

        // Route by action/condition sets (MVP: bxgy_same_item + give_free_item, money_off_cart|free_delivery, bundle_price)
        $discount = 0.0;
        $deliveryReduction = 0.0;
        $detail = [
            'promotion_id' => $promo->id,
            'title' => $promo->title,
            'lines' => [],
            'type' => $promo->type,
        ];

        // 1) BXGY same item (condition) + give_free_item (action) OR implicit free via discount
        $bxgyCond = $promo->conditions->firstWhere('condition_type', 'bxgy_same_item');
        $freeAct = $promo->actions->firstWhere('action_type', 'give_free_item');

        if ($bxgyCond && $promo->type !== 'bundle') {
            $itemId = (int) ($bxgyCond->payload['item_id'] ?? 0);
            $buyQty = max(1, (int) ($bxgyCond->payload['buy_qty'] ?? 1));
            $getQty = max(1, (int) ($bxgyCond->payload['get_qty'] ?? 1));
            $repeat = (bool) ($bxgyCond->payload['repeat'] ?? true);

            $line = $items->firstWhere('item_id', $itemId);
            if ($line) {
                $key = $line['key'] ?? ('i_'.$itemId);
                $have = (int) $line['qty'];
                $already = (int) ($consumed[$key] ?? 0);
                $usable = max(0, $have - $already);
                $setSize = $buyQty + $getQty;
                $sets = $repeat ? intdiv($usable, $setSize) : ($usable >= $setSize ? 1 : 0);

                if ($sets > 0) {
                    $unitPrice = (float) $line['unit_price'];
                    if ($freeAct) {
                        // add free items notionally as discount equivalent to price * getQty * sets
                        $discount += $unitPrice * $getQty * $sets;
                        $detail['lines'][] = [
                            'type' => 'bxgy',
                            'item_id' => $itemId,
                            'sets' => $sets,
                            'discount' => round($unitPrice * $getQty * $sets, 3),
                        ];
                    } else {
                        // Fallback: discount the value of Y
                        $discount += $unitPrice * $getQty * $sets;
                    }

                    if ($promo->stack_behavior !== 'stack') {
                        $consumed[$key] = ($consumed[$key] ?? 0) + ($sets * $setSize);
                    }
                }
            }

            return [
                'applied' => $discount > 0,
                'discount' => round($discount, 3),
                'delivery_reduction' => 0,
                'detail' => $discount > 0 ? $detail : [],
            ];
        }

        // 2) Cart-level: money_off_cart / free_delivery (works with optional cart_min_subtotal)
        $moneyOff = $promo->actions->firstWhere('action_type', 'money_off_cart');
        $freeDel = $promo->actions->firstWhere('action_type', 'free_delivery');

        if ($promo->type === PromotionType::CART && ($moneyOff || $freeDel)) {
            if ($moneyOff) {
                $amt = (float) ($moneyOff->payload['amount'] ?? 0);
                if ($amt > 0) {
                    $discount += min($amt, $totals['subtotal']); // safety cap
                    $detail['lines'][] = ['type' => 'money_off_cart', 'amount' => round($amt, 3)];
                }
            }
            if ($freeDel && $totals['delivery_fee'] > 0) {
                $deliveryReduction = $totals['delivery_fee'];
                $detail['lines'][] = ['type' => 'free_delivery', 'amount' => round($deliveryReduction, 3)];
            }

            return [
                'applied' => ($discount + $deliveryReduction) > 0,
                'discount' => round($discount, 3),
                'delivery_reduction' => round($deliveryReduction, 3),
                'detail' => ($discount + $deliveryReduction) > 0 ? $detail : [],
            ];
        }

        // 3) Bundle price: require a set of items → override combined price to fixed
        $bundleAct = $promo->actions->firstWhere('action_type', 'bundle_price');
        $bundleCond = $promo->conditions->firstWhere('condition_type', 'has_items_set');

        if ($promo->type === PromotionType::BUNDLE && $bundleAct && $bundleCond) {
            $reqItems = collect($bundleCond->payload['items'] ?? []); // [{item_id,qty}]
            $price = (float) ($bundleAct->payload['price'] ?? 0);

            if ($reqItems->isNotEmpty() && $price > 0) {
                // compute how many full sets we can make, respecting exclusivity
                $maxSets = PHP_INT_MAX;
                $linesMap = $items->keyBy('item_id');

                foreach ($reqItems as $req) {
                    $iid = (int) $req['item_id'];
                    $need = (int) $req['qty'];
                    $line = $linesMap->get($iid);
                    if (! $line) {
                        $maxSets = 0;
                        break;
                    }

                    $key = $line['key'] ?? ('i_'.$iid);
                    $freeQty = max(0, ((int) $line['qty']) - ((int) ($consumed[$key] ?? 0)));
                    $setsHere = intdiv($freeQty, $need);
                    $maxSets = min($maxSets, $setsHere);
                }

                if ($maxSets > 0 && $maxSets !== PHP_INT_MAX) {
                    // Regular sum for one set
                    $regularPerSet = 0.0;
                    foreach ($reqItems as $req) {
                        $iid = (int) $req['item_id'];
                        $need = (int) $req['qty'];
                        $line = $linesMap->get($iid);
                        $regularPerSet += (float) $line['unit_price'] * $need;
                    }

                    $regularTotal = $regularPerSet * $maxSets;
                    $bundleTotal = $price * $maxSets;
                    $discount = max(0, $regularTotal - $bundleTotal);

                    // consume quantities if exclusive
                    if ($promo->stack_behavior !== 'stack') {
                        foreach ($reqItems as $req) {
                            $iid = (int) $req['item_id'];
                            $need = (int) $req['qty'] * $maxSets;
                            $line = $linesMap->get($iid);
                            $key = $line['key'] ?? ('i_'.$iid);
                            $consumed[$key] = ($consumed[$key] ?? 0) + $need;
                        }
                    }

                    $detail['lines'][] = [
                        'type' => 'bundle_price',
                        'sets' => $maxSets,
                        'regular_total' => round($regularTotal, 3),
                        'bundle_total' => round($bundleTotal, 3),
                        'discount' => round($discount, 3),
                    ];

                    return [
                        'applied' => $discount > 0,
                        'discount' => round($discount, 3),
                        'delivery_reduction' => 0,
                        'detail' => $detail,
                    ];
                }
            }
        }

        return ['applied' => false, 'discount' => 0.0, 'detail' => []];
    }
}
