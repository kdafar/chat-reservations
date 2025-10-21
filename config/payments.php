<?php

return [
    'drivers' => [
        'myfatoorah' => \App\Services\Payments\Drivers\MyFatoorahGateway::class,
        'tap' => \App\Services\Payments\Drivers\TapGateway::class,
        'stripe' => \App\Services\Payments\Drivers\StripeGateway::class,
        'cash' => \App\Services\Payments\Drivers\CashGateway::class,
    ],

    // Global success/error routes
    'return' => [
        'success' => 'payment.success',
        'error' => 'payment.error',
    ],
];
