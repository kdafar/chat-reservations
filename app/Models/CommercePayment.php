<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommercePayment extends Model
{
    protected $table = 'commerce_payments';

    protected $fillable = [
        'commerce_order_id', 'gateway_account_id', 'method', 'status', 'amount', 'currency',
        'provider_payment_id', 'transaction_id', 'provider_payload', 'paid_at', 'error_message',
    ];

    public const S_PENDING = 'pending';

    public const S_AUTH = 'authorized';

    public const S_PAID = 'paid';

    public const S_FAILED = 'failed';

    public const S_CANCELED = 'canceled';

    public const S_REFUNDED = 'refunded';

    protected $casts = ['provider_payload' => 'array', 'paid_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(CommerceOrder::class, 'commerce_order_id');
    }

    public function gatewayAccount()
    {
        return $this->belongsTo(GatewayAccount::class, 'gateway_account_id');
    }

    public function markPaid(?array $payload = null): void
    {
        $this->forceFill([
            'status' => self::S_PAID,
            'paid_at' => now(),
            'provider_payload' => $payload ?? $this->gateway_payload,
            'error_message' => null,
        ])->save();

        // Update order status and fire events you need
        $this->order->forceFill([
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ])->save();

        event(new \App\Events\PaymentCaptured($this->order, $this)); // hook into #196
    }
}
