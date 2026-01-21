<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class QueueSmokeTestJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        \Log::info('queue-ok', ['ts' => now()->toDateTimeString()]);
        \Storage::append('queue_ping.txt', now()->toDateTimeString());
    }
}
