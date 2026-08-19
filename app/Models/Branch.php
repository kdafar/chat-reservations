<?php

namespace App\Models;

use App\Models\Concerns\LogsClinicActivity;

use App\Models\Concerns\HasImageUrl;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

class Branch extends Model
{
    use LogsClinicActivity;

    use HasImageUrl, HasTranslations;

    protected $fillable = [
        'partner_id', 'slug', 'logo_path', 'cover_image_path',
        'name', 'email', 'license_number', 'phone', 'address',
        'city_id', 'block_id', 'latitude', 'longitude',
        'rating_avg', 'rating_count',
        'delivery_fee', 'min_order_amount', 'max_booking_days',
        'is_available', 'open_for_delivery', 'open_for_pickup',
        'is_hub', 'account_id',
    ];

    protected $appends = [
        'cover_image_url',
        'logo_url',
        'open_now',
    ];

    protected $casts = [
        'name' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'rating_avg' => 'decimal:2',
        'rating_count' => 'integer',
        'delivery_fee' => 'decimal:3',
        'min_order_amount' => 'decimal:3',
        'is_available' => 'boolean',
        'is_hub' => 'boolean',
        'open_for_delivery' => 'boolean',
        'open_for_pickup' => 'boolean',
        'max_booking_days' => 'integer',
        'account_id' => 'integer',
    ];

    public $translatable = ['name'];

