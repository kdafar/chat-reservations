<?php

namespace App\Models;

use App\Models\Concerns\LogsClinicActivity;

use App\Models\Concerns\BelongsToPartnerScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicPackage extends Model
{
    use LogsClinicActivity;

    // Clinic-owned catalog: scoped by partner (shared across the clinic's
    // branches; partner_id null = global). branch_id is an optional override.
    use BelongsToPartnerScope;

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'integer',
        'branch_id' => 'integer',
        'name' => 'array',
        'description' => 'array',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'sort_order' => 'integer',
        'default_price' => 'decimal:3',
        'discount_price' => 'decimal:3',
        'offer_starts_at' => 'date',
        'offer_ends_at' => 'date',
    ];

    /** Safety net: derive the owning clinic from the branch when missing. */
    protected static function booted(): void
    {
        static::saving(function (self $m) {
            if (empty($m->partner_id) && ! empty($m->branch_id)) {
                $m->partner_id = Branch::query()->whereKey($m->branch_id)->value('partner_id');
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClinicPackageItem::class, 'clinic_package_id');
    }

    public function getLocalizedNameAttribute(): string
    {
        $locale = app()->getLocale();
        $arr = $this->name ?? [];

        return (string) ($arr[$locale] ?? $arr['en'] ?? $arr['ar'] ?? ('#'.$this->id));
    }

    public function getLocalizedDescriptionAttribute(): string
    {
        $locale = app()->getLocale();
        $arr = $this->description ?? [];

        return (string) ($arr[$locale] ?? $arr['en'] ?? $arr['ar'] ?? '');
    }

    // ---------------------------------------------------------------------
    // Pricing. `default_price` is the main (list) price; `discount_price` is
    // the offer price. Everything that charges or displays a package price
    // goes through these accessors so the two never drift apart.
    // ---------------------------------------------------------------------

    /** True while the offer window is open (null dates = always open). */
    public function getOfferWindowOpenAttribute(): bool
    {
        $today = now()->startOfDay();

        if ($this->offer_starts_at && $today->lt($this->offer_starts_at->startOfDay())) {
            return false;
        }
        if ($this->offer_ends_at && $today->gt($this->offer_ends_at->startOfDay())) {
            return false;
        }

        return true;
    }

    /** A discount only counts when it is set, cheaper, and currently in window. */
    public function getHasDiscountAttribute(): bool
    {
        if ($this->discount_price === null) {
            return false;
        }

        $discount = (float) $this->discount_price;
        $main = (float) $this->default_price;

        return $discount > 0 && $discount < $main && $this->offer_window_open;
    }

    /** What the patient actually pays per unit. */
    public function getEffectivePriceAttribute(): float
    {
        return $this->has_discount ? (float) $this->discount_price : (float) $this->default_price;
    }

    /** How much the patient saves per unit. */
    public function getSavingsAmountAttribute(): float
    {
        return $this->has_discount
            ? round((float) $this->default_price - (float) $this->discount_price, 3)
            : 0.0;
    }

    /** Saving as a whole percent, for the "SAVE 30%" badge. */
    public function getSavingsPercentAttribute(): int
    {
        $main = (float) $this->default_price;
        if (! $this->has_discount || $main <= 0) {
            return 0;
        }

        return (int) round(($this->savings_amount / $main) * 100);
    }

    /** Packages published on the public website as live offers. */
    public function scopePublicOffers($query)
    {
        $today = now()->toDateString();

        return $query->where('is_active', true)
            ->where('is_public', true)
            ->where(fn ($w) => $w->whereNull('offer_starts_at')->orWhere('offer_starts_at', '<=', $today))
            ->where(fn ($w) => $w->whereNull('offer_ends_at')->orWhere('offer_ends_at', '>=', $today));
    }
}
