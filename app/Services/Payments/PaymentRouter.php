<?php

namespace App\Services\Payments;

use App\Models\CommerceOrder;
use App\Models\CommercePaymentPolicy;
use App\Models\GatewayAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaymentRouter
{
    public function pickAccount(CommerceOrder $order): ?GatewayAccount
    {
        Log::debug('--- PaymentRouter: Picking Account ---', ['order_id' => $order->id, 'total' => $order->grand_total]);

        $key = sprintf('paypol:%s:%s:%s', $order->partner_id, $order->service_id, $order->branch_id);

        $policies = Cache::remember($key, 60, function () use ($order) {
            return CommercePaymentPolicy::query()
                ->where('is_enabled', true)
                ->where(function ($q) use ($order) {
                    $q->whereNull('partner_id')->orWhere('partner_id', $order->partner_id);
                })
                ->where(function ($q) use ($order) {
                    $q->whereNull('service_id')->orWhere('service_id', $order->service_id);
                })
                ->where(function ($q) use ($order) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $order->branch_id);
                })
                ->orderBy('priority')
                ->get();
        });

        Log::debug("Found {$policies->count()} enabled policies in scope.", ['policy_ids' => $policies->pluck('id')->all()]);

        foreach ($policies as $p) {
            if (! $this->matches($p, $order)) {
                continue;
            }

            Log::debug("Policy #{$p->id} ('{$p->name}') is a match. Executing its action.");
            $act = $p->action ?? [];

            // a) hard-pin a specific account
            if (! empty($act['gateway_account_id'])) {
                Log::debug("Action: Use explicit GatewayAccount ID: {$act['gateway_account_id']}");

                return GatewayAccount::with('gateway')
                    ->whereKey($act['gateway_account_id'])
                    ->where('is_active', true)
                    ->first();
            }

            // b) choose by driver + owner preference chain
            if (! empty($act['driver'])) {
                Log::debug("Action: Find account by driver '{$act['driver']}'.");
                $prefer = data_get($act, 'owner_preference.*.owner', ['branch', 'partner', 'service', 'system']);

                foreach ($prefer as $owner) {
                    $acc = $this->firstActiveAccountFor($act['driver'], $owner, $order);
                    if ($acc) {
                        Log::debug("Found account #{$acc->id} for driver '{$act['driver']}' with owner '{$owner}'. Selecting it.");

                        return $acc;
                    }
                }

                if (! empty($act['allow_fallback'])) {
                    Log::warning("No specific account found for driver '{$act['driver']}'. Using fallback.");

                    return GatewayAccount::with('gateway')
                        ->where('is_active', true)
                        ->orderByDesc('is_default')
                        ->first();
                }
            }
        }

        Log::debug('No policies matched. Using default fallback logic.');
        // no policy matched → sane default: branch→partner→service→system
        foreach (['branch', 'partner', 'service', 'system'] as $owner) {
            $acc = $this->firstActiveAccountFor(null, $owner, $order);
            if ($acc) {
                Log::debug("Fallback found default account #{$acc->id} with owner '{$owner}'.");

                return $acc;
            }
        }

        Log::error("No payment account could be found for Order #{$order->id}.");

        return null;
    }

    protected function firstActiveAccountFor(?string $driver, string $owner, CommerceOrder $order): ?GatewayAccount
    {
        $q = GatewayAccount::query()->with('gateway')
            ->where('is_active', true)
            ->where('currency', $order->currency);

        if ($driver) {
            $q->whereHas('gateway', fn ($g) => $g->where('driver', $driver));
        }

        return match ($owner) {
            'branch' => $q->where('owner_type', 'branch')->where('branch_id', $order->branch_id)->orderByDesc('is_default')->first(),
            'partner' => $q->where('owner_type', 'partner')->where('partner_id', $order->partner_id)->orderByDesc('is_default')->first(),
            'service' => $q->where('owner_type', 'service')->where('service_id', $order->service_id)->orderByDesc('is_default')->first(),
            default => $q->where('owner_type', 'system')->orderByDesc('is_default')->first(),
        };
    }

    protected function matches(CommercePaymentPolicy $policy, CommerceOrder $order): bool
    {
        $c = $policy->conditions ?? [];
        $total = (float) $order->grand_total;

        // --- IMPROVED LOGGING AND BUG FIXES ---

        // currency
        if (! empty($c['currency'])) { // Use !empty to handle cases where an empty array is saved
            Log::debug("Policy #{$policy->id} currency check: Order currency='{$order->currency}', Policy requires=".json_encode($c['currency']));
            $ok = is_array($c['currency'])
                ? in_array($order->currency, $c['currency'], true)
                : strcasecmp($order->currency, $c['currency']) === 0;
            if (! $ok) {
                return false;
            }
        }

        // type
        if (! empty($c['order_type']) && ! in_array($order->type, (array) $c['order_type'], true)) {
            Log::debug("Policy #{$policy->id} check failed: Order type='{$order->type}', Policy requires=".json_encode($c['order_type']));

            return false;
        }

        // totals
        if (isset($c['min_total']) && $total < (float) $c['min_total']) {
            Log::debug("Policy #{$policy->id} check failed: total {$total} is less than min_total ".$c['min_total']);

            return false;
        }
        if (isset($c['max_total']) && $total >= (float) $c['max_total']) {
            Log::debug("Policy #{$policy->id} check failed: total {$total} is not less than max_total ".$c['max_total']);

            return false;
        }

        // day/time windows (optional)
        $now = now();
        if (! empty($c['days_of_week']) && ! in_array((int) $now->dayOfWeek, (array) $c['days_of_week'], true)) {
            Log::debug("Policy #{$policy->id} check failed: day of week mismatch.");

            return false;
        }
        if (isset($c['time_between']) && is_array($c['time_between']) && count($c['time_between']) === 2) {
            [$from, $to] = $c['time_between'];
            if ($from && $to) {
                $ok = $now->between($now->copy()->setTimeFromTimeString($from), $now->copy()->setTimeFromTimeString($to));
                if (! $ok) {
                    Log::debug("Policy #{$policy->id} check failed: time window mismatch.");

                    return false;
                }
            }
        }

        return true;
    }
}
