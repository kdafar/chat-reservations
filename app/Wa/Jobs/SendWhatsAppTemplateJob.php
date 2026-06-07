<?php

namespace App\Wa\Jobs;

use App\Wa\Models\WhatsApp\WaMessage;
use App\Wa\Services\WhatsApp\Tenant\TenantWhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppTemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $waMessageId;

    public function __construct(int $waMessageId)
    {
        $this->waMessageId = $waMessageId;
    }

    public function handle(TenantWhatsAppService $tenantWhatsAppService): void
    {
        $message = WaMessage::with(['conversation.number', 'contact'])->find($this->waMessageId);

        if (! $message) {
            return;
        }

        $payload = $message->meta_raw['template_payload'] ?? null;

        if (! $payload) {
            $message->update([
                'status' => 'failed',
                'error_message' => 'Missing template payload',
            ]);

            return;
        }

        $conversation = $message->conversation;
        $number = $conversation?->number;
        $contact = $message->contact;

        if (! $number || ! $contact) {
            $message->update([
                'status' => 'failed',
                'error_message' => 'Missing number or contact',
            ]);

            return;
        }

        $toRaw = $contact->phone ?: $contact->wa_id;
        $to = preg_replace('/\D+/', '', (string) $toRaw);

        if (! $to) {
            $message->update([
                'status' => 'failed',
                'error_message' => 'Invalid destination phone',
            ]);

            return;
        }

        try {
            $svc = $tenantWhatsAppService->forNumber($number);
            $svc->sendTemplate($to, $payload);

            $message->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // rethrow so failed jobs are visible in horizon/queue:failed
            throw $e;
        }
    }
}
