<?php

namespace App\Wa\Listeners;

use App\Wa\Events\IncomingWhatsappMessageReceived;
use App\Wa\Events\OutgoingWhatsappMessageSent;
use App\Wa\Hub\Models\WhatsappMessage;

class LogWhatsappMessage
{
    public function handle($event)
    {
        if ($event instanceof IncomingWhatsappMessageReceived) {
            $this->logIncoming($event);
        } elseif ($event instanceof OutgoingWhatsappMessageSent) {
            $this->logOutgoing($event);
        }
    }

    private function logIncoming(IncomingWhatsappMessageReceived $event): void
    {
        //  FIX: Use firstOrCreate to prevent duplicate entries.
        WhatsappMessage::firstOrCreate(
            [
                // This is the unique key we check against.
                'meta_message_id' => $event->messageData['id'],
            ],
            [
                // This data is only used if the message is new.
                'whatsapp_session_id' => $event->session->id,
                'restaurant_id' => $event->session->selected_vendor_id,
                'direction' => 'incoming',
                'type' => $event->messageData['type'],
                'content' => $event->messageData['text']['body'] ?? json_encode($event->messageData),
                'status' => 'delivered',
            ]
        );
    }

    private function logOutgoing(OutgoingWhatsappMessageSent $event): void
    {
        //  FIX: Use firstOrCreate for outgoing messages as well.
        // This is important because the response from Meta confirming the send
        // could also arrive multiple times.
        WhatsappMessage::firstOrCreate(
            [
                // This is the unique key we check against.
                'meta_message_id' => $event->metaMessageId,
            ],
            [
                // This data is only used if the message is new.
                'whatsapp_session_id' => $event->session->id,
                'restaurant_id' => $event->session->selected_vendor_id,
                'direction' => 'outgoing',
                'type' => $event->body['type'] ?? 'unknown',
                'content' => $event->body,
                'status' => 'sent',
            ]
        );
    }
}
