<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Promotions\PromotionEngine;
use Illuminate\Http\Request;

class CartEvaluateController extends Controller
{
    public function __invoke(Request $request, PromotionEngine $engine)
    {
        $cart = $request->validate([
            'service_id' => 'nullable|integer',
            'partner_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
            'order_type' => 'nullable|string',
            'channel' => 'nullable|string',
            'user_id' => 'nullable|integer',
            'subtotal' => 'nullable|numeric',
            'delivery_fee' => 'nullable|numeric',
            'items' => 'required|array|min:1',
            'items.*.key' => 'nullable|string',
            'items.*.item_id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.category_ids' => 'nullable|array',
        ]);

        $cart['channel'] = $cart['channel'] ?? 'web';

        $result = $engine->evaluate($cart);

        return response()->json($result);
    }
}
