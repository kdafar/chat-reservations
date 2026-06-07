<?php

namespace App\Wa\Jobs;

use App\Wa\Hub\Models\PromotionalCampaign;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use App\Wa\Hub\Models\PromotionalCampaignRecipient;
use App\Wa\Hub\Models\WhatsappSession;
use App\Wa\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Wave\Setting;

class SendPromotionalCampaignMessage implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $campaignId;

    public int $recipientId;

    public bool $bypassFrequencyCap = false;

    public $timeout = 120;

    public $deleteWhenMissingModels = true;

    public function __construct(int $campaignId, int $recipientId, bool $bypassFrequencyCap = false)
    {
        $this->campaignId = $campaignId;
        $this->recipientId = $recipientId;
        $this->bypassFrequencyCap = $bypassFrequencyCap;
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }

    public function middleware(): array
    {
        return [
            new RateLimited('whatsapp_slow_lane'),
        ];
    }

    public function handle(WhatsAppService $whatsapp): void
    {
        /** @var PromotionalCampaign|null $campaign */
        $campaign = PromotionalCampaign::find($this->campaignId);
        /** @var PromotionalCampaignRecipient|null $recipient */
        $recipient = PromotionalCampaignRecipient::find($this->recipientId);

        if (! $campaign || ! $recipient) {
            return;
        }

        //  SAFETY CHECK 1: PAUSE LOGIC
        if ($campaign->status === 'paused') {
            $this->release(60);

            return;
        }

        //  SAFETY CHECK 2: CANCEL LOGIC
        if (in_array($campaign->status, ['failed', 'draft', 'archived'])) {
            return;
        }

        if (in_array($recipient->status, [
            'sent', 'delivered', 'read', 'limited', 'undeliverable', 'experiment_blocked',
        ], true)) {
            return;
        }

        //  NEW: ATOMIC LOCK
        // Prevent race conditions where 2 workers pick up the same phone number simultaneously
        // Lock lasts 10 seconds, sufficient for the DB check and API call
        $lockKey = 'wa_send_lock_'.preg_replace('/[^0-9]/', '', $recipient->msisdn);
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            // Could not get lock, another job is processing this number. Wait 5s.
            $this->release(5);

            return;
        }

        try {
            //  SAFETY CHECK 3: OPT-OUT CHECK (STOP WORD)
            $digits = preg_replace('/\D+/', '', (string) $recipient->msisdn);

            $isBlocked = WhatsappSession::query()
                ->where(function ($q) use ($recipient, $digits) {
                    $q->where('customer_phone_number', $recipient->msisdn)
                        ->orWhere('customer_phone_number', $digits)
                        ->orWhere('customer_phone_number', '+'.$digits);
                })
                ->where('is_blocked', true)
                ->exists();

            if ($isBlocked) {
                $recipient->status = 'undeliverable';
                $recipient->error_message = 'User opted out (STOP received).';
                $recipient->save();

                Log::info("[PromoCampaign] Skipped opted-out user: {$recipient->msisdn}");

                return;
            }

            // ---------------------------------------------------------------------
            // FREQUENCY CAPPING CHECK (24 Hours)
            // ---------------------------------------------------------------------
            $whitelistStr = Setting::get('whatsapp.frequency_cap_whitelist', '');
            $whitelist = array_filter(array_map('trim', explode(',', $whitelistStr)));
            $isWhitelisted = in_array($recipient->msisdn, $whitelist);

            $shouldCheckFrequency = ! $this->bypassFrequencyCap
                && ! env('WHATSAPP_BYPASS_CAP', false)
                && ! $isWhitelisted;

            if ($shouldCheckFrequency) {
                $frequencyHours = 24;
                $threshold = now()->subHours($frequencyHours);

                $lastSent = PromotionalCampaignRecipient::query()
                    ->where('msisdn', $recipient->msisdn)
                    ->where('id', '!=', $this->recipientId) // exclude self
                    ->whereIn('status', ['sent', 'delivered', 'read'])
                    ->where('sent_at', '>', $threshold)
                    ->latest('sent_at')
                    ->first();

                if ($lastSent && $lastSent->sent_at) {
                    $lastSentAt = $lastSent->sent_at instanceof Carbon
                        ? $lastSent->sent_at
                        : Carbon::parse($lastSent->sent_at);

                    $nextAvailableSlot = $lastSentAt->copy()->addHours($frequencyHours);
                    $secondsToWait = now()->diffInSeconds($nextAvailableSlot, false);

                    if ($secondsToWait > 0) {
                        $retryDelay = $secondsToWait + 300;
                        $waitMsg = 'Rate limit: Delayed until '.$nextAvailableSlot->format('M d, H:i');

                        Log::info("[PromoCampaign] Frequency cap hit for {$recipient->msisdn}. {$waitMsg}");

                        $recipient->update(['error_message' => $waitMsg]);
                        $this->release($retryDelay);

                        return;
                    }
                }
            }

            // ---------------------------------------------------------------------
            // PREPARE & SEND
            // ---------------------------------------------------------------------
            $details = (array) $campaign->template_details;
            $vars = (array) $campaign->template_variables;
            $components = $details['components'] ?? [];
            $language = $details['language'] ?? $campaign->default_locale ?? 'en';
            $to = $recipient->msisdn;
            $tplName = $campaign->template_name;

            $bodyParams = [];
            if (! empty($vars)) {
                ksort($vars, SORT_NUMERIC);
                foreach ($vars as $index => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $bodyParams[] = ['type' => 'text', 'text' => (string) $value];
                }
            }

            $headerParams = [];
            $headerComponent = collect($components)->firstWhere('type', 'HEADER');

            if ($headerComponent && $campaign->header_image_path) {
                $format = strtolower((string) ($headerComponent['format'] ?? ''));
                $mediaUrl = Storage::disk('public')->url($campaign->header_image_path);

                if (in_array($format, ['image', 'video', 'document'])) {
                    $headerParams[] = [
                        'type' => $format,
                        $format => ['link' => $mediaUrl],
                    ];
                }
            }

            if ($headerComponent && ($headerComponent['format'] ?? '') === 'TEXT') {
                $headerText = (string) ($headerComponent['text'] ?? '');
                if (str_contains($headerText, '{{') && $campaign->header_variable) {
                    $headerParams[] = ['type' => 'text', 'text' => (string) $campaign->header_variable];
                }
            }

            $templateComponents = [];
            if (! empty($headerParams)) {
                $templateComponents[] = ['type' => 'header', 'parameters' => $headerParams];
            }
            if (! empty($bodyParams)) {
                $templateComponents[] = ['type' => 'body', 'parameters' => $bodyParams];
            }

            $templatePayload = [
                'name' => $tplName,
                'language' => ['code' => $language],
                'components' => $templateComponents,
                'campaign_id' => $this->campaignId, // Passed for Points Accounting Fail-Safe
            ];

            $response = $whatsapp->sendMarketingTemplate($to, $templatePayload);

            $waMessageId = data_get($response, 'messages.0.id');

            if (! $waMessageId) {
                $recipient->status = 'failed';
                $recipient->error_message = 'No WhatsApp message id returned from API.';
                $recipient->save();

                return;
            }

            $recipient->status = 'sent';
            $recipient->sent_at = now();
            $recipient->wa_message_id = $waMessageId;
            $recipient->error_message = null;
            $recipient->save();

        } catch (\Throwable $e) {
            Log::error('[PromoCampaign] Send failed', [
                'campaign_id' => $this->campaignId,
                'recipient_id' => $this->recipientId,
                'error' => $e->getMessage(),
            ]);

            //  SMART RETRY: If it's a network/timeout issue, do NOT fail. Release for later.
            if ($e instanceof ConnectionException || str_contains($e->getMessage(), 'timeout') || str_contains($e->getMessage(), 'cURL error')) {
                $this->release(30); // Try again in 30 seconds

                return;
            }

            $recipient->status = 'failed';
            $recipient->error_message = $e->getMessage();
            $recipient->wa_error_code = (string) $e->getCode();
            $recipient->wa_error_title = class_basename($e);
            $recipient->save();
        } finally {
            //  RELEASE LOCK
            $lock->release();
        }
    }
}
