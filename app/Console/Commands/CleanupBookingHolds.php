<?php

namespace App\Console\Commands;

use App\Services\HoldService;
use Illuminate\Console\Command;

class CleanupBookingHolds extends Command
{
    protected $signature = 'booking:cleanup-holds';

    protected $description = 'Delete/expire old booking holds';

    public function handle(HoldService $holds): int
    {
        $n = $holds->cleanupExpired();
        $this->info("Cleaned {$n} expired holds.");

        return self::SUCCESS;
    }
}
