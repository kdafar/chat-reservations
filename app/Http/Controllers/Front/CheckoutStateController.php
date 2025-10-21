<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CheckoutStateController extends Controller
{
    public function saveOrderType(Request $request)
    {
        $type = $request->validate([
            'order_type' => ['required', 'in:delivery,pickup'],
        ])['order_type'];

        session(['checkout.order_type' => $type]);

        return response()->noContent();
    }

    public function saveAddress(Request $request)
    {
        $addr = $request->validate([
            'address.state_id' => ['nullable', 'integer'],
            'address.city_id' => ['nullable', 'integer'],
            'address.block_id' => ['nullable', 'integer'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.building' => ['nullable', 'string', 'max:255'],
            'address.floor' => ['nullable', 'string', 'max:255'],
            'address.notes' => ['nullable', 'string', 'max:1000'],
        ])['address'] ?? [];

        session(['checkout.address' => $addr]);

        return response()->noContent();
    }

    public function savePhone(Request $request)
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:64'],
            'phone_e164' => ['nullable', 'string', 'max:64'],
        ]);

        session([
            'checkout.phone' => $data['phone_e164'] ?? $data['phone'] ?? null,
            'checkout.phone_raw' => $data['phone'] ?? null,
        ]);

        return response()->noContent();
    }
}
