<?php

namespace App\Services\Payments\Drivers;

use App\Models\CommerceOrder;
use App\Models\GatewayAccount;
use App\Services\Payments\Contracts\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TapGateway implements PaymentGateway
{
    protected function secret(array $cred): string
    {
        return $cred['secret_key'] ?? throw new RuntimeException('Tap secret_key missing.');
    }

    protected function baseUrl(array $cred): string
    {
        $isTest = ($cred['mode'] ?? 'live') !== 'live';

        return $isTest ? 'https://api.tap.company' : 'https://api.tap.company'; // same base, keys control mode
    }

    public function createCheckout(CommerceOrder $order, GatewayAccount $account, array $options = []): array
    {
        $cred = $account->credentials ?? [];
        $base = $this->baseUrl($cred);
        $sec = $this->secret($cred);

        $payload = [
            'amount' => round((float) $order->grand_total, 3),
            'currency' => $order->currency ?? 'KWD',
            'threeDSecure' => true,
            'save_card' => false,
            'description' => $order->code,
            'statement_descriptor' => config('app.name', 'Zad'),
            'metadata' => ['order_id' => $order->id],
            'customer' => [
                'first_name' => $order->snapshot_customer['name'] ?? 'Guest',
                'email' => $order->snapshot_customer['email'] ?? 'guest@noemail.local',
                'phone' => ['country_code' => '965', 'number' => preg_replace('/\D+/', '', $order->snapshot_customer['phone'] ?? '00000000')],
            ],
            'source' => ['id' => 'src_all'],
            'redirect' => [
                'url' => route('payments.callback', ['driver' => 'tap']),
            ],
        ];

        $res = Http::withToken($sec)->post("{$base}/v2/charges", $payload);
        if (! $res->ok()) {
            throw new RuntimeException('Tap charge create failed: '.json_encode($res->json()));
        }

        $data = $res->json();

        return [
            'redirectUrl' => $data['transaction']['url'] ?? '',
            'reference' => (string) ($data['id'] ?? ''),
            'providerPayload' => $data,
        ];
    }

    public function handleCallback(Request $request, GatewayAccount $account): array
    {
        // Tap redirects with ?tap_id={charge_id}
        $chargeId = $request->get('tap_id');
        if (! $chargeId) {
            return ['status' => 'failed', 'reference' => null, 'raw' => ['reason' => 'missing tap_id']];
        }

        $cred = $account->credentials ?? [];
        $sec = $this->secret($cred);
        $base = $this->baseUrl($cred);

        $res = \Illuminate\Support\Facades\Http::withToken($sec)->get("{$base}/v2/charges/{$chargeId}");
        if (! $res->ok()) {
            return ['status' => 'failed', 'reference' => $chargeId, 'raw' => $res->json()];
        }

        $data = $res->json();
        $status = strtolower($data['status'] ?? 'failed'); // 'CAPTURED','AUTHORIZED','FAILED','CANCELLED'
        $map = [
            'captured' => 'paid',
            'authorized' => 'authorized',
            'failed' => 'failed',
            'cancelled' => 'canceled',
            'cancelled_refund' => 'refunded',
        ];

        return ['status' => $map[$status] ?? 'failed', 'reference' => $chargeId, 'raw' => $data];
    }

    public function handleWebhook(Request $request, GatewayAccount $account): array
    {
        // Verify signature if you configured one; Tap webhooks include 'tap-signature' header
        // TODO: add HMAC verification if you set a webhook secret
        $event = $request->input('event') ?? '';
        $obj = $request->input('object') ?? [];
        $status = strtolower($obj['status'] ?? 'failed');
        $map = ['captured' => 'paid', 'authorized' => 'authorized', 'failed' => 'failed', 'cancelled' => 'canceled'];

        return ['status' => $map[$status] ?? 'failed', 'reference' => $obj['id'] ?? null, 'raw' => $request->all()];
    }
}
