<?php

namespace App\Http\Controllers\Api;

use App\Services\Flows\FlowBookingHandler; // ⬅️ Switched to Handler
use App\Services\WAFlow\FlowCrypto;
use App\Services\WAFlow\FlowResponder;
use App\Services\WAFlow\FlowSession;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WhatsAppFlowEndpointController extends Controller
{
    public function __construct(
        private FlowCrypto $crypto,
        private FlowResponder $responder,
        private FlowSession $sessions,
        private FlowBookingHandler $handler, // ⬅️ Injected the new handler
    ) {}

    public function __invoke(Request $request)
    {
        // 1) Parse & decrypt
        $encDataB64 = $request->input('encrypted_flow_data');
        $encKeyB64 = $request->input('encrypted_aes_key');
        $ivB64 = $request->input('initial_vector');

        if (! is_string($encDataB64) || ! is_string($encKeyB64) || ! is_string($ivB64)) {
            Log::warning('Flow: bad request json');

            return response('bad request', 400);
        }

        $req = $this->crypto->decrypt($encDataB64, $encKeyB64, $ivB64);
        if (! $req) {
            return response('decryption failed', 421);
        }

        // Logging
        Log::info('Flow: Decrypted Request Payload', [
            'scope' => 'wa.flow.endpoint',
            'action' => $req->action ?? null,
            'screen' => $req->screen ?? null,
            'version' => $req->version ?? null,
            'flow_token' => $req->flowToken ?? null,
            'data_keys' => $req->data ? array_keys((array) $req->data) : [],
            'data_payload' => $req->data ? (array) $req->data : [],
        ]);

        // 2) Health check (PING)
        $action = strtoupper((string) ($req->action ?? ''));
        if ($action === 'PING') {
            Log::info('Flow: PING -> active');
            $body = [
                'version' => '3.0',
                'screen' => 'HEALTH_CHECK',
                'data' => ['status' => 'active'],
            ];

            return $this->responder->encrypt($body, $req->aesKey, $req->requestIv);
        }

        // 3) Resolve WA session (msisdn/locale)
        $session = $this->sessions->findByFlowToken($req->flowToken);
        $locale = $this->sessions->localeOf($session);

        Log::info('Flow: session lookup', $this->sessions->debugMeta($session));

        // 4) Call the handler (FlowBookingHandler)
        Log::info('Flow: Calling Handler', [
            'scope' => 'wa.flow.endpoint',
            'screen' => $req->screen ?? null,
            'action' => $action,
            'locale' => $locale,
        ]);

        // Call the Handler directly
        $out = $this->handler->handle($req, $session, $locale);

        // 5) Get screen and data from handler response
        $respScreen = (string) ($out['screen'] ?? '');
        $respData = (array) ($out['data'] ?? []);

        Log::info('Flow: Service Response', [
            'scope' => 'wa.flow.endpoint',
            'response_screen' => $respScreen,
            'response_data_keys' => array_keys($respData),
        ]);

        // 6) Defensive shape-normalization for APPOINTMENT only
        if ($respScreen === 'APPOINTMENT') {
            $respData = $this->sanitizeAppointmentData($respData);
        }

        // 7) Force version
        $body = ['version' => '3.0', 'screen' => $respScreen, 'data' => $respData];

        Log::info('Flow: Encrypting Response', [
            'scope' => 'wa.flow.endpoint',
            'version' => $body['version'],
            'screen' => $body['screen'],
        ]);

        // 8) Encrypt & return
        return $this->responder->encrypt($body, $req->aesKey, $req->requestIv);
    }

    /**
     * Coerce APPOINTMENT data into WA schema-friendly shapes.
     * This matches the v7.5+ flow structure.
     */
    private function sanitizeAppointmentData(array $out): array
    {
        $shapeItem = static fn (array $item): array => [
            'id' => (string) ($item['id'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'enabled' => array_key_exists('enabled', $item) ? (bool) $item['enabled'] : true,
        ];

        // Sanitize 'available_party_sizes'
        $party = array_values((array) ($out['available_party_sizes'] ?? []));
        $party = array_values(array_map($shapeItem, $party));
        $out['available_party_sizes'] = $party;

        // Sanitize 'available_times'
        $time = array_values((array) ($out['available_times'] ?? []));
        $time = array_values(array_map($shapeItem, $time));
        $out['available_times'] = $time;

        // Ensure other fields are correct types
        $out['min_date'] = (string) ($out['min_date'] ?? '');
        $out['max_date'] = (string) ($out['max_date'] ?? '');
        $out['unavailable_dates'] = array_values((array) ($out['unavailable_dates'] ?? []));
        $out['show_times'] = isset($out['show_times']) ? (bool) $out['show_times'] : (count($time) > 0);

        return $out;
    }
}
