<?php

namespace App\Listeners;

use App\Events\PaymentCaptured;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class SendPaymentReceipt implements NotTenantAware, ShouldQueue
{
    public function handle(PaymentCaptured $event): void
    {
        // TODO: send email or push a webhook/Slack message
        Log::info('Payment captured', [
            'order_id' => $event->order->id,
            'order_code' => $event->order->code,
            'payment_id' => $event->payment->id,
            'amount' => $event->payment->amount,
            'currency' => $event->payment->currency,
        ]);
    }
}
