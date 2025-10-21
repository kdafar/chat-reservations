<?php

namespace App\Services\Payments;

use App\Models\GatewayAccount;
use App\Services\Payments\Contracts\PaymentGateway;
use InvalidArgumentException;

class GatewayManager
{
    public function driver(GatewayAccount $account): PaymentGateway
    {
        $driver = $account->gateway->driver ?? null;
        $map = config('payments.drivers', []);
        $class = $map[$driver] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("Unsupported gateway driver: {$driver}");
        }

        return app($class);
    }

    public function successUrl(): string
    {
        return route(config('payments.return.success'));
    }

    public function errorUrl(): string
    {
        return route(config('payments.return.error'));
    }
}
