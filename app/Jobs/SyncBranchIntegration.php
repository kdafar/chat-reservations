<?php

namespace App\Jobs;

use App\Models\BranchIntegration;
use App\Models\BranchIntegrationLog;
use App\Services\MenuSync\Importer;
use App\Services\MenuSync\ProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class SyncBranchIntegration implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $integrationId) {}

    public function handle(): void
    {
        $int = BranchIntegration::with('branch')->find($this->integrationId);
        if (! $int || ! $int->enabled) {
            return;
        }

        $log = BranchIntegrationLog::create([
            'branch_integration_id' => $int->id,
            'started_at' => now(),
            'status' => 'running',
        ]);

        $providerKey = $int->provider;
        $provider = ProviderFactory::make($providerKey);

        try {
            $en = $provider->fetch($int->api_base_url, $int->api_key, 'en');
            $ar = $provider->fetch($int->api_base_url, $int->api_key, 'ar');

            app(Importer::class, ['source' => $providerKey])->run($int->branch, $en, $ar);

            $log->update([
                'finished_at' => now(),
                'status' => 'success',
                'categories' => is_countable($en) ? count($en) : 0,
                'items' => is_array($en) ? collect($en)->sum(fn ($c) => count($c['items'] ?? [])) : 0,
                'message' => 'Sync completed',
            ]);

            \Log::info('Branch integration synced', ['integration' => $int->id, 'branch' => $int->branch_id]);
        } catch (\Throwable $e) {
            $log->update([
                'finished_at' => now(),
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);

            \Log::error('Branch integration sync failed', [
                'integration' => $int->id,
                'branch' => $int->branch_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
