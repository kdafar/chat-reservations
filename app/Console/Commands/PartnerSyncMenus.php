<?php

namespace App\Console\Commands;

use App\Models\BranchIntegration;
use App\Services\MenuSync\Importer;
use App\Services\MenuSync\ProviderFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PartnerSyncMenus extends Command
{
    protected $signature = 'partner:menus:sync 
        {--partner= : Partner ID}
        {--branch= : Branch ID}
        {--provider= : Override provider key}
        {--dry : Dry-run (no DB writes)}';

    protected $description = 'Sync menus from external APIs into Branch -> Menu -> Section -> Item (with modifiers)';

    public function handle(): int
    {
        $partnerId = $this->option('partner') ? (int) $this->option('partner') : null;
        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;
        $dry = (bool) $this->option('dry');

        $query = BranchIntegration::query()->where('enabled', true)
            ->when($partnerId, fn ($q) => $q->whereHas('branch', fn ($b) => $b->where('partner_id', $partnerId)))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $integrations = $query->with('branch')->get();
        if ($integrations->isEmpty()) {
            $this->warn('No integrations found for the given filters.');

            return 0;
        }

        $bar = $this->output->createProgressBar($integrations->count());
        $bar->start();

        foreach ($integrations as $int) {
            $bar->setMessage("Branch #{$int->branch_id} ({$int->provider})");
            try {
                $providerKey = $this->option('provider') ?: $int->provider;
                $provider = ProviderFactory::make($providerKey);

                $en = $provider->fetch($int->api_base_url, $int->api_key, 'en');
                $ar = $provider->fetch($int->api_base_url, $int->api_key, 'ar');

                if ($dry) {
                    $this->line("\n[DRY] fetched EN=".count($en).' AR='.count($ar)." for branch {$int->branch_id}");
                } else {
                    app(Importer::class, ['source' => $providerKey])
                        ->run($int->branch, $en, $ar);
                }
            } catch (Throwable $e) {
                $this->error("\nSync error for branch {$int->branch_id}: ".$e->getMessage());
                Log::error('Partner sync failed', ['branch' => $int->branch_id, 'e' => $e]);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->info("\nDone.");

        return 0;
    }
}
