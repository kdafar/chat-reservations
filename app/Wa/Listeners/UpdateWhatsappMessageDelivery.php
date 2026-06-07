<?php

namespace App\Wa\Listeners;

use App\Wa\Events\OutgoingWhatsappStatusReceived;
use App\Wa\Hub\Models\WhatsappMessage;
use Illuminate\Support\Facades\Log;

class UpdateWhatsappMessageDelivery
{
    public function handle(OutgoingWhatsappStatusReceived $event): void
    {
        $s = $event->status;

        $wamid = $s['id'] ?? null;
        $status = $s['status'] ?? null;           // sent|delivered|read|failed
        $code = data_get($s, 'errors.0.code');
        $title = data_get($s, 'errors.0.title');

        // Meta may put details in different places
        $rawDetail = data_get($s, 'errors.0.error_data.details')
                  ?? data_get($s, 'errors.0.details')
                  ?? data_get($s, 'errors.0.message');

        // Build a JSON-safe payload for the JSON column
        $detailsPayload = null;
        if (is_array($rawDetail) || is_object($rawDetail)) {
            $detailsPayload = $rawDetail;
        } elseif (is_string($rawDetail) && $rawDetail !== '') {
            $detailsPayload = ['details' => $rawDetail];
        }

        // If available, also include error_data for richer debugging
        $errorData = data_get($s, 'errors.0.error_data');
        if (is_array($errorData) || is_object($errorData)) {
            $detailsPayload = ($detailsPayload ?? []) + ['error_data' => $errorData];
        }

        $detailsJson = null;
        if ($detailsPayload !== null) {
            $detailsJson = json_encode($detailsPayload, JSON_UNESCAPED_UNICODE);
            if ($detailsJson === false) {
                Log::warning('[WA] Failed to json_encode error_details', [
                    'wamid' => $wamid,
                    'json_error' => json_last_error_msg(),
                ]);
                $detailsJson = null;
            }
        }

        if (! $wamid) {
            return;
        }

        WhatsappMessage::where('meta_message_id', $wamid)
            ->latest('id')
            ->limit(1)
            ->update([
                'delivery_status' => $status,
                'error_code' => $code,
                'error_title' => $title,
                // IMPORTANT: pass JSON text or null into a JSON column
                'error_details' => $detailsJson,
            ]);

        Log::info('[WA] Delivery persisted', compact('wamid', 'status', 'code'));
    }
}
