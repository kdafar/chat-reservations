<?php

namespace App\Console\Commands;

use App\Models\RestaurantTable;
use Illuminate\Console\Command;

class FreeStuckTables extends Command
{
    protected $signature = 'tables:free-stuck {--hours=6}';

    protected $description = 'Free tables stuck as occupied when there is no active seated booking updated recently';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        // Option A: naive free-all-occupied (use if you don't want lookup)
        $count = RestaurantTable::where('status', 'occupied')
            ->whereDoesntHave('bookings', function ($q) use ($hours) {
                $q->where('status', 'seated')
                    ->where('updated_at', '>=', now()->subHours($hours));
            })
            ->update(['status' => 'available']);

        $this->info("Freed {$count} tables.");

        return self::SUCCESS;
    }
}
