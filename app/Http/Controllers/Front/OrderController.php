<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\CommerceOrder;
use App\Services\CartService;
use App\Services\Payments\PaymentAccountSelector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function __construct(
        private PaymentAccountSelector $selector,
        private CartService $cartService
    ) {}

    public function index(Request $request)
    {
        $orders = CommerceOrder::query()
            ->where('user_id', auth()->id())
            ->latest('id')
            ->paginate(20);

        return view('front.account.orders', compact('orders'));
    }

    public function show(CommerceOrder $order)
    {
        $this->authorizeView($order);

        return view('front.orders.show', compact('order'));
    }

    public function status(CommerceOrder $order): JsonResponse
    {
        $this->authorizeView($order);

        return response()->json([
            'status' => $order->status,
            'payment_status' => optional($order->latestPayment)->status,
            'updated_at' => $order->updated_at?->toIso8601String(),
        ]);
    }

    public function pay(CommerceOrder $order)
    {
        $this->authorizeView($order);

        $payment = $order->latestPayment;
        abort_unless($payment && $payment->method !== 'cod' && $payment->status !== 'paid', 422, 'Payment not payable.');

        return app(\App\Http\Controllers\Front\PaymentController::class)->start(
            request()->duplicate([], [
                'order_id' => $order->id,
                'account_id' => $payment->gateway_account_id,
            ])
        );
    }

    public function cancel(CommerceOrder $order)
    {
        $this->authorizeView($order);
        abort_unless($order->is_cancelable, 422, 'Order can’t be cancelled now.');

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'cancelled']);
            if ($p = $order->latestPayment) {
                if ($p->status !== 'paid') {
                    $p->update(['status' => 'void']);
                }
            }
        });

        return back()->with('success', __('Order cancelled.'));
    }

    public function reorder(CommerceOrder $order)
    {
        $this->authorizeView($order);
        Log::debug("--- Starting reorder for Order #{$order->id} ---");

        $branch = $order->branch;
        if (! $branch || ! $branch->is_available) {
            Log::warning("Reorder failed: Branch #{$order->branch_id} is currently unavailable.");

            return redirect()->route('home')->with('toast.error', __('This branch is currently unavailable.'));
        }
        Log::debug("Branch '{$branch->name}' is available. Clearing the cart.");

        $this->cartService->clear();

        foreach ($order->items()->with('modifiers', 'menuItem')->get() as $line) {
            Log::debug("Processing order item line #{$line->id} for original MenuItem ID: {$line->menu_item_id}");

            $menuItem = $line->menuItem;
            if (! $menuItem) {
                Log::warning("MenuItem with ID {$line->menu_item_id} not found or is inactive. Skipping line #{$line->id}.");

                continue;
            }
            Log::debug("Found MenuItem: '{$menuItem->name}' (ID: {$menuItem->id})");

            $modifiers = $line->modifiers->mapWithKeys(function ($mod) {
                // This structure matches what CartService expects
                return [$mod->modifier_group_id => $mod->modifier_option_id];
            })->all();

            Log::debug('Extracted modifiers for reorder:', $modifiers);
            Log::debug('Calling cartService->addItem with:', [
                'branch' => $branch->id,
                'item' => $menuItem->id,
                'qty' => $line->quantity,
                'mods' => $modifiers,
                'note' => $line->note,
                'offer' => $line->offer,
            ]);

            $this->cartService->addItem($branch, $menuItem, $line->quantity, $modifiers, true, $line->note, $line->offer);
        }

        Log::debug('Finished reorder process. Redirecting to cart.');

        return redirect()->route('cart.index')->with('toast.success', __('Items from your previous order have been added to your cart.'));
    }

    private function authorizeView(CommerceOrder $order): void
    {
        if (! auth()->check()) {
            abort_unless((int) session('last_order_id') === (int) $order->id, 403);

            return;
        }
        abort_if($order->user_id && $order->user_id !== auth()->id(), 403);
    }
}
