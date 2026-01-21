<?php

namespace App\Services\WAFlow;

use App\Models\WhatsappFlowState;
use App\Models\WhatsappSession;

/**
 * FlowCtx: convenient read/write of flow context with DB winning over session.
 * - Token-first API for screens to survive navigation.
 * - Mirrors into WhatsappSession->context (for quick lookups & fallbacks).
 */
class FlowCtx
{
    public function __construct(private FlowStateStore $store) {}

    /** Back-compat: merge session + state (DB wins). */
    public function get(?WhatsappSession $session, ?WhatsappFlowState $state): array
    {
        $s = (array) ($session?->context ?? []);
        $d = (array) ($state?->data ?? []);

        return array_replace($s, $d);
    }

    /** Back-compat: write to both session + state. */
    public function save(?WhatsappSession $session, ?WhatsappFlowState $state, array $ctx): void
    {
        if ($session) {
            $session->update(['context' => $ctx, 'last_interacted_at' => now()]);
        }
        if ($state) {
            $this->store->merge($state, $ctx);
        }
    }

    /** Ensure a state row for this token, optionally binding msisdn & starting screen. */
    public function ensure(string $flowToken, ?string $msisdn, string $screen = 'APPOINTMENT'): ?WhatsappFlowState
    {
        return $this->store->getOrCreate($flowToken, $msisdn, $screen);
    }

    /** Get the unified ctx for a token (DB wins; session fills gaps if available). */
    public function all(string $flowToken): array
    {
        $state = $this->store->byToken($flowToken);
        if (! $state) {
            return [];
        }

        // Try to enrich with session (if any) so gaps are filled
        $session = $state->msisdn
            ? WhatsappSession::firstWhere('phone', $state->msisdn)
            : null;

        $s = (array) ($session?->context ?? []);
        $d = (array) ($state->data ?? []);

        return array_replace($s, $d); // DB wins
    }

    /**
     * Merge a patch into DB ctx; optionally mirror into session.
     * DB is source of truth, session is convenience.
     */
    public function put(string $flowToken, array $patch, ?WhatsappSession $session = null): void
    {
        $state = $this->store->byToken($flowToken);
        if (! $state) {
            // If we don't have a state yet, create one using session->phone if available
            $state = $this->store->getOrCreate($flowToken, $session?->phone, 'APPOINTMENT');
        }

        $this->store->merge($state, $patch);

        if ($session) {
            $ctx = array_replace((array) ($session->context ?? []), $patch);
            $session->update(['context' => $ctx, 'last_interacted_at' => now()]);
        }
    }

    /** Update the current screen for a token. */
    public function setScreen(string $flowToken, string $screen): void
    {
        if ($state = $this->store->byToken($flowToken)) {
            $this->store->setScreen($state, $screen);
        }
    }

    /** Remove keys from state (and mirror removal to session if we can resolve it). */
    public function clear(string $flowToken, array $keys): void
    {
        if (! $keys) {
            return;
        }

        $state = $this->store->byToken($flowToken);
        if (! $state) {
            return;
        }

        $this->store->clear($state, $keys);

        if ($state->msisdn) {
            if ($session = WhatsappSession::firstWhere('phone', $state->msisdn)) {
                $ctx = (array) ($session->context ?? []);
                foreach ($keys as $k) {
                    unset($ctx[$k]);
                }
                $session->update(['context' => $ctx, 'last_interacted_at' => now()]);
            }
        }
    }

    /** Hard delete the flow state row. */
    public function destroy(string $flowToken): void
    {
        if ($state = $this->store->byToken($flowToken)) {
            $this->store->destroy($state);
        }
    }
}
