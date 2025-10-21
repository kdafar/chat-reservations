<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CartCouponController extends Controller
{
    public function apply(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:64']]);
        $code = mb_strtoupper(trim($request->string('code')));

        /** @var \App\Services\CartService $cartSvc */
        $cartSvc = app(\App\Services\CartService::class);
        $dto = $cartSvc->getCartDto(); // your DTO (has branchId, subtotal, maybe lines)

        // Get Branch model safely (your DTO has branchId, not branch)
        $branch = null;
        if (property_exists($dto, 'branch') && $dto->branch instanceof \App\Models\Branch) {
            $branch = $dto->branch;
        } elseif (! empty($dto->branchId)) {
            $branch = \App\Models\Branch::select('id')->find($dto->branchId);
        } else {
            // last resort: try cart model if your service exposes it
            if (method_exists($cartSvc, 'getCartModel')) {
                $cartModel = $cartSvc->getCartModel();
                if ($cartModel && $cartModel->branch_id) {
                    $branch = \App\Models\Branch::select('id')->find($cartModel->branch_id);
                }
            }
        }

        $orderType = $request->string('order_type')->toString() ?: session('checkout.order_type', 'delivery');
        $subtotal = (float) ($dto->subtotal ?? 0);

        /** @var \App\Models\Coupon|null $coupon */
        $coupon = \App\Models\Coupon::query()
            ->with(['branches', 'menus', 'sections', 'items']) // ok even if you won’t use scopes now
            ->whereRaw('UPPER(code) = ?', [$code])
            ->active()
            ->first();

        if (! $coupon) {
            return response()->json(['message' => __('front.coupon_invalid')], 422);
        }
        if (! $coupon->appliesToBranch($branch)) {
            return response()->json(['message' => __('front.coupon_invalid')], 422);
        }
        if (! $coupon->allowsOrderType($orderType)) {
            return response()->json(['message' => __('front.coupon_invalid')], 422);
        }
        if (! $coupon->passesMin($subtotal)) {
            return response()->json(['message' => __('front.min_order_amount')], 422);
        }

        // Usage caps
        $userId = optional($request->user())->id;
        $phone = $request->string('phone_e164')->toString() ?: session('checkout.phone');

        $globalLeft = $coupon->remainingGlobalUses();
        if ($globalLeft !== null && $globalLeft <= 0) {
            return response()->json(['message' => __('front.coupon_invalid')], 422);
        }
        $perLeft = $coupon->remainingUsesFor($userId, $phone);
        if ($perLeft !== null && $perLeft <= 0) {
            return response()->json(['message' => __('front.coupon_invalid')], 422);
        }

        // OPTIONAL: only if your Coupon supports dynamic compute + DTO exposes lines
        if (method_exists($coupon, 'computeDiscount')) {
            $lines = property_exists($dto, 'lines') ? (array) $dto->lines : [];
            $calc = $coupon->computeDiscount($lines, $subtotal, $orderType);
            if (($calc['discount'] ?? 0) <= 0) {
                return response()->json(['message' => __('front.coupon_invalid')], 422);
            }
        }

        // Save coupon in session; discount will be applied in getCartDto()
        $cartSvc->setCoupon($coupon);

        return response()->json(['message' => __('front.coupon_applied')]);
    }

    public function remove()
    {
        app(\App\Services\CartService::class)->clearCoupon();

        return response()->json(['message' => __('front.coupon_removed')]);
    }
}
