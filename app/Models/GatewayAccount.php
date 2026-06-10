<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class GatewayAccount extends Model
{
    use \App\Models\Concerns\BelongsToBranchScope;

    protected $fillable = [
        'gateway_id', 'owner_type', 'partner_id', 'branch_id', 'service_id', 'display_name', 'credentials', 'currency', 'is_active', 'is_default',
    ];

    protected $casts = [
        'credentials' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $m) {
            // 1. Normalize scope in a more maintainable way.
            $ownerKeys = ['branch_id', 'partner_id', 'service_id'];
            $activeKey = match ($m->owner_type) {
                'branch' => 'branch_id',
                'partner' => 'partner_id',
                'service' => 'service_id',
                default => null,
            };

            foreach ($ownerKeys as $key) {
                if ($key !== $activeKey) {
                    $m->{$key} = null;
                }
            }

            // 2. If this is default, unset others in the same scope using a clean query scope.
            if ($m->is_default) {
                self::query()
                    ->where('id', '!=', $m->id ?? 0)
                    ->inSameScope($m)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * Scope a query to only include records in the same operational scope.
     */
    public function scopeInSameScope(Builder $query, self $model): void
    {
        $query->where('gateway_id', $model->gateway_id)
            ->where('currency', $model->currency)
            ->where('owner_type', $model->owner_type)
            ->when($model->owner_type === 'branch', fn ($q) => $q->where('branch_id', $model->branch_id))
            ->when($model->owner_type === 'partner', fn ($q) => $q->where('partner_id', $model->partner_id))
            ->when($model->owner_type === 'service', fn ($q) => $q->where('service_id', $model->service_id));
    }

    public function gateway()
    {
        return $this->belongsTo(Gateway::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public static function availableForBooking(\App\Models\Booking $booking): Collection
    {
        $branchId = (int) $booking->branch_id;
        $partnerId = (int) ($booking->branch?->partner_id ?? 0);

        return self::query()
            ->with('gateway')
            ->where('is_active', true)
            ->where(function ($q) use ($branchId, $partnerId) {
                $q->where(function ($sq) use ($branchId) {
                    $sq->where('owner_type', 'branch')->where('branch_id', $branchId);
                })
                    ->orWhere(function ($sq) use ($partnerId) {
                        $sq->where('owner_type', 'partner')->where('partner_id', $partnerId);
                    })
                    ->orWhere('owner_type', 'system');
            })
            // Priority: branch > partner > system
            ->orderByRaw("FIELD(owner_type, 'branch', 'partner', 'system')")
            // Deterministic inside each scope:
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();
    }

    public static function bestForBooking(\App\Models\Booking $booking): ?self
    {
        return self::availableForBooking($booking)
            ->filter(function (self $acc) {
                if (($acc->gateway?->driver ?? null) !== 'myfatoorah') {
                    return false;
                }

                $apiKey = (string) data_get($acc->credentials, 'api_key', '');
                if ($apiKey === '') {
                    return false;
                }

                // Reject obvious placeholders
                if (str_contains($apiKey, 'XXXX')) {
                    return false;
                }

                return true;
            })
            ->sortByDesc(fn (self $a) => (int) $a->is_default)
            ->sortByDesc(fn (self $a) => (int) $a->id)
            ->first();
    }

    /**
     * Best usable MyFatoorah account for a branch (visit-balance payment links).
     * Mirrors bestForBooking() but keyed on branch/partner instead of a booking:
     * branch override > partner > system, skipping accounts without a real key.
     */
    public static function bestForBranch(int $branchId, ?int $partnerId): ?self
    {
        return self::query()
            ->with('gateway')
            ->where('is_active', true)
            ->where(function ($q) use ($branchId, $partnerId) {
                $q->where(function ($sq) use ($branchId) {
                    $sq->where('owner_type', 'branch')->where('branch_id', $branchId);
                })
                    ->when($partnerId, fn ($qq) => $qq->orWhere(function ($sq) use ($partnerId) {
                        $sq->where('owner_type', 'partner')->where('partner_id', $partnerId);
                    }))
                    ->orWhere('owner_type', 'system');
            })
            ->orderByRaw("FIELD(owner_type, 'branch', 'partner', 'system')")
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get()
            ->first(function (self $acc) {
                if (($acc->gateway?->driver ?? null) !== 'myfatoorah') {
                    return false;
                }
                $apiKey = (string) data_get($acc->credentials, 'api_key', '');

                return $apiKey !== '' && ! str_contains($apiKey, 'XXXX');
            });
    }

    public static function paymentOptionsForBooking(\App\Models\Booking $booking): array
    {
        $branchId = (int) $booking->branch_id;
        $partnerId = (int) ($booking->branch?->partner_id ?? 0);

        $accounts = self::query()
            ->where('is_active', true)
            ->where(function ($q) use ($branchId, $partnerId) {
                $q->where(function ($sq) use ($branchId) {
                    $sq->where('owner_type', 'branch')->where('branch_id', $branchId);
                })
                    ->orWhere(function ($sq) use ($partnerId) {
                        $sq->where('owner_type', 'partner')->where('partner_id', $partnerId);
                    })
                    ->orWhere('owner_type', 'system');
            })
            ->orderByRaw("FIELD(owner_type, 'branch', 'partner', 'system')")
            ->get();

        // Build options strictly from config, but keep stable keys.
        // We will accept both patterns:
        //  A) manual method accounts (credentials.method = cash/knet/visa/link)
        //  B) gateway driver accounts (gateway relation present -> key = driver)
        $options = [];

        foreach ($accounts as $acc) {
            // A) Manual method definition (no need for gateway relation)
            $method = (string) data_get($acc->credentials, 'method', '');
            if ($method !== '' && in_array($method, ['cash', 'knet', 'visa', 'link'], true)) {
                // Branch-level should override partner/system automatically due to ordering
                if (! array_key_exists($method, $options)) {
                    $options[$method] = $acc->display_name ?: ucfirst($method);
                }

                continue;
            }

            // B) Online gateway definition (requires gateway)
            $driver = $acc->gateway?->driver;
            if ($driver) {
                if (! array_key_exists($driver, $options)) {
                    $options[$driver] = $acc->display_name ?: ($acc->gateway?->label() ?? ucfirst($driver));
                }
            }
        }

        return $options;
    }

    /**
     * Convenience: ensure at least the manual methods are present.
     * Only include 'link' (online payment) when a valid MyFatoorah gateway
     * account actually exists for this booking — otherwise selecting 'link'
     * would crash later when the controller tries to create an invoice.
     */
    public static function paymentOptionsForBookingWithFallback(\App\Models\Booking $booking): array
    {
        $opts = self::paymentOptionsForBooking($booking);

        if (! empty($opts)) {
            return $opts;
        }

        // Nothing explicitly configured: provide the manual POS defaults.
        $defaults = [
            'cash' => 'Cash',
            'knet' => 'KNET (POS)',
            'visa' => 'Credit Card (POS)',
        ];

        // Only expose 'link' if a usable online gateway account exists.
        if (self::bestForBooking($booking)) {
            $defaults['link'] = 'Payment Link (Online)';
        }

        return $defaults;
    }
}
