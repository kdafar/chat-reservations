<?php

namespace App\Services;

use App\Enums\PromotionType;
use App\Models\Address;
use App\Models\Branch;
use App\Models\CommerceCart;
use App\Models\MenuItem;
use App\Models\ModifierOption;
use App\Models\Promotion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CartService
{
    public function getCartDto(): \App\Data\CartDto
    {
        $cart = $this->getCartModel();

        if (! $cart) {
            return new \App\Data\CartDto(
                items: [],
                lines: [],
                subtotal: 0.0,
                deliveryFee: 0.0,
                discount: 0.0,
                total: 0.0,
                itemCount: 0,
                currency: 'KWD',
                branchId: null,
                coupon: null,
            );
        }

        // Build UI items
        $items = $cart->items->map(function ($line) {
            $menuItem = $line->item;
            $imageUrl = $menuItem?->image_url ?? asset('images/food-placeholder.jpg');

            $name = $menuItem?->name;
            if (is_array($name)) {
                $name = $name[app()->getLocale()] ?? reset($name) ?? 'Unknown Item';
            } elseif (! is_string($name) || $name === '') {
                $name = 'Unknown Item';
            }

            return [
                'rowId' => $line->row_id,
                'item_id' => $line->item_id,
                'name' => $name,
                'qty' => (int) $line->qty,
                'price' => (float) $line->unit_price,
                'subtotal' => (float) $line->subtotal,
                'modifiers' => $line->modifiers ?? [],
                'modifiers_display' => $this->normalizeLineModifiersForDisplay($line->modifiers)['display'] ?? [],
                'offer' => $line->offer ?? null,
                'note' => $line->note ?? null,
                'image_url' => $imageUrl,
                'image' => $imageUrl,
                'sku' => $menuItem?->sku,
            ];
        })->values()->all();

        // Build normalized lines for coupon engine
        $lines = $cart->items->map(function ($line) {
            $qty = (float) $line->qty;
            $unit = (float) $line->unit_price;
            $total = (float) ($line->subtotal ?? ($qty * $unit));

            return [
                'menu_item_id' => (int) $line->item_id,
                'qty' => $qty,
                'unit_price' => $unit,
                'line_total' => $total,
            ];
        })->values()->all();

        // --- FIX: Use round() for all calculations to maintain precision with floats. ---
        $subtotal = round((float) $cart->items->sum('subtotal'), 3);
        $currency = $cart->currency ?? 'KWD';
        $branchId = $cart->branch_id;
        $branch = optional($cart)->branch;

        $deliveryFee = (float) ($cart->delivery_fee ?? $branch?->delivery_fee ?? 0.0);

        $couponModel = null;
        $couponId = session('cart.coupon_id');
        if ($couponId) {
            $couponModel = \App\Models\Coupon::query()->with(['branches', 'menus', 'sections', 'items'])->active()->find($couponId);
        }

        $discount = 0.0;
        $couponPayload = null;
        if ($couponModel) {
            $calc = method_exists($couponModel, 'computeDiscount')
                ? $couponModel->computeDiscount($lines, $subtotal, session('checkout.order_type', 'delivery'))
                : ['discount' => $couponModel->computeDiscountForSubtotal($subtotal)];

            $discount = round((float) ($calc['discount'] ?? 0.0), 3);
            $couponPayload = ['id' => $couponModel->id, 'code' => $couponModel->code];
        }

        $total = round(max(0.0, $subtotal + $deliveryFee - $discount), 3);

        return new \App\Data\CartDto(
            items: $items,
            lines: $lines,
            subtotal: $subtotal,
            deliveryFee: $deliveryFee,
            discount: $discount,
            total: $total,
            itemCount: (int) $cart->items->sum('qty'),
            currency: $currency,
            branchId: $branchId,
            coupon: $couponPayload,
        );
    }

    public function addItem(Branch $branch, MenuItem $item, int $qty, array $modifiers, bool $force = false, ?string $note = null, ?array $offer = null): array
    {
        $cart = $this->getCartModel();

        if ($cart && $cart->branch_id && $cart->branch_id !== $branch->id) {
            if (! $force) {
                return ['ok' => false, 'conflict' => ['current_branch_name' => $cart->branch->name ?? 'another branch', 'new_branch_name' => $branch->name]];
            }
            $this->clear();
            $cart = null;
        }

        if (! $cart) {
            $cart = CommerceCart::create([
                'user_id' => Auth::id(),
                'session_id' => Auth::guest() ? Session::getId() : null,
                'branch_id' => $branch->id,
                'currency' => 'KWD',
            ]);
        }

        $normalizedMods = $this->normalizeModifiers($modifiers);
        $rowId = $this->buildRowId($item->id, $normalizedMods);
        $unitPrice = $this->computeUnitPrice($item, $normalizedMods);
        $existingItem = $cart->items()->where('row_id', $rowId)->first();

        if ($existingItem) {
            $newQty = $existingItem->qty + $qty;
            $newSubtotal = $this->calculateLineSubtotal($item, $newQty, $offer ?? $existingItem->offer, $unitPrice);
            $existingItem->update([
                'qty' => $newQty,
                'unit_price' => $unitPrice,
                'subtotal' => $newSubtotal,
                'note' => $note ?? $existingItem->note,
                'offer' => $offer ?? $existingItem->offer,
            ]);
        } else {
            $lineSubtotal = $this->calculateLineSubtotal($item, $qty, $offer, $unitPrice);
            $cart->items()->create([
                'item_id' => $item->id,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => $lineSubtotal,
                'modifiers' => $normalizedMods,
                'row_id' => $rowId,
                'note' => $note,
                'offer' => $offer,
            ]);
        }

        return ['ok' => true, 'conflict' => null];
    }

    public function updateItem(string $rowId, int $qty): bool
    {
        $cart = $this->getCartModel();
        if (! $cart) {
            return false;
        }

        $item = $cart->items()->where('row_id', $rowId)->first();
        if (! $item || ! $item->item) {
            return false;
        }

        $lineSubtotal = $this->calculateLineSubtotal($item->item, $qty, $item->offer, (float) $item->unit_price);
        $item->update(['qty' => $qty, 'subtotal' => $lineSubtotal]);

        return true;
    }

    public function removeItem(string $rowId): bool
    {
        $cart = $this->getCartModel();
        if (! $cart) {
            return false;
        }
        $deleted = $cart->items()->where('row_id', $rowId)->delete();
        // FIX: Changed ->isEmpty() to ->count() === 0 to call on the relationship
        if ($cart->fresh()->items()->count() === 0) {
            $cart->delete();
        }

        return $deleted > 0;
    }

    public function clear(): void
    {
        $this->getCartModel()?->delete();
    }

    protected function getCartModel(): ?CommerceCart
    {
        $query = CommerceCart::query()->with([
            'items' => fn ($q) => $q->select('id', 'commerce_cart_id', 'item_id', 'row_id', 'qty', 'unit_price', 'subtotal', 'modifiers', 'note', 'offer'),
            'items.item',
            'branch:id,name,delivery_fee',
            'address:id,user_id,city_id,block_id',
        ]);

        return Auth::check()
            ? $query->where('user_id', Auth::id())->first()
            : $query->where('session_id', Session::getId())->first();
    }

    private function normalizeModifiers(?array $mods): array
    {
        $mods = $mods ?? [];
        ksort($mods);

        return $mods;
    }

    private function buildRowId(int $itemId, array $mods): string
    {
        return hash('sha256', $itemId.'|'.json_encode($mods));
    }

    private function computeUnitPrice(MenuItem $item, array $mods): float
    {
        [, $delta] = $this->expandSelectedModifiersForDisplay($item, $mods);

        return round((float) $item->price + (float) $delta, 3);
    }

    public function setCoupon(\App\Models\Coupon $coupon): void
    {
        session(['cart.coupon_id' => $coupon->getKey(), 'cart.coupon_code' => $coupon->code]);
    }

    public function clearCoupon(): void
    {
        session()->forget(['cart.coupon_id', 'cart.coupon_code']);
    }

    public function getAppliedCoupon(): ?\App\Models\Coupon
    {
        $id = session('cart.coupon_id');

        return $id ? \App\Models\Coupon::query()->active()->find($id) : null;
    }

    private function t(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_string($v)) {
            return $v;
        }
        if (is_array($v)) {
            $loc = app()->getLocale();

            return (string) ($v[$loc] ?? $v['en'] ?? reset($v) ?? '');
        }

        return (string) $v;
    }

    private function calculateLineSubtotal(MenuItem $menuItem, int $qty, ?array $offer, float $unitPrice): float
    {
        if (empty($offer['id'])) {
            return $qty * $unitPrice;
        }

        $promotion = Promotion::with(['actions', 'conditions'])->find($offer['id']);
        if (! $promotion) {
            return $qty * $unitPrice;
        }

        switch ($promotion->type) {
            case PromotionType::BUNDLE:
                $bundleAction = $promotion->actions->firstWhere('action_type', 'bundle_price');
                $bundleCondition = $promotion->conditions->firstWhere('condition_type', 'has_items_set');

                $bundlePrice = (float) ($bundleAction->payload['price'] ?? 0);
                $bundleItems = collect($bundleCondition->payload['items'] ?? []);

                if ($bundlePrice > 0 && $bundleItems->isNotEmpty()) {
                    // Find the total number of items specified in the bundle condition.
                    $totalBundleQty = $bundleItems->sum('qty');
                    if ($totalBundleQty > 0) {
                        // This item's share of the bundle price is its quantity relative to the total bundle quantity.
                        $thisItemInBundle = $bundleItems->firstWhere('item_id', $menuItem->id);
                        $thisItemQtyInBundle = $thisItemInBundle['qty'] ?? 1;
                        $priceShare = ($bundlePrice / $totalBundleQty) * $thisItemQtyInBundle;

                        return $priceShare * $qty;
                    }
                }
                break; // Fallback if bundle is misconfigured

            case PromotionType::ITEM:
                $condition = $promotion->conditions->firstWhere('condition_type', 'bxgy_same_item');
                if ($condition && ! empty($condition->payload['buy_qty']) && ! empty($condition->payload['get_qty'])) {
                    $buyQty = (int) $condition->payload['buy_qty'];
                    $getQty = (int) $condition->payload['get_qty'];
                    $totalParts = $buyQty + $getQty;

                    if ($qty >= $totalParts) {
                        $numberOfDeals = floor($qty / $totalParts);
                        $freeItems = $numberOfDeals * $getQty;
                        $paidItems = $qty - $freeItems;

                        return $paidItems * $unitPrice;
                    }
                }
                break; // Fallback if it's an item promo but not BOGO
        }

        // Default fallback for misconfigured offers or other promotion types (like 'cart')
        return $qty * $unitPrice;
    }

    private function normalizeLineModifiersForDisplay($raw): array
    {
        $display = [];
        if (! is_array($raw)) {
            return ['display' => [], 'offer' => null];
        }

        $ids = [];
        array_walk_recursive($raw, function ($value) use (&$ids) {
            if (is_numeric($value)) {
                $ids[] = (int) $value;
            }
        });

        if ($ids) {
            $options = ModifierOption::whereIn('id', array_unique($ids))->get();
            foreach ($options as $opt) {
                $display[] = [
                    'name' => $opt->getTranslation('name', app()->getLocale()),
                    'price' => (float) ($opt->price_delta ?? 0),
                ];
            }
        }

        return ['display' => $display, 'offer' => $raw['__offer'] ?? null];
    }

    private function expandSelectedModifiersForDisplay(?MenuItem $item, array $selected): array
    {
        if (! $item) {
            return [[], 0.0];
        }
        $delta = 0.0;
        $ids = [];
        array_walk_recursive($selected, function ($value) use (&$ids) {
            if (is_numeric($value)) {
                $ids[] = (int) $value;
            }
        });

        if ($ids) {
            $delta = ModifierOption::whereIn('id', array_unique($ids))->sum('price_delta');
        }

        return [[], round((float) $delta, 3)];
    }

    /** Optional: normalize known offer/bundle shapes to a consistent array for UI. */
    private function extractOfferVisuals($line): array
    {
        // Try common places: $line->meta, $line->offer_items, $line->extras, etc.
        $offer = [
            'badge' => null,      // e.g. "Offer", "Bundle", "Free item"
            'items' => [],        // [['name'=>..., 'qty'=>1, 'price'=>0], ...]
            'savings' => null,    // numeric or null
        ];

        $meta = is_array($line->meta ?? null) ? $line->meta : (json_decode($line->meta ?? '[]', true) ?: []);
        $raw = $meta['offer_items'] ?? $line->offer_items ?? $line->extras ?? null;

        if (is_array($raw) && $raw) {
            foreach ($raw as $ri) {
                $offer['items'][] = [
                    'name' => $this->t($ri['name'] ?? ($ri['title'] ?? '')),
                    'qty' => (int) ($ri['qty'] ?? 1),
                    'price' => isset($ri['price']) ? (float) $ri['price'] : 0.0,
                    'free' => isset($ri['free']) ? (bool) $ri['free'] : ((isset($ri['price']) && (float) $ri['price'] == 0.0)),
                ];
            }
            $offer['badge'] = $meta['offer_badge'] ?? $line->offer_badge ?? __('Offer');
        }

        $offer['savings'] = isset($meta['savings']) ? (float) $meta['savings'] : null;

        // If nothing detected, return empty to avoid UI noise.
        if (! $offer['badge'] && empty($offer['items']) && ! $offer['savings']) {
            return [];
        }

        return $offer;
    }

    public function setAddressById(int $addressId): bool
    {
        $cart = $this->getCartModel();
        if (! $cart) {
            return false;
        }

        $address = Address::where('id', $addressId)->where('user_id', Auth::id())->first();
        if (! $address) {
            return false;
        }

        $cart->address_id = $address->id;
        $cart->delivery_fee = $this->computeDeliveryFee($cart->branch_id, $address->block_id, $address->city_id);
        $cart->order_type = 'delivery';
        $cart->save();
        session(['checkout.address_id' => $address->id]);

        return true;
    }

    public function setGuestAddress(array $payload): bool
    {
        $cart = $this->getCartModel();
        if (! $cart) {
            return false;
        }

        // store the guest address only in session (no DB row)
        Session::put('checkout.guest_address', $payload);

        // you can compute a fee using city_id/block_id in the payload if provided
        $cart->delivery_fee = $this->computeDeliveryFee(
            $cart->branch_id,
            $payload['block_id'] ?? null,
            $payload['city_id'] ?? null,
        );

        $cart->order_type = 'delivery';
        $cart->address_id = null; // guests don’t have an Address row
        $cart->save();

        return true;
    }

    /**
     * Very basic delivery-fee logic (customize to your rules).
     * You can replace this with a proper matrix or pivot lookup.
     */
    protected function computeDeliveryFee(?int $branchId, ?int $blockId, ?int $cityId): float
    {
        return (float) (optional(\App\Models\Branch::find($branchId))->delivery_fee ?? 0);
    }

    public function updateItemNote(string $rowId, ?string $note): bool
    {
        $cart = $this->getCartModel();
        if (! $cart) {
            return false;
        }
        $line = $cart->items()->where('row_id', $rowId)->first();
        if (! $line) {
            return false;
        }
        $line->update(['note' => $note]); // column write

        return true;
    }
}
