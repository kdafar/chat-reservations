<?php

namespace App\Support;

use App\Models\Branch;
use Illuminate\Support\Arr;

class CartService
{
    public static function get(): array
    {
        return session('cart', ['items' => []]);
    }

    public static function set(array $cart): void
    {
        session(['cart' => $cart]);
    }

    public static function clear(array $keep = []): void
    {
        $snapshot = [];
        foreach ($keep as $k) {
            $snapshot[$k] = session($k);
        }
        session()->forget('cart');
        foreach ($snapshot as $k => $v) {
            session([$k => $v]);
        }
    }

    public static function vendorLockedBranchId(): ?int
    {
        return Arr::get(session('cart'), 'branch_id');
    }

    public static function lockToBranch(Branch $branch): void
    {
        $cart = self::get();
        $cart['branch_id'] = $branch->id;
        $cart['partner_id'] = $branch->partner_id;
        self::set($cart);
    }

    /**
     * Enforce single-branch rule.
     *
     * @return array{ok:bool, conflict?:array}
     */
    public static function guardSingleBranch(Branch $incoming, bool $force = false): array
    {
        $currentId = self::vendorLockedBranchId();

        // Empty cart or not locked yet => lock to incoming
        if (! $currentId) {
            self::lockToBranch($incoming);

            return ['ok' => true];
        }

        // Same exact branch => OK
        if ($currentId === (int) $incoming->id) {
            return ['ok' => true];
        }

        // Different branch (even if same partner) => need confirmation
        if (! $force) {
            return [
                'ok' => false,
                'conflict' => [
                    'current_branch_id' => $currentId,
                    'incoming_branch_id' => $incoming->id,
                    'message' => __('front.cart_switch_branch_prompt')
                        ?? 'Your cart has items from another branch. Start a new order?',
                ],
            ];
        }

        // Forced switch: clear cart and lock to new branch
        self::clear(['address', 'city_id', 'block_id']); // keep location
        self::lockToBranch($incoming);

        return ['ok' => true];
    }
}
