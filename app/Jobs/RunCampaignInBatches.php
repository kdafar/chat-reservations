<?php

namespace App\Jobs;

use App\Models\BulkInviteCampaign;
use App\Models\BulkInviteCampaignRecipient;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunCampaignInBatches implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $campaignId)
    {
        $this->onQueue('campaigns');
    }

    public function handle(): void
    {
        // Global pause
        if ((bool) Settings::get('wa.campaigns.sending_paused', false)) {
            Log::info('CampaignRunner: paused by settings');
            $this->release(300);

            return;
        }

        $mps = max(1, (int) Settings::get('wa.campaigns.mps', 5));          // messages per second
        $batchSize = max(1, (int) Settings::get('wa.campaigns.batch_size', 200)); // recipients per slice
        $quietStart = (int) Settings::get('wa.campaigns.quiet_start', 21);         // 24h clock
        $quietEnd = (int) Settings::get('wa.campaigns.quiet_end', 9);
        $tz = config('app.timezone', 'Asia/Kuwait');

        $campaign = BulkInviteCampaign::findOrFail($this->campaignId);

        // Quiet hours window
        $hour = now($tz)->hour;
        if ($hour >= $quietStart || $hour < $quietEnd) {
            $resumeIn = ($hour >= $quietStart)
                ? (24 - $hour) + $quietEnd
                : ($quietEnd - $hour);

            Log::info('CampaignRunner: quiet hours — releasing', [
                'campaign' => $campaign->id, 'resume_in_hours' => $resumeIn,
            ]);
            $this->release($resumeIn * 3600);

            return;
        }

        // Fetch next slice
        /** @var Collection<int, BulkInviteCampaignRecipient> $recipients */
        $recipients = BulkInviteCampaignRecipient::query()
            ->where('bulk_invite_campaign_id', $campaign->id)
            ->whereIn('status', ['pending', 'retry'])
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        if ($recipients->isEmpty()) {
            if (method_exists($campaign, 'updateCounts')) {
                $campaign->updateCounts();
            }

            // If truly no more to send (pending/retry/queued), we can stop re-queuing
            $hasMore = BulkInviteCampaignRecipient::query()
                ->where('bulk_invite_campaign_id', $campaign->id)
                ->whereIn('status', ['pending', 'retry', 'queued'])
                ->exists();

            Log::info('CampaignRunner: no recipients left', [
                'campaign' => $campaign->id, 'has_more' => $hasMore,
            ]);

            return;
        }

        // Mark this slice as queued
        BulkInviteCampaignRecipient::query()
            ->whereIn('id', $recipients->pluck('id'))
            ->update(['status' => 'queued', 'queued_at' => now($tz)]);

        // Schedule the sends with per-second pacing (MPS)
        $count = $recipients->count();
        Log::info('CampaignRunner: scheduling slice', [
            'campaign' => $campaign->id, 'count' => $count, 'mps' => $mps,
        ]);

        $i = 0;
        foreach ($recipients as $r) {
            $secDelay = intdiv($i, $mps); // e.g., 0..(count/mps)
            SendCampaignInvite::dispatch($campaign->id, $r->id)
                ->onQueue('campaigns')
                ->delay(now($tz)->addSeconds($secDelay));
            $i++;
        }

        // Re-queue self after this slice finishes scheduling
        $sliceSeconds = (int) ceil($count / $mps);
        self::dispatch($campaign->id)
            ->onQueue('campaigns')
            ->delay(now($tz)->addSeconds($sliceSeconds + 1));
    }
}
