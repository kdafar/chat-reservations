<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MetaHealthService
{
    public function __construct(protected WhatsAppApiService $wa) {}

    public function fetch(): array
    {
        try {
            $version = (string) $this->wa->getGraphVersion(); // e.g. v24.0
            $phoneId = (string) $this->wa->getPhoneId();
            $token = (string) $this->wa->getGraphToken();

            if ($token === '' || $phoneId === '') {
                return [
                    'ok' => false,
                    'error' => 'Missing WhatsApp access token or phone number id (services.whatsapp.* / env).',
                    'data' => [],
                ];
            }

            // 1) PHONE FIELDS (only what's safely supported)
            $phoneUrl = "https://graph.facebook.com/{$version}/{$phoneId}";
            $phoneFields = implode(',', [
                'display_phone_number',
                'quality_rating',
                'name_status',
                'verified_name',
            ]);

            $ph = $this->wa->graphGet($phoneUrl, ['fields' => $phoneFields]);
            if (! ($ph['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'error' => 'Graph error (phone): '.$ph['code'].' '.$ph['body'],
                    'data' => [],
                ];
            }
            $phone = $ph['json'] ?? [];

            // 2) Resolve WABA ID (prefer config/env first)
            $wabaId = (string) (
                config('services.whatsapp.waba_id')
                ?: env('WHATSAPP_BUSINESS_ACCOUNT_ID', '')
            );

            if ($wabaId === '') {
                $wabaEdgeUrl = "https://graph.facebook.com/{$version}/{$phoneId}/whatsapp_business_account";
                $edge = $this->wa->graphGet($wabaEdgeUrl, ['fields' => 'id']); // fields often ignored on edge
                if ($edge['ok'] ?? false) {
                    $ej = $edge['json'] ?? [];
                    if (isset($ej['id'])) {
                        $wabaId = (string) $ej['id'];
                    } elseif (isset($ej['data'][0]['id'])) {
                        $wabaId = (string) $ej['data'][0]['id'];
                    } else {
                        Log::info('MetaHealth: WABA edge returned no id', ['json' => $ej]);
                    }
                } else {
                    Log::info('MetaHealth: WABA edge not accessible', ['code' => $edge['code'] ?? null]);
                }
            }

            // 3) If we have WABA ID, fetch details + templates total
            $wabaName = null;
            $isOBA = null;
            $templatesTotal = null;

            if ($wabaId !== '') {
                // WABA details
                $wabaUrl = "https://graph.facebook.com/{$version}/{$wabaId}";
                $waba = $this->wa->graphGet($wabaUrl, ['fields' => 'name,is_official_business_account']);
                if ($waba['ok'] ?? false) {
                    $wj = $waba['json'] ?? [];
                    $wabaName = isset($wj['name']) ? (string) $wj['name'] : null;
                    $isOBA = array_key_exists('is_official_business_account', $wj)
                        ? (bool) $wj['is_official_business_account']
                        : null;
                } else {
                    Log::info('MetaHealth: WABA details not accessible', ['code' => $waba['code'] ?? null]);
                }

                // Templates total (summary)
                $tpl = $this->wa->graphGet("https://graph.facebook.com/{$version}/{$wabaId}/message_templates", [
                    'limit' => 1,               // any positive limit, summary carries total_count
                    'summary' => 'total_count',
                ]);
                if ($tpl['ok'] ?? false) {
                    $templatesTotal = (int) (($tpl['json']['summary']['total_count'] ?? 0));
                } else {
                    Log::info('MetaHealth: templates listing not accessible', ['code' => $tpl['code'] ?? null]);
                }
            }

            return [
                'ok' => true,
                'data' => [
                    'graph_version' => $version,
                    'phone_number_id' => $phoneId,
                    'display_phone_number' => (string) ($phone['display_phone_number'] ?? ''),
                    'quality_rating' => strtoupper((string) ($phone['quality_rating'] ?? 'UNKNOWN')),
                    'name_status' => strtoupper((string) ($phone['name_status'] ?? 'UNKNOWN')),
                    'verified_name' => (string) ($phone['verified_name'] ?? ''),

                    // Extras (may be null if perms missing)
                    'waba_id' => $wabaId ?: null,
                    'waba_name' => $wabaName,
                    'is_official_business_account' => $isOBA,
                    'templates_total' => $templatesTotal,

                    // Raw for debugging if you ever want to show it
                    'raw' => [
                        'phone' => $phone,
                        // NOTE: details/edge payloads are not included here to keep the object small
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('MetaHealthService.fetch failed', ['e' => $e->getMessage()]);

            return [
                'ok' => false,
                'error' => 'Exception: '.$e->getMessage(),
                'data' => [],
            ];
        }
    }
}
