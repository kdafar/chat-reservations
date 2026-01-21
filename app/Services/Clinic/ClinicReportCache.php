<?php

namespace App\Services\Clinic;

use Illuminate\Support\Facades\Cache;

class ClinicReportCache
{
    public static function remember(string $key, int $seconds, callable $callback)
    {
        $tenantKey = self::tenantKeyPrefix();

        return Cache::remember($tenantKey.':'.$key, $seconds, $callback);
    }

    protected static function tenantKeyPrefix(): string
    {
        // Spatie multitenancy: keep defensive.
        try {
            $tenant = function_exists('tenant') ? tenant() : null;
            $tenantId = is_object($tenant) ? ($tenant->id ?? null) : null;

            return 'clinic_reports:'.($tenantId ? ('tenant_'.$tenantId) : 'no_tenant');
        } catch (\Throwable $e) {
            return 'clinic_reports:no_tenant';
        }
    }
}
