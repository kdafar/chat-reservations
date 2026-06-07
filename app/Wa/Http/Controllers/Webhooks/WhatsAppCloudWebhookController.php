<?php

namespace App\Wa\Http\Controllers\Webhooks;

use App\Wa\Http\Controllers\Controller;
use App\Wa\Models\WhatsApp\WaAccount;
use App\Wa\Models\WhatsApp\WaContact;
use App\Wa\Models\WhatsApp\WaConversation;
use App\Wa\Models\WhatsApp\WaMessage;
use App\Wa\Models\WhatsApp\WaNumber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppCloudWebhookController extends Controller
{
    /**
     * GET /webhooks/whatsapp-cloud
     * Used by Meta to verify the webhook.
     */
    public function verify(Request $request)
    {
        $mode = $request->input('hub_mode');
        $token = $request->input('hub_verify_token');
        $challenge = $request->input('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * POST /webhooks/whatsapp-cloud
     * Main handler for incoming messages & status updates.
     */
    public function handle(Request $request)
    {
        $payload = $request->all();

        Log::info('[WA-WEBHOOK] Incoming payload', [
            'payload' => $payload,
        ]);

        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return response()->json(['ignored' => true]);
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $metadata = $value['metadata'] ?? [];
                $phoneNumberId = $metadata['phone_number_id'] ?? null;

                if (! $phoneNumberId) {
                    continue;
                }

                // Map phone_number_id -> WaNumber
                $number = WaNumber::where('phone_number_id', $phoneNumberId)->first();

                if (! $number) {
                    Log::warning('[WA-WEBHOOK] No WaNumber found for phone_number_id', [
                        'phone_number_id' => $phoneNumberId,
                    ]);

                    continue;
                }

                $account = $number->account;

                // 1) Incoming messages
                foreach ($value['messages'] ?? [] as $message) {
                    $this->handleIncomingMessage($account, $number, $value, $message);
                }

                // 2) Status updates (for outgoing messages)
                foreach ($value['statuses'] ?? [] as $status) {
                    $this->handleStatusUpdate($account, $number, $status);
                }
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Handle an incoming message from a customer
     */
    protected function handleIncomingMessage(
        WaAccount $account,
        WaNumber $number,
        array $value,
        array $message
    ): void {
        $waId = $message['from'] ?? null; // JID like "9655xxxxxxx"
        if (! $waId) {
            return;
        }

        $phoneDigits = preg_replace('/\D+/', '', $waId);

        // Try to get contact details from the "contacts" array
        $contactName = null;
        foreach ($value['contacts'] ?? [] as $contact) {
            if (($contact['wa_id'] ?? null) === $waId) {
                $contactName = $contact['profile']['name'] ?? null;
                break;
            }
        }

        // Upsert wa_contacts
        $contact = WaContact::firstOrCreate(
            [
                'wa_account_id' => $account->id,
                'wa_id' => $waId,
            ],
            [
                'phone' => $phoneDigits,
                'name' => $contactName,
                'meta_raw' => $value['contacts'] ?? null,
            ]
        );

        // Find or create conversation (per account + number + contact)
        $conversation = WaConversation::firstOrCreate(
            [
                'wa_account_id' => $account->id,
                'wa_number_id' => $number->id,
                'contact_id' => $contact->id,
            ],
            [
                'status' => 'open',
            ]
        );

        // Extract message details
        $metaMessageId = $message['id'] ?? null;
        $type = $message['type'] ?? 'text';
        $body = null;

        if ($type === 'text') {
            $body = $message['text']['body'] ?? null;
        } elseif ($type === 'interactive') {
            // You can expand this later; for now store a simple summary
            $body = '[interactive reply]';
        } elseif ($type === 'image') {
            $body = '[image message]';
        } else {
            $body = '['.$type.' message]';
        }

        $timestamp = isset($message['timestamp'])
            ? Carbon::createFromTimestamp((int) $message['timestamp'])
            : now();

        // Store wa_messages (incoming)
        $waMessage = WaMessage::create([
            'wa_account_id' => $account->id,
            'wa_number_id' => $number->id,
            'conversation_id' => $conversation->id,

            'direction' => 'inbound',   // <-- incoming (canonical; v2 keys off inbound/outbound)
            'type' => $type,
            'body' => $body,
            'meta_message_id' => $metaMessageId,
            'status' => 'received',

            'sent_at' => $timestamp,
            'meta_raw' => $message,
        ]);

        // Update conversation timestamps
        $conversation->fill([
            'last_message_at' => $timestamp,
            'last_incoming_at' => $timestamp,
        ])->save();

        Log::info('[WA-WEBHOOK] Stored incoming message', [
            'conversation_id' => $conversation->id,
            'message_id' => $waMessage->id,
        ]);
    }

    /**
     * Handle status updates for outgoing messages (sent/delivered/read/failed)
     */
    protected function handleStatusUpdate(
        WaAccount $account,
        WaNumber $number,
        array $status
    ): void {
        $metaMessageId = $status['id'] ?? null;
        if (! $metaMessageId) {
            return;
        }

        $newStatus = $status['status'] ?? null;
        $ts = isset($status['timestamp'])
            ? Carbon::createFromTimestamp((int) $status['timestamp'])
            : now();

        $waMessage = WaMessage::where('wa_account_id', $account->id)
            ->where('wa_number_id', $number->id)
            ->where('meta_message_id', $metaMessageId)
            ->first();

        if (! $waMessage) {
            Log::warning('[WA-WEBHOOK] Status for unknown message', [
                'meta_message_id' => $metaMessageId,
                'status' => $newStatus,
            ]);

            return;
        }

        $waMessage->status = $newStatus;

        switch ($newStatus) {
            case 'sent':
                $waMessage->sent_at = $ts;
                break;

            case 'delivered':
                $waMessage->delivered_at = $ts;
                break;

            case 'read':
                $waMessage->read_at = $ts;
                break;

            case 'failed':
                $error = $status['errors'][0] ?? null;
                if ($error) {
                    $waMessage->error_code = $error['code'] ?? null;
                    $waMessage->error_message = $error['title'] ?? ($error['message'] ?? null);
                }
                break;
        }

        $waMessage->meta_raw = $status;
        $waMessage->save();

        // Also bump conversation last_message_at for delivered/read if you want
        if ($waMessage->conversation) {
            $waMessage->conversation->update([
                'last_message_at' => $ts,
                'last_outgoing_at' => $newStatus === 'sent' || $newStatus === 'delivered' || $newStatus === 'read'
                    ? $ts
                    : $waMessage->conversation->last_outgoing_at,
            ]);
        }

        Log::info('[WA-WEBHOOK] Updated message status', [
            'wa_message_id' => $waMessage->id,
            'status' => $newStatus,
        ]);
    }
}