    /** The branch's cash / operating account — see ChartOfAccounts. */
    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Accounting\Account::class, 'account_id');
    }

    protected static function booted(): void
    {
        // auto-slug on create if empty
        static::creating(function (Branch $b) {
            if (blank($b->slug)) {
                $base = $b->getTranslation('name', app()->getLocale()) ?? $b->getTranslation('name', 'en') ?? 'branch';
                $b->slug = Str::slug($base) ?: 'branch-'.Str::random(6);
            }
        });
    }

    protected function tags(): Attribute
    {
        return Attribute::make(
            get: function () {
                $tags = [];

                if ($this->rating_avg >= 4.5 && $this->rating_count > 10) {
                    $tags[] = ['text' => 'Top Rated', 'icon' => 'fa-star text-amber-500'];
                }

                if ($this->delivery_fee == 0) {
                    $tags[] = ['text' => 'Free Delivery', 'icon' => 'fa-truck text-green-500'];
                }

                // Example of another potential tag
                // if ($this->created_at->isAfter(now()->subDays(14))) {
                //     $tags[] = ['text' => 'New', 'icon' => 'fa-sparkles text-blue-500'];
                // }

                return $tags;
            }
        );
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        // Check absolute URL columns first if you use them
        $rawCoverUrl = $this->attributes['cover_image_src_url'] ?? $this->attributes['cover_image_url'] ?? null;
        if (! empty($rawCoverUrl) && filter_var($rawCoverUrl, FILTER_VALIDATE_URL)) {
            return $rawCoverUrl;
        }

        $path = $this->cover_image_path ?: null;
        if ($path) {
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                return $path;
            }
            $path = ltrim($path, '/');
            $path = preg_replace('#^(public/|storage/)+#', '', $path);

            return Storage::disk('public')->url($path);
        }

        // If you prefer a visible placeholder instead of null:
        // return asset('images/restaurant-cover-placeholder.jpg');
        return null;
    }

    public function getLogoUrlAttribute(): ?string
    {
        // 1) A stored absolute URL wins (check both columns if you have them)
        $rawLogoUrl = $this->attributes['logo_src_url'] ?? $this->attributes['logo_url'] ?? null;
        if (! empty($rawLogoUrl) && filter_var($rawLogoUrl, FILTER_VALIDATE_URL)) {
            return $rawLogoUrl;
        }

        // 2) Relative file path on the public disk
        $path = $this->logo_path ?: null;
        if ($path) {
            // If someone saved http(s) directly in logo_path, just return it.
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                return $path;
            }

            // normalize weird prefixes / slashes (public/…, storage/…)
            $path = ltrim($path, '/');
            $path = preg_replace('#^(public/|storage/)+#', '', $path);

            // Build URL using the *public* disk (respects APP_URL + /storage)
            return Storage::disk('public')->url($path);
        }

        // 3) No logo — return null so callers render their own fallback (the
        // clinic site shows a branded icon). Pointing at a placeholder file
        // that doesn't exist just yields a broken image.
        return null;
    }

    public function getOpenNowAttribute(): bool
    {
        return $this->isOpenNow();
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'branch_service');
    }

    public function cuisines()
    {
        return $this->belongsToMany(Cuisine::class, 'branch_cuisine');
    }

    public function menus()
    {
        return $this->hasMany(Menu::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function block()
    {
        return $this->belongsTo(Block::class);
    }

    public function openingHours()
    {
        return $this->hasMany(BranchOpeningHour::class);
    }

    public function scopeAvailable(Builder $q): Builder
    {
        return $q->where('is_available', 1);
    }

    public function scopeForService(Builder $q, int $serviceId): Builder
    {
        return $q->whereHas('services', fn ($qq) => $qq->where('services.id', $serviceId));
    }

    public function scopeInBlock(Builder $q, int $blockId): Builder
    {
        return $q->whereHas('coverageBlocks', function ($qq) use ($blockId) {
            $qq->where('blocks.id', $blockId)               // related table
                ->where('branch_block.is_active', 1);        // pivot table
        });
    }

    public function scopeCuisineIn(Builder $q, array $cuisineIds): Builder
    {
        if (empty($cuisineIds)) {
            return $q;
        }

        return $q->whereHas('cuisines', fn ($qq) => $qq->whereIn('cuisines.id', $cuisineIds));
    }

    public function scopeSortByParam(Builder $q, string $sort, ?int $blockId = null): Builder
    {
        $sort = strtolower($sort);
        $locale = app()->getLocale();

        return match ($sort) {
            'rating' => $q->orderByDesc('rating_avg'),
            'a_z' => $q->orderBy("name->$locale"),
            'z_a' => $q->orderByDesc("name->$locale"),
            'fee_asc' => $this->orderByEffectiveFee($q, 'asc', $blockId),
            'fee_desc' => $this->orderByEffectiveFee($q, 'desc', $blockId),
            default => $q->orderByDesc('rating_avg')->orderBy("name->$locale"),
        };
    }

    protected function orderByEffectiveFee(Builder $q, string $dir = 'asc', ?int $blockId = null): Builder
    {
        // Prefer pivot fee for the requested block, fallback to branches.delivery_fee (if you have it)
        // If you don't have branches.delivery_fee, remove the COALESCE fallback.
        $placeholder = $blockId ?? 0;

        return $q->orderByRaw("
            COALESCE(
                (SELECT bb.delivery_fee
                   FROM branch_block bb
                  WHERE bb.branch_id = branches.id
                    AND bb.block_id = ?
                  LIMIT 1),
                branches.delivery_fee
            ) $dir
        ", [$placeholder]);
    }

    public function coverageBlocks()
    {
        // Pivot: branch_block
        return $this->belongsToMany(Block::class, 'branch_block')
            ->withPivot(['delivery_fee', 'min_order_amount', 'is_active'])
            ->withTimestamps();
    }

    public function isOpenNow(?CarbonInterface $at = null): bool
    {
        $at = $at?->copy() ?? now('Asia/Kuwait');
        $day = (int) $at->dayOfWeekIso; // Mon=1..Sun=7
        $map = [7 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6]; // convert to our 0..6 (Sun..Sat)
        $row = $this->openingHours()->where('day_of_week', $map[$day])->first();

        if (! $row || $row->is_closed) {
            return false;
        }
        if (! $row->opens_at || ! $row->closes_at) {
            return false;
        }

        $open = $at->copy()->setTimeFromTimeString($row->opens_at);
        $close = $at->copy()->setTimeFromTimeString($row->closes_at);

        // handle overnight (e.g., 18:00 -> 02:00)
        if ($close->lessThanOrEqualTo($open)) {
            return $at->greaterThanOrEqualTo($open) || $at->lessThan($close->copy()->addDay());
        }

        return $at->betweenIncluded($open, $close);
    }

    public function blocks()
    {
        // Alias for coverage blocks pivot
        return $this->coverageBlocks();
    }

    public function effectiveForBlock(?int $blockId): array
    {
        if ($blockId && $this->relationLoaded('blocks')) {
            $b = $this->blocks->firstWhere('id', $blockId);
            if ($b && $b->pivot && $b->pivot->is_active) {
                return [
                    'delivery_fee' => $b->pivot->delivery_fee ?? $this->delivery_fee,
                    'min_order_amount' => $b->pivot->min_order_amount ?? $this->min_order_amount,
                ];
            }
        }

        return ['delivery_fee' => $this->delivery_fee, 'min_order_amount' => $this->min_order_amount];
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        // If a specific field was requested (e.g. {branch:slug}), honor it:
        if ($field) {
            return $this->where($field, $value)->firstOrFail();
        }

        // Default: try slug first, then fallback to id
        return $this->where('slug', $value)->first()
            ?? $this->findOrFail($value);
    }

    public function coupons()
    {
        return $this->belongsToMany(Coupon::class, 'coupon_branch');
    }

    public function gatewayAccounts()
    {
        return $this->hasMany(GatewayAccount::class, 'branch_id')
            ->where('owner_type', 'branch');
    }

    public function getLocalizedNameAttribute(): string
    {
        $name = $this->attributes['name'] ?? null;

        // If casted to array in $casts, use $this->name directly:
        $arr = is_array($this->name) ? $this->name : (is_string($name) ? json_decode($name, true) : []);
        $locale = app()->getLocale();

        return $arr[$locale] ?? $arr['en'] ?? $arr['ar'] ?? (is_string($this->name) ? $this->name : 'Branch #'.$this->id);
    }

    /**
     * SAFE SCOPE: Filters Branches based on User Access.
     *  Usage: Branch::forUser(auth()->user())->get();
     */
    public function scopeForUser(\Illuminate\Database\Eloquent\Builder $query, ?\App\Models\User $user = null): \Illuminate\Database\Eloquent\Builder
    {
        $user = $user ?? auth()->user();

        // 1. Guard: No user? Return query as-is or empty depending on your preference.
        // Usually safe to return query, but if strictly secured, use whereRaw('1=0').
        if (! $user) {
            return $query;
        }

        // 2. Guard: Admin Bypass
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return $query;
        }

        // 3. Get Logic
        return $query->where(function ($q) use ($user) {
            // A. Direct Access (Staff assigned to specific branch)
            $q->whereIn('id', \Illuminate\Support\Facades\DB::table('branch_user')
                ->where('user_id', $user->id)
                ->pluck('branch_id')
            );

            // B. Manager Access (User manages the Parent Clinic -> sees all branches)
            $managedPartnerIds = \Illuminate\Support\Facades\DB::table('partner_user')
                ->where('user_id', $user->id)
                ->pluck('partner_id');

            if ($managedPartnerIds->isNotEmpty()) {
                $q->orWhereIn('partner_id', $managedPartnerIds);
            }
        });
    }
}
