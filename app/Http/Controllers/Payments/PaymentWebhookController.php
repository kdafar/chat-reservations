<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\CommerceOrder;
use App\Models\CommercePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $driver)
    {
        $payload = $request->all();

        // try to map identifiers from various providers (MyFatoorah/Tap/Stripe/etc.)
        $ids = $this->extractPaymentIdentifiers($payload);

        // 1) try by transaction_id / provider_payment_id
        $payment = CommercePayment::query()
            ->when($ids['transaction_id'], fn ($q) => $q->orWhere('transaction_id', $ids['transaction_id']))
            ->when($ids['provider_payment_id'], fn ($q) => $q->orWhere('provider_payment_id', $ids['provider_payment_id']))
            ->latest('id')
            ->first();

        // 2) try by order_code if not found
        if (! $payment && $ids['order_code']) {
            $order = CommerceOrder::where('code', $ids['order_code'])->first();
            if ($order) {
                $payment = CommercePayment::where('commerce_order_id', $order->id)->latest('id')->first();
            }
        }

        // if still not found, store nothing but acknowledge to avoid provider retries storm
        if (! $payment) {
            // you can log for later reconciliation:
            \Log::warning('Webhook: payment not matched', ['driver' => $driver, 'ids' => $ids, 'payload' => $payload]);

            return response()->json(['ok' => true, 'matched' => false], 202);
        }

        // map status
        $mapped = $this->mapStatus($payload, $driver);

        // update payment
        $update = [
            'status' => $mapped,
            'provider_payload' => $payload,
        ];
        if ($ids['provider_payment_id'] && ! $payment->provider_payment_id) {
            $update['provider_payment_id'] = $ids['provider_payment_id'];
        }
        if ($ids['transaction_id'] && ! $payment->transaction_id) {
            $update['transaction_id'] = $ids['transaction_id'];
        }
        if ($mapped === 'paid' && ! $payment->paid_at) {
            $update['paid_at'] = now();
        }

        $payment->update($update);

        // update order timestamps / status minimally
        $order = $payment->order;
        if ($order && $mapped === 'paid') {
            $order->update([
                'placed_at' => $order->placed_at ?: now(),
                // keep current status flow; don't force-change if partner already moved it forward
                // 'status' => $order->status === 'draft' ? 'placed' : $order->status,
            ]);
        }

        return response()->json(['ok' => true, 'matched' => true], 200);
    }

    private function extractPaymentIdentifiers(array $p): array
    {
        // normalize keys from various providers
        $providerPaymentId = Arr::get($p, 'PaymentId')
            ?? Arr::get($p, 'payment_id')
            ?? Arr::get($p, 'InvoiceId')
            ?? Arr::get($p, 'invoice_id')
            ?? Arr::get($p, 'id');

        $transactionId = Arr::get($p, 'TransactionId')
            ?? Arr::get($p, 'transaction_id')
            ?? Arr::get($p, 'trx_id')
            ?? Arr::get($p, 'data.id')
            ?? Arr::get($p, 'charge.id');

        $orderCode = Arr::get($p, 'order_code')
            ?? Arr::get($p, 'OrderCode')
            ?? Arr::get($p, 'metadata.order_code')
            ?? Arr::get($p, 'Data.CustomerReference')
            ?? Arr::get($p, 'CustomerReference');

        return [
            'provider_payment_id' => $providerPaymentId,
            'transaction_id' => $transactionId,
            'order_code' => $orderCode,
        ];
    }

    private function mapStatus(array $p, string $driver): string
    {
        // direct
        $status = strtolower((string) (Arr::get($p, 'status') ?? ''));
        if (in_array($status, ['paid', 'failed', 'refunded'], true)) {
            return $status;
        }

        // common variants
        $invoice = strtolower((string) (Arr::get($p, 'InvoiceStatus') ?? Arr::get($p, 'invoice_status') ?? ''));
        if ($invoice === 'paid') {
            return 'paid';
        }
        if ($invoice === 'failed') {
            return 'failed';
        }
        if ($invoice === 'refunded') {
            return 'refunded';
        }

        // booleans
        $success = Arr::get($p, 'is_paid') ?? Arr::get($p, 'IsSuccess') ?? Arr::get($p, 'success');
        if (is_bool($success) && $success === true) {
            return 'paid';
        }

        // default conservative
        return 'failed';
    }
}
