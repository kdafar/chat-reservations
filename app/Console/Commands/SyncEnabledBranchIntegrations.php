<?php

namespace App\Console\Commands;

use App\Jobs\SyncBranchIntegration;
use App\Models\BranchIntegration;
use Illuminate\Console\Command;

class SyncEnabledBranchIntegrations extends Command
{
    protected $signature = 'integrations:sync-enabled {--queue=default} {--chunk=100}';

    protected $description = 'Dispatch SyncBranchIntegration for all enabled integrations';

    public function handle(): int
    {
        $queue = (string) $this->option('queue');
        $chunk = (int) $this->option('chunk');

        BranchIntegration::query()
            ->where('enabled', true)
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use ($queue) {
                foreach ($rows as $r) {
                    SyncBranchIntegration::dispatch($r->id)->onQueue($queue);
                }
            });

        $this->info('Queued sync for enabled integrations.');

        return self::SUCCESS;
    }
}
