<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceOrder extends Model
{
    protected $table = 'commerce_orders';

    protected $fillable = [
        'code', 'service_id', 'partner_id', 'branch_id', 'user_id', 'type', 'status', 'address_id',
        'snapshot_partner', 'snapshot_branch', 'snapshot_customer', 'items_total', 'delivery_fee', 'discount_total', 'tax_total',
        'grand_total', 'currency', 'notes', 'placed_at', 'confirmed_at', 'delivered_at', 'payment_status',
    ];

    protected $casts = [
        'snapshot_partner' => 'array',
        'snapshot_branch' => 'array',
        'snapshot_customer' => 'array',
        'placed_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // ... The rest of your model remains the same

    public array $statusOptions = [
        'placed' => 'Placed',
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'preparing' => 'Preparing',
        'ready' => 'Ready',
        'out_for_delivery' => 'Out for Delivery',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];

    public function confirm(int $id): void
    {
        $this->transition($id, 'confirmed');
    }

    public function prepare(int $id): void
    {
        $this->transition($id, 'preparing');
    }

    public function ready(int $id): void
    {
        $this->transition($id, 'ready');
    }

    public function outForDelivery(int $id): void
    {
        $this->transition($id, 'out_for_delivery');
    }

    public function delivered(int $id): void
    {
        $this->transition($id, 'delivered');
    }

    public function cancel(int $id): void
    {
        $this->transition($id, 'cancelled');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function items()
    {
        return $this->hasMany(CommerceOrderItem::class, 'commerce_order_id');
    }

    public function payment()
    {
        return $this->hasOne(CommercePayment::class, 'commerce_order_id');
    }

    public function latestPayment()
    {
        return $this->hasOne(CommercePayment::class)->latestOfMany();
    }

    public function getIsPayableAttribute(): bool
    {
        $p = $this->latestPayment;

        return $p && $p->method === 'online' && $p->status !== 'paid' && in_array($this->status, ['placed', 'pending', 'confirmed']);
    }

    public function getIsCancelableAttribute(): bool
    {
        $p = $this->latestPayment;
        $paid = $p && $p->status === 'paid';

        return ! $paid && in_array($this->status, ['placed', 'pending', 'confirmed']);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $query = $this->newQuery();

        if ($field) {
            return $query->where($field, $value)->firstOrFail();
        }

        // Try by code first
        $byCode = $query->where('code', $value)->first();
        if ($byCode) {
            return $byCode;
        }

        // Legacy numeric id support
        if (ctype_digit((string) $value)) {
            $byId = $query->find((int) $value);
            if ($byId) {
                return $byId;
            }
        }

        abort(404);
    }
}
