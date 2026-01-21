<?php

namespace App\Services\WAFlow;

use App\Models\WhatsappSession;

class FlowSession
{
    public function findByFlowToken(string $token): ?WhatsappSession
    {
        return WhatsappSession::where('context->flow_token', $token)->first();
    }

    public function localeOf(?WhatsappSession $session): string
    {
        return ($session?->locale === 'ar') ? 'ar' : 'en';
    }

    public function debugMeta(?WhatsappSession $session): array
    {
        $msisdn = $session?->phone;

        return [
            'scope' => 'wa.flow.endpoint',
            'found' => (bool) $session,
            'msisdn' => $msisdn ? substr($msisdn, 0, 3).'••••' : null,
            'locale' => $this->localeOf($session),
            'ctx_keys' => $session ? array_keys((array) $session->context) : [],
        ];
    }
}
