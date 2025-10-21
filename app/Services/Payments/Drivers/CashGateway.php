<?php

namespace App\Services\Payments\Drivers;

use App\Models\CommerceOrder;
use App\Models\GatewayAccount;
use App\Services\Payments\Contracts\PaymentGateway;
use Illuminate\Http\Request;

class CashGateway implements PaymentGateway
{
    public function createCheckout(CommerceOrder $order, GatewayAccount $account, array $options = []): array
    {
        // No external checkout; mark as pending COD and return success url
        return [
            'redirectUrl' => route(config('payments.return.success')),
            'reference' => 'COD-'.($order->code),
            'providerPayload' => ['note' => 'Cash on delivery'],
        ];
    }

    public function handleCallback(Request $request, GatewayAccount $account): array
    {
        return ['status' => 'pending', 'reference' => null, 'raw' => ['note' => 'cod']];
    }

    public function handleWebhook(Request $request, GatewayAccount $account): array
    {
        return ['status' => 'pending', 'reference' => null, 'raw' => ['note' => 'cod']];
    }
}
