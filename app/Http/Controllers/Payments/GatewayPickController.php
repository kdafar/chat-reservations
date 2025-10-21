<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentAccountSelector;
use Illuminate\Http\Request;

class GatewayPickController extends Controller
{
    public function __construct(private PaymentAccountSelector $selector) {}

    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'driver' => ['nullable', 'string'],           // defaults to myfatoorah
            'preference' => ['nullable', 'in:partner,system,cash'],
        ]);

        $result = $this->selector->pick(
            partnerId: (int) $data['partner_id'],
            driver: $data['driver'] ?? 'myfatoorah',
            preference: $data['preference'] ?? 'partner'
        );

        return response()->json($result);
    }
}
