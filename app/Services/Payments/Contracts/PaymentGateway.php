<?php

namespace App\Services\Payments\Contracts;

use App\Models\CommerceOrder;
use App\Models\GatewayAccount;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /**
     * Create a checkout and return [redirectUrl, reference, providerPayload].
     * Throw \RuntimeException on failure.
     */
    public function createCheckout(CommerceOrder $order, GatewayAccount $account, array $options = []): array;

    /**
     * Handle user redirect callback (front channel). Return a normalized update:
     * ['status' => 'paid|failed|canceled|pending', 'reference' => 'xxx', 'raw' => [...]]
     */
    public function handleCallback(Request $request, GatewayAccount $account): array;

    /**
     * Handle server webhook (back channel). Same normalized structure as handleCallback.
     */
    public function handleWebhook(Request $request, GatewayAccount $account): array;
}
