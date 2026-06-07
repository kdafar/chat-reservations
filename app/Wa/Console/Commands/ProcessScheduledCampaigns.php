<?php

namespace App\Wa\Console\Commands;

use App\Wa\Hub\Models\PromotionalCampaign;
use App\Wa\Jobs\SendPromotionalCampaignMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches campaigns whose scheduled_at has arrived. Ported from the source
 * repo; scheduled every minute by the clinic scheduler.
 */
class ProcessScheduledCampaigns extends Command
{
    protected $signature = 'wa:campaigns:process-scheduled';

    protected $description = 'Queue messages for scheduled WhatsApp campaigns that have reached their start time.';

    public function handle(): int
    {
        $now = now();

        $campaigns = PromotionalCampaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->get();

        if ($campaigns->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($campaigns as $campaign) {
            try {
                $campaign->update(['status' => 'sending', 'sent_at' => $campaign->sent_at ?? now()]);

                $count = 0;
                $campaign->recipients()->where('status', 'pending')->chunkById(500, function ($recipients) use ($campaign, &$count) {
                    foreach ($recipients as $recipient) {
                        dispatch(new SendPromotionalCampaignMessage($campaign->id, $recipient->id));
                        $count++;
                    }
                });

                $this->info("Campaign {$campaign->id}: queued {$count} messages.");
                Log::info("wa:campaigns:process-scheduled queued {$count} for campaign {$campaign->id}");
            } catch (\Throwable $e) {
                $this->error("Campaign {$campaign->id} failed: ".$e->getMessage());
                Log::error('wa:campaigns:process-scheduled error', ['campaign' => $campaign->id, 'error' => $e->getMessage()]);
            }
        }

        return self::SUCCESS;
    }
}
