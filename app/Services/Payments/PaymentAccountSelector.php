<?php

namespace App\Services\Payments;

use App\Models\GatewayAccount;

class PaymentAccountSelector
{
    /**
     * Pick a gateway account based on partner & driver.
     *
     * @param  int  $partnerId  Partner ID
     * @param  string  $driver  e.g. 'myfatoorah', 'tap', 'stripe'
     * @param  string|null  $preference  'partner' | 'system' | 'cash' | null
     * @return array{method:string, account_id:int|null}
     */
    public function pick(int $partnerId, string $driver = 'myfatoorah', ?string $preference = 'partner'): array
    {
        $pref = $preference ?: 'partner';

        if ($pref === 'cash') {
            return ['method' => 'cash', 'account_id' => null];
        }

        // Partner default active
        $partnerAccount = GatewayAccount::query()
            ->whereHas('gateway', fn ($q) => $q->where('driver', $driver))
            ->where('owner_type', 'partner')
            ->where('partner_id', $partnerId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        // System default active
        $systemAccount = GatewayAccount::query()
            ->whereHas('gateway', fn ($q) => $q->where('driver', $driver))
            ->where('owner_type', 'system')
            ->whereNull('partner_id')
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if ($pref === 'partner') {
            $use = $partnerAccount ?: $systemAccount;
        } else { // $pref === 'system'
            $use = $systemAccount ?: $partnerAccount;
        }

        if (! $use) {
            // fallback to cash if nothing configured
            return ['method' => 'cash', 'account_id' => null];
        }

        return ['method' => 'online', 'account_id' => $use->id];
    }
}
