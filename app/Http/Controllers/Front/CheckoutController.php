<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\GatewayAccount;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function index(Request $request)
    {
        $resolved = $this->resolveBranchOrRedirect($request);
        if ($resolved instanceof RedirectResponse) {
            return $resolved;
        }
        $branch = $resolved;
        $cart = $this->cartService->getCartDto();

        if ($cart->isEmpty()) {
            return redirect()->route('cart.index')->with('toast.error', __('Your cart is empty.'));
        }

        $currentBlockId = (int) ($request->session()->get('block_id') ?? Arr::get($request->session()->get('address', []), 'block_id'));
        $availability = $this->resolveOrderTypeOptions($branch, $currentBlockId);

        if (empty($availability['delivery']) && empty($availability['pickup'])) {
            abort(403, 'Branch is currently unavailable for orders.');
        }

        $gateways = $this->resolveGatewayOptions($branch, $cart->currency);
        $allowedMethods = $gateways['codes'];
        $defaultPayment = $gateways['default'];
        $addresses = auth()->check() ? auth()->user()->addresses()->with(['city', 'block'])->latest()->get() : collect();

        return view('front.checkout.index', [
            'branch' => $branch,
            'cart' => $cart,
            'addresses' => $addresses,
            'orderType' => $availability['delivery'] ? 'delivery' : 'pickup',
            'allowedOrderTypes' => array_values(array_filter([
                $availability['delivery'] ? 'delivery' : null,
                $availability['pickup'] ? 'pickup' : null,
            ])),
            'deliveryFee' => $availability['delivery_fee'],
            'minOrderAmount' => $availability['min_order_amount'],
            'allowedMethods' => $allowedMethods,
            'defaultPayment' => $defaultPayment,
        ]);
    }

    public function store(Request $request)
    {
        $resolved = $this->resolveBranchOrRedirect($request);
        if ($resolved instanceof RedirectResponse) {
            return $resolved;
        }

        $branch = $resolved;
        $cart = $this->cartService->getCartDto();

        if ($cart->isEmpty()) {
            return back()->withErrors(['cart' => __('Your cart is empty.')])->withInput();
        }

        $serviceId = (int) (
            $request->integer('service_id')
            ?: optional($request->route('service'))->id
            ?: (int) $request->session()->get('service_id')
            ?: (int) $branch->services()->select('services.id')->first()?->id
        );

        if (! $serviceId || ! $branch->services()->whereKey($serviceId)->exists()) {
            return back()->withErrors(['service_id' => __('Selected service is not available for this branch.')])->withInput();
        }

        $currentBlockId = (int) ($request->session()->get('block_id')
            ?? \Illuminate\Support\Arr::get($request->session()->get('address', []), 'block_id'));

        $deliveryMeta = $this->effectiveDeliveryMeta($branch, $currentBlockId);

        $rules = [
            'order_type' => ['required', Rule::in(['delivery', 'pickup'])],
            'payment' => ['required', Rule::in(['online', 'cash'])],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
        ];

        if ($request->input('order_type') === 'delivery') {
            $mode = $request->input('address_mode', 'new');
            if (\Illuminate\Support\Str::startsWith($mode, 'saved:')) {
                $rules['address_mode'] = ['required', 'regex:/^saved:\d+$/'];
            } else {
                $rules = array_merge($rules, [
                    'address.city_id' => ['required', 'integer', 'exists:cities,id'],
                    'address.block_id' => ['required', 'integer', 'exists:blocks,id'],
                    'address.street' => ['required', 'string', 'max:190'],
                ]);
            }
        }

        $validated = $request->validate($rules);

        if (($validated['order_type'] ?? $request->input('order_type')) === 'delivery'
            && $deliveryMeta['min'] > 0
            && $cart->subtotal < (float) $deliveryMeta['min']) {
            return back()->withErrors(['cart' => __('Order subtotal does not meet the minimum order amount.')])->withInput();
        }

        $order = \DB::transaction(function () use ($branch, $serviceId, $validated, $cart, $deliveryMeta, $request) {
            $deliveryFee = ($validated['order_type'] === 'delivery') ? (float) $deliveryMeta['fee'] : 0.0;
            // Recalculate grand total based on the final, confirmed delivery fee.
            $grandTotal = max(0.0, ((float) $cart->subtotal - (float) $cart->discount) + $deliveryFee);

            $addressId = null;
            if ($validated['order_type'] === 'delivery') {
                if (\Illuminate\Support\Str::startsWith($request->input('address_mode', 'new'), 'saved:')) {
                    $addressId = (int) \Illuminate\Support\Str::after($request->input('address_mode'), 'saved:');
                } elseif (auth()->check() && $request->boolean('save_address')) {
                    $addr = (array) $request->input('address', []);
                    $newAddress = auth()->user()->addresses()->create([
                        'city_id' => $addr['city_id'] ?? null, 'block_id' => $addr['block_id'] ?? null,
                        'street' => $addr['street'] ?? null, 'building' => $addr['building'] ?? null,
                        'floor' => $addr['floor'] ?? null, 'notes' => $addr['notes'] ?? null,
                    ]);
                    $addressId = $newAddress->id;
                }
            }

            $order = \App\Models\CommerceOrder::create([
                'code' => 'ZAD-'.strtoupper(uniqid()),
                'service_id' => $serviceId,
                'partner_id' => $branch->partner_id,
                'branch_id' => $branch->id,
                'user_id' => auth()->id(),
                'type' => $validated['order_type'],
                'status' => 'placed',
                'address_id' => $addressId,
                'snapshot_partner' => Arr::only($branch->partner->toArray(), ['id', 'name']),
                'snapshot_branch' => Arr::only($branch->toArray(), ['id', 'name']),
                'snapshot_customer' => ['name' => $validated['name'], 'phone' => $validated['phone']],
                'items_total' => (float) $cart->subtotal,
                'discount_total' => (float) $cart->discount,
                'tax_total' => 0.0,
                'delivery_fee' => $deliveryFee,
                'grand_total' => $grandTotal,
                'currency' => $cart->currency,
                'placed_at' => now(),
            ]);

            foreach ($cart->items as $item) {
                $order->items()->create([
                    'menu_item_id' => $item['item_id'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'name' => $item['name'] ?? 'Unknown Item',
                    'quantity' => $item['qty'] ?? 1,
                    'unit_price' => $item['price'] ?? 0.0,
                    'subtotal' => $item['subtotal'] ?? 0.0,
                    'modifiers' => $item['modifiers'] ?? [],
                    'offer' => $item['offer'] ?? [],
                ]);
            }

            if (! empty($cart->coupon)) {
                session(['applied_coupon' => ['coupon_id' => $cart->coupon['id'] ?? null, 'coupon_code' => $cart->coupon['code'] ?? null, 'discount' => (float) $cart->discount]]);
            }

            session(['last_order_id' => $order->id, 'service_id' => $serviceId]);

            return $order;
        });

        // After the order is created, handle the payment.
        session(['should_clear_cart' => true]);

        if ($validated['payment'] === 'cash') {
            $cashAccount = $this->findCashAccount($branch, $cart->currency);
            if (! $cashAccount) {
                return back()->withErrors(['payment' => __('Cash on Delivery is currently not available.')])->withInput();
            }

            $order->payment()->create([
                'method' => 'cash',
                'status' => \App\Models\CommercePayment::S_PENDING,
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'gateway_account_id' => $cashAccount->id,
            ]);

            return redirect()->route('payment.success')->with('status', 'pending_cod');
        }

        // For "online" payments, hand off to the PaymentController.
        return app(\App\Http\Controllers\Front\PaymentController::class)->start(
            Request::create('', 'POST', ['order_id' => $order->id])
        );
    }

    protected function resolveBranchOrRedirect(Request $request): Branch|RedirectResponse
    {
        $cart = $this->cartService->getCartDto();
        $branchId = (int) ($cart->branchId ?? $request->integer('branch_id'));

        if (! $branchId) {
            return redirect()->route('home')->with('toast.error', __('Please choose a restaurant first.'));
        }

        $branch = Branch::with(['coverageBlocks', 'openingHours'])->find($branchId);

        if (! $branch || ! $branch->is_available) {
            return redirect()->route('cart.index')->with('toast.error', __('This branch is unavailable.'));
        }

        return $branch;
    }

    protected function resolveOrderTypeOptions(Branch $branch, ?int $blockId): array
    {
        $open = $branch->isOpenNow();
        $deliveryAllowed = $open && $branch->open_for_delivery && $this->branchCoversBlock($branch, $blockId);
        $pickupAllowed = $open && $branch->open_for_pickup;
        $meta = $this->effectiveDeliveryMeta($branch, $blockId);

        return [
            'open' => $open,
            'delivery' => $deliveryAllowed,
            'pickup' => $pickupAllowed,
            'delivery_fee' => $meta['fee'],
            'min_order_amount' => $meta['min'],
        ];
    }

    protected function branchCoversBlock(Branch $branch, ?int $blockId): bool
    {
        if (! $blockId) {
            return false;
        }

        return $branch->coverageBlocks()->where('blocks.id', $blockId)->wherePivot('is_active', 1)->exists();
    }

    protected function effectiveDeliveryMeta(Branch $branch, ?int $blockId): array
    {
        $pivot = null;
        if ($blockId) {
            $pivot = $branch->coverageBlocks()->where('blocks.id', $blockId)->first()?->pivot;
        }

        return [
            'fee' => $pivot->delivery_fee ?? (float) $branch->delivery_fee ?? 0.000,
            'min' => $pivot->min_order_amount ?? (float) $branch->min_order_amount ?? 0.000,
        ];
    }

    protected function resolveGatewayOptions(Branch $branch, string $currency = 'KWD'): array
    {
        $allAvailableAccounts = $this->getAllAvailableAccounts($branch, $currency);

        $hasOnline = $allAvailableAccounts->contains(fn ($acc) => $acc->gateway->driver !== 'cash');
        $hasCash = $allAvailableAccounts->contains(fn ($acc) => $acc->gateway->driver === 'cash');

        $allowed = [];
        if ($hasOnline) {
            $allowed[] = 'online';
        }
        if ($hasCash) {
            $allowed[] = 'cash';
        }

        $default = 'online';
        if (! in_array('online', $allowed) && in_array('cash', $allowed)) {
            $default = 'cash';
        }

        return ['codes' => $allowed, 'default' => $default, 'accounts' => []]; // Accounts not needed here
    }

    private function findCashAccount(Branch $branch, string $currency): ?GatewayAccount
    {
        return $this->getAllAvailableAccounts($branch, $currency)
            ->first(fn ($acc) => $acc->gateway->driver === 'cash');
    }

    private function getAllAvailableAccounts(Branch $branch, string $currency): \Illuminate\Support\Collection
    {
        return GatewayAccount::query()
            ->with('gateway')
            ->where('is_active', 1)
            ->where('currency', $currency)
            ->whereHas('gateway', fn ($q) => $q->where('is_active', 1))
            ->where(function ($query) use ($branch) {
                $query
                    ->where('owner_type', 'system')
                    ->orWhere(fn ($q) => $q->where('owner_type', 'partner')->where('partner_id', $branch->partner_id))
                    ->orWhere(fn ($q) => $q->where('owner_type', 'branch')->where('branch_id', $branch->id));
            })
            ->get();
    }

    protected function pickAccountForCode(array $map, string $code): ?GatewayAccount
    {
        return $map[$code] ?? null;
    }
}
