<?php

namespace App\Services\MenuSync;

use App\Services\MenuSync\Contracts\MenuProvider;
use App\Services\MenuSync\Providers\GenericJsonProvider;

class ProviderFactory
{
    public static function make(string $provider): MenuProvider
    {
        return match ($provider) {
            'generic_json' => new GenericJsonProvider,
            // 'vendor_x'   => new VendorXProvider(),
            default => new GenericJsonProvider,
        };
    }
}
