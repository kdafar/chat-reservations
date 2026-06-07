<?php

namespace App\Wa\Jobs;

use App\Wa\Hub\Models\PromotionalCampaign;
use App\Wa\Hub\Models\WhatsappSession;
use App\Wa\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendPromotionalCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public PromotionalCampaign $campaign;

    public array $sessionIds;

    public function __construct(PromotionalCampaign $campaign, array $sessionIds)
    {
        $this->campaign = $campaign;
        $this->sessionIds = $sessionIds;
    }

    public function handle(WhatsAppService $whatsAppService)
    {
        $this->campaign->load(['messageTemplate', 'restaurant']);
        $templateBody = $this->campaign->messageTemplate->body;
        $restaurant = $this->campaign->restaurant;

        if (! $restaurant) {
            $this->fail(new \Exception('Campaign is not associated with a restaurant.'));

            return;
        }

        // --- CHANGE: Tag all targeted sessions with the campaign ID before sending ---
        WhatsappSession::whereIn('id', $this->sessionIds)
            ->update(['last_promotional_campaign_id' => $this->campaign->id]);

        $sessions = WhatsappSession::with(['customerProfile', 'restaurant'])->find($this->sessionIds);
        $sentCount = 0;

        foreach ($sessions as $session) {
            try {
                $message = $this->personalizeMessage($templateBody, $session);
                $whatsAppService->sendTextMessage($session->customer_phone_number, $message);
                $sentCount++;
                sleep(1);
            } catch (\Exception $e) {
                Log::error('Failed to send promotional message', [
                    'campaign_id' => $this->campaign->id,
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        if ($sentCount > 0) {
            DB::transaction(function () use ($restaurant, $sentCount) {
                $restaurant->decrement('points', $sentCount);
            });
        }

        $this->campaign->update(['status' => 'completed']);
    }

    private function personalizeMessage(string $body, WhatsappSession $session): string
    {
        $placeholders = [
            '{{customer_name}}' => $session->customerProfile->full_name ?? 'Valued Customer',
            '{{restaurant_name}}' => $session->restaurant->name ?? 'one of our restaurants',
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $body);
    }

    public function failed(\Throwable $exception)
    {
        $this->campaign->update(['status' => 'failed']);
        Log::critical('Promotional Campaign Job Failed', [
            'campaign_id' => $this->campaign->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
