<?php

namespace App\Services\WAFlow;

use App\Models\WhatsappFlowState;
use App\Models\WhatsappSession;
use App\Services\WAFlow\Screen\AppointmentScreen;
use App\Services\WAFlow\Screen\DetailsScreen;
use App\Services\WAFlow\Screen\SummaryScreen;
use Illuminate\Support\Facades\Log;

class FlowRouter
{
    public function __construct(
        private AppointmentScreen $appointment,
        private DetailsScreen $details,
        private SummaryScreen $summary,
        private FlowCrypto $crypto,
        private FlowValidator $validator,

        // DB state wiring
        private FlowStateStore $store,
        private FlowCtx $ctx,
    ) {}

    public function route(FlowRequest $req, ?WhatsappSession $session, string $locale): array
    {
        // ----- Client error echo (Meta reports our last payload invalid) -----
        if (isset($req->data['error'])) {
            Log::warning('Flow: client error', ['data' => $req->data]);

            // Keep user on current screen, but acknowledge so client recovers
            return $this->frame($req, $req->screen ?: 'APPOINTMENT', ['acknowledged' => true]);
        }

        // ----- Health / INIT without token (Meta probes) -----
        if (($req->action === 'PING' || $req->action === 'INIT')
            && $req->flowToken === ''
            && ($req->screen === '' || $req->screen === null)) {
            Log::info('Flow: health ping/INIT (empty flow_token) → status:active');

            return ['data' => ['status' => 'active']];
        }

        // ----- PING (keep state warm) -----
        if ($req->action === 'PING') {
            $state = $req->flowToken !== ''
                ? $this->store->getOrCreate($req->flowToken, $session?->phone, $req->screen ?: 'APPOINTMENT')
                : null;

            if ($state) {
                $this->store->setScreen($state, $req->screen ?: ($state->screen ?: 'APPOINTMENT'));
            }

            return $this->frame($req, $req->screen ?: 'APPOINTMENT', ['status' => 'active']);
        }

        // ----- INIT → APPOINTMENT -----
        if ($req->action === 'INIT') {
            Log::info('Flow: INIT → APPOINTMENT');

            $state = $this->store->getOrCreate($req->flowToken, $session?->phone, 'APPOINTMENT');
            $this->store->setScreen($state, 'APPOINTMENT');

            // IMPORTANT: pass flow_token (your other calls do)
            $data = $this->appointment->build($session, $locale, $req->flowToken);

            return $this->frame($req, 'APPOINTMENT', $data);
        }

        // ----- DATA_EXCHANGE -----
        if ($req->action === 'DATA_EXCHANGE') {
            // Ensure a DB state even if user jumped screens
            $state = $this->store->getOrCreate(
                $req->flowToken,
                $session?->phone,
                $req->screen ?: 'APPOINTMENT'
            );

            switch ($req->screen) {
                case 'APPOINTMENT':
                    $result = $this->appointment->exchange($req, $session, $locale);

                    return $this->formatResult($req, 'APPOINTMENT', $result, $state);

                case 'DETAILS':
                    $result = $this->details->exchange($req, $session, $locale);

                    return $this->formatResult($req, 'DETAILS', $result, $state);

                case 'SUMMARY':
                    $result = $this->summary->exchange($req, $session, $locale);

                    return $this->formatResult($req, 'SUMMARY', $result, $state);

                case 'CONFIRMATION':
                    // Usually returned by SUMMARY; treat as no-op render
                    $this->store->setScreen($state, 'CONFIRMATION');

                    return $this->frame($req, 'CONFIRMATION', (object) []);

                default:
                    // Unknown screen → rebuild APPOINTMENT
                    $this->store->setScreen($state, 'APPOINTMENT');
                    $data = $this->appointment->build($session, $locale, $req->flowToken);

                    return $this->frame($req, 'APPOINTMENT', $data);

            }
        }

        // ----- BACK / fallback -----
        $state = $this->store->getOrCreate($req->flowToken, $session?->phone, $req->screen ?: 'APPOINTMENT');
        $to = in_array($req->screen, ['APPOINTMENT', 'DETAILS', 'SUMMARY', 'CONFIRMATION'], true)
            ? $req->screen
            : 'APPOINTMENT';
        $this->store->setScreen($state, $to ?: 'APPOINTMENT');

        return $this->frame($req, $to ?: 'APPOINTMENT', (object) []);
    }

    /**
     * Normalize screen results:
     * - ['__nav'=>'DETAILS','__data'=>[..]] → navigate to another screen
     * - ['screen'=>'X','data'=>[..]]        → already framed; validate + frame
     * - else                                 → stay on current screen
     */
    private function formatResult(FlowRequest $req, string $currentScreen, array $result, ?WhatsappFlowState $state = null): array
    {
        if (isset($result['__nav'])) {
            $next = (string) $result['__nav'];
            $data = (array) ($result['__data'] ?? []);
            Log::info('Flow: router nav', ['from' => $currentScreen, 'to' => $next, 'keys' => array_keys($data)]);

            if ($state) {
                $this->store->setScreen($state, $next);
            }

            return $this->frame($req, $next, $data);
        }

        if (isset($result['screen']) && array_key_exists('data', $result)) {
            $screen = (string) $result['screen'];
            if ($state) {
                $this->store->setScreen($state, $screen);
            }

            return $this->frame($req, $screen, (array) $result['data']);
        }

        if ($state) {
            $this->store->setScreen($state, $currentScreen);
        }

        return $this->frame($req, $currentScreen, $result);
    }

    private function frame(FlowRequest $req, string $screen, array|object $data): array
    {
        $arr = is_array($data) ? $data : (array) $data;

        // Validate/sanitize against schema for this screen
        $vr = $this->validator->assert($screen, $arr);
        if (! $vr->ok) {
            Log::warning('Flow: validator errors', ['screen' => $screen, 'errors' => $vr->errors]);

            if (config('services.whatsapp.flows.validator_strict', true)) {
                $safe = $this->validator->safeAppointmentPayload();

                return [
                    'version' => $req->version,
                    'flow_token' => $req->flowToken,
                    'screen' => 'APPOINTMENT',
                    'data' => $safe,
                ];
            }

            $arr = $vr->normalized;
        }

        return [
            'version' => $req->version,
            'flow_token' => $req->flowToken,
            'screen' => $screen,
            'data' => $arr,
        ];
    }
}
