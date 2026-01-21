<?php

namespace App\Console\Commands;

use App\Models\WhatsappSession;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CleanupWhatsappSessions extends Command
{
    protected $signature = 'wa:sessions:expire {--hours=12 : Idle hours before expiring}';

    protected $description = 'Expire idle WhatsApp sessions';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = Carbon::now(config('app.timezone'))->subHours($hours);

        // This query correctly uses the string 'expired' which fixes the
        // "Data truncated for column 'status'" error.
        $n = WhatsappSession::query()
            ->whereNotNull('last_interacted_at')
            ->where('last_interacted_at', '<', $cutoff)
            ->where('status', '!=', 'expired')   // don’t re-expire
            ->update([
                'status' => 'expired',       // string, not DB::raw()
                'updated_at' => now(),
            ]);

        $this->info("Expired {$n} sessions last touched before {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
