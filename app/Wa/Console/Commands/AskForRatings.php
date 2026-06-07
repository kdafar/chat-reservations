<?php

namespace App\Wa\Console\Commands;

use App\Wa\Hub\Models\WhatsappSession;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AskForRatings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ask-for-ratings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Asks customers for a rating one hour after their order is completed.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Searching for completed orders to request ratings...');

        // Find sessions that were completed between 60 and 120 minutes ago
        // to avoid sending multiple requests for the same order.
        $sessions = WhatsappSession::with('restaurant')
            ->where('status', 'completed')
            ->where('updated_at', '<=', Carbon::now()->subHour())
            ->where('updated_at', '>', Carbon::now()->subHours(2))
            ->get();

        if ($sessions->isEmpty()) {
            $this->info('No completed orders found in the last hour.');

            return 0;
        }

        foreach ($sessions as $session) {
            $this->info("Found completed order for customer: {$session->customer_phone_number}");
            $this->requestRatingForOrder($session);
        }

        $this->info('Finished sending rating requests.');

        return 0;
    }

    /**
     * Sends the rating request message for a given session.
     */
    private function requestRatingForOrder(WhatsappSession $session)
    {
        if (! $session->restaurant) {
            $this->error("Session ID {$session->id} is missing a restaurant.");

            return;
        }

        // First, we need to find the Chatwoot conversation ID for this customer.
        $conversationId = $this->findChatwootConversationId($session->customer_phone_number);

        if (! $conversationId) {
            $this->error("Could not find Chatwoot conversation for {$session->customer_phone_number}");

            return;
        }

        $locale = 'en'; // You can enhance this later to get user's preferred language
        $restaurantId = $session->restaurant->id;
        $restaurantName = $session->restaurant->getTranslation('name', $locale);

        // Construct the interactive button message payload
        $payload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => sprintf(
                        'Thanks for your recent order from %s! We would love your feedback. How would you rate your experience?',
                        $restaurantName
                    ),
                ],
                'action' => [
                    'buttons' => [
                        ['type' => 'reply', 'reply' => ['id' => "rate_order_{$restaurantId}_1", 'title' => '⭐️ Bad']],
                        ['type' => 'reply', 'reply' => ['id' => "rate_order_{$restaurantId}_2", 'title' => '⭐️⭐️ OK']],
                        ['type' => 'reply', 'reply' => ['id' => "rate_order_{$restaurantId}_3", 'title' => '⭐️⭐️⭐️ Good']],
                        ['type' => 'reply', 'reply' => ['id' => "rate_order_{$restaurantId}_4", 'title' => '⭐️⭐️⭐️⭐️ Great']],
                        ['type' => 'reply', 'reply' => ['id' => "rate_order_{$restaurantId}_5", 'title' => '⭐️⭐️⭐️⭐️⭐️ Excellent']],
                    ],
                ],
            ],
        ];

        // Send the message using the same logic as the BotController
        $this->sendMessageToChatwoot($conversationId, $payload);
        $this->info("Rating request sent to {$session->customer_phone_number}");
    }

    /**
     * Finds a Chatwoot conversation ID for a given phone number.
     */
    private function findChatwootConversationId(string $phoneNumber): ?int
    {
        $chatwootUrl = env('CHATWOOT_URL');
        $chatwootApiToken = env('CHATWOOT_BOT_TOKEN');
        $chatwootAccountId = env('CHATWOOT_ACCOUNT_ID');

        // 1. Search for the contact by phone number
        $searchUrl = "{$chatwootUrl}/api/v1/accounts/{$chatwootAccountId}/contacts/search?q={$phoneNumber}";
        $response = Http::withHeaders(['api_access_token' => $chatwootApiToken])->get($searchUrl);

        if ($response->failed() || empty($response->json('payload'))) {
            return null;
        }

        $contactId = $response->json('payload.0.id');

        // 2. Get the conversations for that contact
        $convUrl = "{$chatwootUrl}/api/v1/accounts/{$chatwootAccountId}/contacts/{$contactId}/conversations";
        $convResponse = Http::withHeaders(['api_access_token' => $chatwootApiToken])->get($convUrl);

        if ($convResponse->failed() || empty($convResponse->json('payload'))) {
            return null;
        }

        // Return the ID of the most recent conversation
        return $convResponse->json('payload.0.id');
    }

    /**
     * Sends a message payload to a Chatwoot conversation.
     */
    private function sendMessageToChatwoot(int $conversationId, array $payload): void
    {
        $chatwootUrl = env('CHATWOOT_URL');
        $chatwootApiToken = env('CHATWOOT_BOT_TOKEN');
        $chatwootAccountId = env('CHATWOOT_ACCOUNT_ID');

        $chatwootPayload = [
            'content_attributes' => ['submitted_values' => [$payload]],
            'message_type' => 'outgoing',
            'private' => false,
        ];

        $apiUrl = "{$chatwootUrl}/api/v1/accounts/{$chatwootAccountId}/conversations/{$conversationId}/messages";

        $response = Http::withHeaders([
            'api_access_token' => $chatwootApiToken,
            'Content-Type' => 'application/json',
        ])->post($apiUrl, $chatwootPayload);

        if ($response->failed()) {
            Log::error('Failed to send rating request via Chatwoot API', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
