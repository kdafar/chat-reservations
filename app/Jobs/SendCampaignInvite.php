<?php

namespace App\Jobs;

use App\Models\BulkInviteCampaign;
use App\Models\BulkInviteCampaignRecipient;
use App\Services\WhatsAppInviteSender;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class SendCampaignInvite implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // runner sets the queue via ->onQueue('campaigns')
    public int $tries = 5;

    public int $timeout = 30;

    public array $backoff = [60, 300, 900, 1800];

    public function __construct(
        public int $campaignId,
        public int $recipientId
    ) {}

    public function handle(WhatsAppInviteSender $sender): void
    {
        // --- global pause ---
        if ((bool) Settings::get('wa.campaigns.sending_paused', false)) {
            $this->release(300);

            return;
        }

        // --- quiet hours guard ---
        $tz = config('app.timezone', 'Asia/Kuwait');
        $hour = now($tz)->hour;
        $quietStart = (int) Settings::get('wa.campaigns.quiet_start', 21);
        $quietEnd = (int) Settings::get('wa.campaigns.quiet_end', 9);

        if ($hour >= $quietStart || $hour < $quietEnd) {
            $resumeIn = ($hour >= $quietStart) ? (24 - $hour) + $quietEnd : ($quietEnd - $hour);
            $this->release($resumeIn * 3600);

            return;
        }

        // --- load models ---
        $campaign = BulkInviteCampaign::findOrFail($this->campaignId);
        $recipient = BulkInviteCampaignRecipient::findOrFail($this->recipientId);

        // Idempotency
        if ($recipient->status === 'sent' && $recipient->wa_message_id) {
            return;
        }

        // Optional per-MSISDN spacing
        $gap = (int) Settings::get('wa.campaigns.pair_gap_seconds', 0);
        if ($gap > 0 && $recipient->msisdn) {
            $lastSentAt = BulkInviteCampaignRecipient::query()
                ->where('msisdn', $recipient->msisdn)
                ->whereNotNull('sent_at')
                ->latest('sent_at')
                ->value('sent_at'); // string|Carbon|null

            if ($lastSentAt) {
                $last = $lastSentAt instanceof \Carbon\Carbon
                    ? $lastSentAt->copy()
                    : \Carbon\Carbon::parse($lastSentAt, $tz);

                $nextAllowed = $last->timezone($tz)->addSeconds($gap);

                if ($nextAllowed->isFuture()) {
                    $this->release($nextAllowed->diffInSeconds(\Carbon\Carbon::now($tz)) ?: 1);

                    return;
                }
            }
        }

        try {
            // DRY RUN
            if ((bool) Settings::get('wa.campaigns.dry_run', false)) {
                $recipient->markSent('DRY-'.Str::uuid());
            } else {
                // sender now takes only (campaign, recipient)
                $waId = $sender->send($campaign, $recipient);
                $recipient->markSent($waId);
            }
        } catch (\Throwable $e) {
            $msg = strtolower($e->getMessage());

            // Gentle cool-downs for risk/limits
            if (str_contains($msg, 'rate') || str_contains($msg, 'limit') || str_contains($msg, 'too many')) {
                $recipient->markFailed('rate-limit: '.$e->getMessage());
                $this->release(3600);

                return;
            }
            if (str_contains($msg, 'quality') || str_contains($msg, 'reach') || str_contains($msg, 'blocked')) {
                $recipient->markFailed('quality/cap: '.$e->getMessage());
                $this->release(7200);

                return;
            }

            // Unknown → record and bubble up
            $recipient->markFailed($e->getMessage());
            throw $e;
        }

        // Keep counters fresh
        if (method_exists($campaign, 'updateCounts')) {
            $campaign->updateCounts();
        }

        // Optional nudge: if runner died, wake it up
        try {
            $hasMore = BulkInviteCampaignRecipient::query()
                ->where('bulk_invite_campaign_id', $campaign->id)   // correct FK
                ->whereIn('status', ['pending', 'retry'])
                ->exists();

            if ($hasMore) {
                $runnerQueued = DB::table('jobs')
                    ->where('queue', 'campaigns')
                    ->where('payload', 'like', '%RunCampaignInBatches%')
                    ->where('payload', 'like', '%"campaignId":'.$campaign->id.'%')
                    ->exists();

                if (! $runnerQueued) {
                    RunCampaignInBatches::dispatch($campaign->id)
                        ->onQueue('campaigns')
                        ->delay(now($tz)->addSeconds(1));
                }
            }
        } catch (\Throwable $ignore) {
            // best-effort; safe to ignore
        }
    }
}
