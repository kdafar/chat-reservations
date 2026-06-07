<?php

namespace App\Wa\Listeners;

use App\Wa\Events\OutgoingWhatsappMessageSent;

class StoreOutgoingWhatsappMessage
{
    public function handle(OutgoingWhatsappMessageSent $e): void
    {
        if (empty($e->metaMessageId)) {
            \Log::warning('[WA] Skipping store: no metaMessageId', ['type' => $e->body['type'] ?? null]);

            return;
        }

        $msg = \App\Wa\Hub\Models\WhatsappMessage::updateOrCreate(
            ['meta_message_id' => $e->metaMessageId],
            [
                'whatsapp_session_id' => $e->session->id,
                'restaurant_id' => $e->session->selected_vendor_id,
                'direction' => 'outgoing',
                'type' => $e->body['type'] ?? null,
                'content' => $e->body, // cast to array
                'delivery_status' => \App\Wa\Hub\Models\WhatsappMessage::STATUS_QUEUED,
            ]
        );

        \Log::info('[WA] Outgoing stored/upserted', [
            'wamid' => $e->metaMessageId,
            'type' => $msg->type,
            'tpl' => data_get($msg->content, 'template.name'),
        ]);
    }
}
