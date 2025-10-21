<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Coupon extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    protected $fillable = [
        'name', 'code', 'discount_type', 'discount_amount', 'discount_percent', 'min_order_amount',
        'max_uses', 'uses_per_user', 'allowed_order_type', 'starts_at', 'ends_at', 'is_active',
        'apply_to', 'item_limit', 'max_discount_amount', 'notes',
    ];

    public $translatable = ['name'];

    protected $casts = [
        'name' => 'array',
        'discount_amount' => 'decimal:3',
        'discount_percent' => 'decimal:2',
        'min_order_amount' => 'decimal:3',
        'max_discount_amount' => 'decimal:3',
        'max_uses' => 'integer',
        'uses_per_user' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'coupon_branch');
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'coupon_menu');
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(MenuSection::class, 'coupon_menu_section', 'coupon_id', 'menu_section_id');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'coupon_menu_item');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    // Active scope
    public function scopeActive($q)
    {
        $now = now('Asia/Kuwait');

        return $q->where('is_active', true)
            ->where(fn ($x) => $x->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($x) => $x->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    // Guards
    public function appliesToBranch(?Branch $branch): bool
    {
        if (! $branch) {
            return true;
        } // global
        if (! $this->relationLoaded('branches')) {
            $has = $this->branches()->exists();

            return ! $has || $this->branches()->whereKey($branch->getKey())->exists();
        }

        return $this->branches->isEmpty() || $this->branches->contains('id', $branch->getKey());
    }

    public function allowsOrderType(?string $type): bool
    {
        $type = $type ?: 'delivery';

        return $this->allowed_order_type === 'any' || $this->allowed_order_type === $type;
    }

    public function passesMin(float $subtotal): bool
    {
        return $subtotal >= (float) $this->min_order_amount;
    }

    public function remainingGlobalUses(): ?int
    {
        if ($this->max_uses === null) {
            return null;
        }

        return max(0, $this->max_uses - (int) $this->redemptions()->count());
    }

    public function remainingUsesFor(?int $userId, ?string $phone): ?int
    {
        if ($this->uses_per_user === null) {
            return null;
        }
        $q = $this->redemptions();
        if ($userId) {
            $q->where('user_id', $userId);
        } elseif ($phone) {
            $q->where('phone', $phone);
        } else {
            return 0;
        }

        return max(0, $this->uses_per_user - (int) $q->count());
    }

    /** Resolve eligible item IDs (items > sections > menus). Empty => ALL items */
    public function resolveEligibleItemIds(): array
    {
        if ($this->relationLoaded('items')) {
            if ($this->items->isNotEmpty()) {
                return $this->items->pluck('id')->all();
            }
        } elseif ($this->items()->exists()) {
            return $this->items()->pluck('menu_items.id')->all();
        }

        if ($this->relationLoaded('sections')) {
            if ($this->sections->isNotEmpty()) {
                return MenuItem::whereIn('menu_section_id', $this->sections->pluck('id'))->pluck('id')->all();
            }
        } elseif ($this->sections()->exists()) {
            $s = $this->sections()->pluck('menu_sections.id');

            return MenuItem::whereIn('menu_section_id', $s)->pluck('id')->all();
        }

        if ($this->relationLoaded('menus')) {
            if ($this->menus->isNotEmpty()) {
                $m = $this->menus->pluck('id');
                $sec = MenuSection::whereIn('menu_id', $m)->pluck('id');

                return MenuItem::whereIn('menu_section_id', $sec)->pluck('id')->all();
            }
        } elseif ($this->menus()->exists()) {
            $m = $this->menus()->pluck('menus.id');
            $sec = MenuSection::whereIn('menu_id', $m)->pluck('id');

            return MenuItem::whereIn('menu_section_id', $sec)->pluck('id')->all();
        }

        return []; // no scoping -> all items
    }

    /**
     * Compute discount vs cart.
     * $cartLines: [ ['menu_item_id'=>int,'qty'=>float,'unit_price'=>float,'line_total'=>float], ... ]
     * Returns: ['discount'=>float,'eligible_subtotal'=>float,'items_used'=>int[]]
     */
    public function computeDiscount(array $cartLines, float $cartSubtotal, string $orderType = 'delivery'): array
    {
        if (! $this->allowsOrderType($orderType) || ! $this->passesMin($cartSubtotal)) {
            return ['discount' => 0.0, 'eligible_subtotal' => 0.0, 'items_used' => []];
        }

        $eligibleIds = $this->resolveEligibleItemIds();
        $isScoped = ! empty($eligibleIds);

        $eligibleLines = [];
        foreach ($cartLines as $idx => $line) {
            $id = (int) ($line['menu_item_id'] ?? 0);
            $qty = (float) ($line['qty'] ?? 1);
            $unit = (float) ($line['unit_price'] ?? 0);
            $total = (float) ($line['line_total'] ?? ($unit * $qty));
            if ($total <= 0) {
                continue;
            }
            if ($isScoped && ! in_array($id, $eligibleIds, true)) {
                continue;
            }
            $eligibleLines[$idx] = $total;
        }

        if (empty($eligibleLines) && $this->apply_to === 'matching_items') {
            return ['discount' => 0.0, 'eligible_subtotal' => 0.0, 'items_used' => []];
        }

        if ($this->item_limit) {
            arsort($eligibleLines); // highest value first
            $eligibleLines = array_slice($eligibleLines, 0, (int) $this->item_limit, true);
        }

        $eligibleSubtotal = array_sum($eligibleLines);
        $base = ($this->apply_to === 'order') ? $cartSubtotal : $eligibleSubtotal;
        if ($base <= 0) {
            return ['discount' => 0.0, 'eligible_subtotal' => $eligibleSubtotal, 'items_used' => array_keys($eligibleLines)];
        }

        $discount = 0.0;
        if ($this->discount_type === 'percent') {
            $pct = max(0, (float) $this->discount_percent);
            $discount = round($base * ($pct / 100), 3);
        } else {
            $amt = max(0, (float) $this->discount_amount);
            $discount = round(min($amt, $base), 3);
        }

        if ($this->max_discount_amount !== null) {
            $discount = min($discount, (float) $this->max_discount_amount);
        }

        return [
            'discount' => round($discount, 3),
            'eligible_subtotal' => round($eligibleSubtotal, 3),
            'items_used' => array_keys($eligibleLines),
        ];
    }
}
