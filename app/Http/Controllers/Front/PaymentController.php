<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\CommerceOrder;
use App\Models\CommercePayment;
use App\Models\CouponRedemption;
use App\Models\GatewayAccount;
use App\Services\Payments\GatewayManager;
use App\Services\Payments\PaymentRouter;
use Illuminate\Http\Request;
use Throwable;

class PaymentController extends Controller
{
    // Make $manager optional so calling the method directly won't break DI
    public function start(Request $request, ?GatewayManager $manager = null, ?PaymentRouter $router = null)
    {
        $manager ??= app(GatewayManager::class);
        $router ??= app(PaymentRouter::class);

        $orderId = (int) ($request->get('order_id') ?? session('order_id') ?? session('last_order_id'));
        $accountId = (int) $request->get('account_id');

        $order = CommerceOrder::with('payment')->findOrFail($orderId);
        $account = $accountId
            ? GatewayAccount::with('gateway')->findOrFail($accountId)
            : $router->pickAccount($order);

        if (! $account || ! $account->is_active) {
            return back()->withErrors(['payment' => __('No available payment account.')]);
        }

        \Log::info('PAYMENTS: selected account', [
            'id' => $account->id,
            'driver' => $account->gateway->driver,
            'owner' => $account->owner_type,
            'branch_id' => $account->branch_id,
            'curr' => $account->currency,
            'has_api' => is_array($account->credentials) && ! empty($account->credentials['api_key']),
            'mode' => $account->credentials['mode'] ?? null,
        ]);

        $payment = $order->payment ?: new CommercePayment(['commerce_order_id' => $order->id]);
        $payment->fill([
            'method' => $account->gateway->driver === 'cash' ? 'cash' : 'online',
            'status' => CommercePayment::S_PENDING,
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'gateway_account_id' => $account->id,
        ])->save();

        // COD shortcut
        if ($account->gateway->driver === 'cash') {
            session(['order_id' => $order->id, 'last_order_id' => $order->id]);

            return redirect()->route(config('payments.return.success'))->with('status', 'pending_cod');
        }

        try {
            $driver = $manager->driver($account);
            $res = $driver->createCheckout($order, $account, []);

            $payment->forceFill([
                'provider_payment_id' => $res['reference'] ?? null,      // MF InvoiceId / Tap charge_id / Stripe session_id
                'provider_payload' => $res['providerPayload'] ?? null,
            ])->save();

            session(['order_id' => $order->id, 'last_order_id' => $order->id]);

            return redirect()->away($res['redirectUrl']);
        } catch (Throwable $e) {
            report($e);
            $payment->forceFill(['status' => CommercePayment::S_FAILED])->save();

            return redirect()->route(config('payments.return.error'))
                ->with('error', __('Unable to start payment. Please try another method.'));
        }
    }

