<?php

namespace App\Events;

use App\Models\CommerceOrder;
use App\Models\CommercePayment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCaptured
{
    use Dispatchable, SerializesModels;

    public CommerceOrder $order;

    public CommercePayment $payment;

    public function __construct(CommerceOrder $order, CommercePayment $payment)
    {
        $this->order = $order;
        $this->payment = $payment;
    }
}
