<?php

namespace App\Services\Payments\Drivers;

use App\Models\CommerceOrder;
use App\Models\GatewayAccount;
use App\Services\Payments\Contracts\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MyFatoorahGateway implements PaymentGateway
{
    protected function baseUrl(array $cred): string
    {
        $isTest = ($cred['mode'] ?? 'live') !== 'live';

        return $isTest ? 'https://apitest.myfatoorah.com' : 'https://api.myfatoorah.com';
    }

    protected function token(array $cred): string
    {
        $token = $cred['api_key'] ?? null;
        if (! $token || ! is_string($token) || Str::length($token) < 20) {
            throw new RuntimeException('MyFatoorah api_key missing/invalid in credentials.');
        }

        return $token;
    }

    protected function client(string $base, string $token)
    {
        return Http::baseUrl($base)
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }

    /**
     * Optionally resolve PaymentMethodId using InitiatePayment
     * $pref can be numeric id (1,2,...) or a name like 'KNET'/'VISA'/'MADA'/'APPLEPAY'
     */
    protected function resolveMethodId($pref, CommerceOrder $order, GatewayAccount $account): int
    {
        // numeric already
        if (is_numeric($pref)) {
            return (int) $pref;
        }

        $cred = $account->credentials ?? [];
        $base = $this->baseUrl($cred);
        $cli = $this->client($base, $this->token($cred));

        // InitiatePayment expects amount & currency
        $ip = $cli->post('/v2/InitiatePayment', [
            'InvoiceAmount' => (float) $order->grand_total,
            'CurrencyIso' => $order->currency ?? 'KWD',
        ]);

        if (! $ip->ok() || ! ($ip['IsSuccess'] ?? false)) {
            // surface the body so you can see what went wrong
            $body = $ip->json() ?: $ip->body();
            throw new RuntimeException('MyFatoorah InitiatePayment failed: '.json_encode($body));
        }

        $methods = (array) ($ip['Data']['PaymentMethods'] ?? []);
        if (empty($methods)) {
            throw new RuntimeException('MyFatoorah: no payment methods returned for amount/currency.');
        }

        $want = strtoupper((string) $pref);
        // Try match by Name or PaymentMethodCode (e.g., KNET / VMP / MADA etc.)
        foreach ($methods as $m) {
            $name = strtoupper((string) ($m['PaymentMethodEn'] ?? $m['PaymentMethodAr'] ?? $m['PaymentMethod'] ?? ''));
            $code = strtoupper((string) ($m['PaymentMethodCode'] ?? ''));
            if ($code === $want || str_contains($name, $want)) {
                return (int) $m['PaymentMethodId'];
            }
        }

        // Fallback: pick the first method
        return (int) ($methods[0]['PaymentMethodId'] ?? 1);
    }

    public function createCheckout(CommerceOrder $order, GatewayAccount $account, array $options = []): array
    {
        $cred = $account->credentials ?? [];
        $base = $this->baseUrl($cred);
        $token = $this->token($cred);
        $client = $this->client($base, $token);

        $lang = app()->getLocale() === 'ar' ? 'AR' : 'EN';

        // Validate callbacks are absolute HTTPS
        $callback = route('payments.callback', ['driver' => 'myfatoorah']);
        $errorUrl = $callback; // MF can use same
        if (! Str::startsWith($callback, 'http')) {
            throw new RuntimeException('payments.callback URL must be absolute; check APP_URL.');
        }

        // Email must be valid; some MF setups reject non-routable TLDs
        $email = $order->snapshot_customer['email'] ?? null;
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'test@example.com';
        }

        // Decide PaymentMethodId
        // Accept: options['mf_method_id'] (int) or options['mf_method'] (string like 'KNET'/'VISA')
        $methodId = isset($options['mf_method_id'])
            ? (int) $options['mf_method_id']
            : $this->resolveMethodId(($options['mf_method'] ?? 'KNET'), $order, $account);

        $payload = [
            'PaymentMethodId' => $methodId,
            'CustomerName' => $order->snapshot_customer['name'] ?? 'Guest',
            'CustomerEmail' => $email,
            'CustomerMobile' => preg_replace('/\D+/', '', $order->snapshot_customer['phone'] ?? '') ?: '00000000',
            'MobileCountryCode' => $options['country_code'] ?? '+965',
            'InvoiceValue' => (float) $order->grand_total,
            'DisplayCurrencyIso' => $order->currency ?? 'KWD',
            'CallBackUrl' => $callback,
            'ErrorUrl' => $errorUrl,
            'Language' => $lang,
            'CustomerReference' => $order->code,
            'UserDefinedField' => (string) $order->id,
            // Optional but nice:
            // 'NotificationOption'  => 'ALL',
        ];

        try {
            $res = $client->post('/v2/ExecutePayment', $payload);
        } catch (Throwable $e) {
            throw new RuntimeException('MyFatoorah network error: '.$e->getMessage(), 0, $e);
        }

        // Better diagnostics for non-JSON responses
        $json = $res->json();
        if (! $res->ok() || ! data_get($json, 'IsSuccess')) {
            $body = $json ?: $res->body();
            throw new RuntimeException('MyFatoorah ExecutePayment failed: '.json_encode($body));
        }

        $data = $json['Data'] ?? [];

        return [
            'redirectUrl' => (string) ($data['PaymentURL'] ?? ''),
            'reference' => (string) ($data['InvoiceId'] ?? ''),
            'providerPayload' => $data,
        ];
    }

    public function handleCallback(Request $request, GatewayAccount $account): array
    {
        $paymentId = $request->get('paymentId');
        $cred = $account->credentials ?? [];
        $base = $this->baseUrl($cred);
        $client = $this->client($base, $this->token($cred));

        if (! $paymentId) {
            return ['status' => 'failed', 'reference' => null, 'raw' => ['reason' => 'missing paymentId']];
        }

        $res = $client->post('/v2/getPaymentStatus', ['KeyType' => 'PaymentId', 'Key' => $paymentId]);
        $json = $res->json();

        if (! $res->ok() || ! data_get($json, 'IsSuccess')) {
            return ['status' => 'failed', 'reference' => null, 'raw' => ($json ?: $res->body())];
        }

        $data = $json['Data'] ?? [];
        $status = strtolower($data['InvoiceStatus'] ?? 'failed');
        $map = ['paid' => 'paid', 'pending' => 'pending', 'cancelled' => 'canceled', 'canceled' => 'canceled', 'failed' => 'failed'];

        return [
            'status' => $map[$status] ?? 'failed',
            'reference' => (string) ($data['InvoiceId'] ?? ''),
            'raw' => $data,
        ];
    }

    public function handleWebhook(Request $request, GatewayAccount $account): array
    {
        $cred = $account->credentials ?? [];
        $base = $this->baseUrl($cred);
        $client = $this->client($base, $this->token($cred));

        if ($pid = $request->input('PaymentId') ?? $request->input('paymentId')) {
            return $this->handleCallback(new Request(['paymentId' => $pid]), $account);
        }

        if ($inv = $request->input('InvoiceId') ?? $request->input('invoiceId')) {
            $res = $client->post('/v2/getPaymentStatus', ['KeyType' => 'InvoiceId', 'Key' => (string) $inv]);
            $json = $res->json();
            if ($res->ok() && data_get($json, 'IsSuccess')) {
                $data = $json['Data'] ?? [];
                $status = strtolower($data['InvoiceStatus'] ?? 'failed');
                $map = ['paid' => 'paid', 'pending' => 'pending', 'cancelled' => 'canceled', 'canceled' => 'canceled', 'failed' => 'failed'];

                return ['status' => $map[$status] ?? 'failed', 'reference' => (string) $inv, 'raw' => $data];
            }
        }

        return ['status' => 'failed', 'reference' => null, 'raw' => $request->all()];
    }
}
