<?php

namespace App\Services\Payments\Drivers;

use App\Models\CommerceOrder;
use App\Models\GatewayAccount;
use App\Services\Payments\Contracts\PaymentGateway;
use Illuminate\Http\Request;
use RuntimeException;

class StripeGateway implements PaymentGateway
{
    protected function secret(array $cred): string
    {
        return $cred['secret'] ?? $cred['secret_key'] ?? throw new RuntimeException('Stripe secret missing.');
    }

    public function createCheckout(CommerceOrder $order, GatewayAccount $account, array $options = []): array
    {
        $cred = $account->credentials ?? [];
        \Stripe\Stripe::setApiKey($this->secret($cred));

        $session = \Stripe\Checkout\Session::create([
            'mode' => 'payment',
            'success_url' => route('payments.callback', ['driver' => 'stripe']).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('payments.callback', ['driver' => 'stripe']).'?canceled=1',
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($order->currency ?? 'kwd'),
                    'product_data' => ['name' => $order->code],
                    'unit_amount' => (int) round($order->grand_total * 100),
                ],
                'quantity' => 1,
            ]],
            'metadata' => ['order_id' => $order->id],
        ]);

        return [
            'redirectUrl' => $session->url,
            'reference' => $session->id,
            'providerPayload' => $session->toArray(),
        ];
    }

    public function handleCallback(Request $request, GatewayAccount $account): array
    {
        if ($request->boolean('canceled')) {
            return ['status' => 'canceled', 'reference' => null, 'raw' => $request->all()];
        }
        $sessionId = $request->get('session_id');
        if (! $sessionId) {
            return ['status' => 'failed', 'reference' => null, 'raw' => $request->all()];
        }

        $cred = $account->credentials ?? [];
        \Stripe\Stripe::setApiKey($this->secret($cred));
        $session = \Stripe\Checkout\Session::retrieve($sessionId);

        $status = $session->payment_status === 'paid' ? 'paid' : 'pending';

        return ['status' => $status, 'reference' => $sessionId, 'raw' => $session->toArray()];
    }

    public function handleWebhook(Request $request, GatewayAccount $account): array
    {
        // TODO: verify Stripe signature with webhook secret if configured
        $type = $request->input('type', '');
        $obj = $request->input('data.object', []);
        if ($type === 'checkout.session.completed') {
            return ['status' => 'paid', 'reference' => $obj['id'] ?? null, 'raw' => $request->all()];
        }

        return ['status' => 'pending', 'reference' => $obj['id'] ?? null, 'raw' => $request->all()];
    }
}
