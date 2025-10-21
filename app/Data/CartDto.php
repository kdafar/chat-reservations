<?php

namespace App\Data;

class CartDto
{
    public function __construct(
        // UI-friendly list for rendering
        public readonly array $items,

        // Normalized lines for promo calculations (one per cart line)
        // Each line: ['menu_item_id'=>int, 'qty'=>float, 'unit_price'=>float, 'line_total'=>float]
        public readonly array $lines,

        public readonly float $subtotal,
        public readonly float $deliveryFee,
        public readonly float $discount,
        public readonly float $total,

        public readonly int $itemCount,
        public readonly string $currency,

        public readonly ?int $branchId,

        // Optional: info to show in the UI (code, items_used, etc.)
        public readonly ?array $coupon = null,
    ) {}

    public function isEmpty(): bool
    {
        return empty($this->items);
    }
}