    // /payments/callback/{driver}
    public function callback(string $driver, Request $request, ?GatewayManager $manager = null)
    {
        $manager ??= app(GatewayManager::class);

        $orderId = (int) (session('order_id') ?? session('last_order_id') ?? $request->get('order_id'));

        /** @var CommercePayment $payment */
        $payment = CommercePayment::where('commerce_order_id', $orderId)->latest('id')->firstOrFail();
        $account = GatewayAccount::query()->with('gateway')->findOrFail($payment->gateway_account_id);

        if ($account->gateway->driver !== $driver) {
            return redirect()->route(config('payments.return.error'))->with('error', __('Invalid gateway.'));
        }

        $res = $manager->driver($account)->handleCallback($request, $account);

        // try to extract a transaction id per provider
        $txId = $payment->transaction_id
            ?? data_get($res, 'raw.InvoiceTransactions.0.PaymentId')   // MyFatoorah
            ?? data_get($res, 'raw.PaymentId')                         // MyFatoorah alt
            ?? data_get($res, 'raw.reference.transaction')             // Tap
            ?? data_get($res, 'raw.payment_intent')                    // Stripe
            ?? null;

        $payment->forceFill([
            'provider_payment_id' => $res['reference'] ?? $payment->provider_payment_id,
            'transaction_id' => $txId,
            'provider_payload' => $res['raw'] ?? $payment->provider_payload,
        ])->save();

        switch ($res['status']) {
            case 'paid':
                $payment->markPaid($res['raw'] ?? null);

                try {
                    $this->recordCouponRedemption($payment->order);
                } catch (\Throwable $e) {
                    report($e);
                }

                try {
                    app(\App\Services\CartService::class)->clear();
                } catch (\Throwable $e) {
                    report($e); // non-fatal
                }
                session()->forget('should_clear_cart');      // in case it was set
                session(['last_order_id' => $payment->commerce_order_id]);

                return redirect()->route(config('payments.return.success'))->with('status', 'paid');

            case 'authorized':
            case 'pending':
                $payment->forceFill(['status' => CommercePayment::S_PENDING])->save();

                return redirect()->route(config('payments.return.success'))->with('status', 'pending');

            case 'canceled':
            case 'failed':
            default:
                $payment->forceFill(['status' => CommercePayment::S_FAILED])->save();

                return redirect()->route(config('payments.return.error'))->with('error', __('Payment failed or canceled.'));
        }
    }

    // /payments/webhook/{driver}/{account}
    public function webhook(string $driver, int $accountId, Request $request, ?GatewayManager $manager = null)
    {
        $manager ??= app(GatewayManager::class);

        $account = GatewayAccount::query()->with('gateway')->findOrFail($accountId);
        abort_if($account->gateway->driver !== $driver, 404);

        $res = $manager->driver($account)->handleWebhook($request, $account);

        // Try resolve by provider reference first
        $payment = CommercePayment::where('gateway_account_id', $account->id)
            ->when(($ref = $res['reference'] ?? null), fn ($q) => $q->where('provider_payment_id', $ref))
            ->latest('id')->first();

        if ($payment) {
            $txId = $payment->transaction_id
                ?? data_get($res, 'raw.InvoiceTransactions.0.PaymentId')
                ?? data_get($res, 'raw.PaymentId')
                ?? data_get($res, 'raw.reference.transaction')
                ?? data_get($res, 'raw.payment_intent')
                ?? null;

            $payment->forceFill([
                'transaction_id' => $txId,
                'provider_payload' => $res['raw'] ?? $payment->provider_payload,
            ])->save();

            if ($res['status'] === 'paid') {
                $payment->markPaid($res['raw'] ?? null);
            } elseif (in_array($res['status'], ['failed', 'canceled'], true)) {
                $payment->forceFill(['status' => CommercePayment::S_FAILED])->save();
            }
        }

        return response()->json(['ok' => true]);
    }

    public function success()
    {
        $orderId = session('order_id') ?? session('last_order_id');
        if (! $orderId) {
            return redirect()->route('home');
        }

        $order = \App\Models\CommerceOrder::find($orderId);

        if (session('should_clear_cart', false)) {
            try {
                $this->recordCouponRedemption($order);
            } catch (\Throwable $e) {
                report($e);
            }
            try {
                app(\App\Services\CartService::class)->clear();
            } catch (\Throwable $e) {
                report($e);
            }
            session()->forget('should_clear_cart');
        }

        return view('payment-success', ['order' => $order]);
    }

    public function error()
    {
        return view('payment-error');
    }

    protected function recordCouponRedemption(\App\Models\CommerceOrder $order): void
    {
        $c = session('applied_coupon');
        if (! $c || empty($c['coupon_id']) || $order->discount_total <= 0) {
            return;
        }

        CouponRedemption::firstOrCreate(
            ['coupon_id' => $c['coupon_id'], 'order_id' => $order->id],
            [
                'user_id' => auth()->id(),
                'phone' => $order->snapshot_customer['phone'] ?? null,
                'discount_applied' => $order->discount_total,
            ]
        );
    }
}
