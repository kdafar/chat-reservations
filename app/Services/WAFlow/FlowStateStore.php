<?php

namespace App\Services\WAFlow;

use App\Models\WhatsappFlowState;

class FlowStateStore
{
    /** Lightweight getter by token. */
    public function byToken(?string $token): ?WhatsappFlowState
    {
        if (! $token) {
            return null;
        }

        return WhatsappFlowState::byToken($token)->first();
    }

    /** Create or refresh the state for a token (binds msisdn and starting screen if new). */
    public function getOrCreate(?string $token, ?string $msisdn, string $screen = 'APPOINTMENT'): ?WhatsappFlowState
    {
        if (! $token) {
            return null;
        }

        $state = $this->byToken($token);
        if ($state) {
            return $this->touch($state);
        }

        if (! $msisdn) {
            return null;
        }

        $state = new WhatsappFlowState([
            'flow_token' => $token,
            'msisdn' => $msisdn,
            'screen' => $screen,
            'data' => [],
            'expires_at' => now()->addHour(),
        ]);
        $state->save();

        return $state;
    }

    /** Extend TTL. */
    public function touch(WhatsappFlowState $state): WhatsappFlowState
    {
        $state->expires_at = now()->addHour();
        $state->save();

        return $state;
    }

    /** Update just the screen (and extend TTL). */
    public function setScreen(?WhatsappFlowState $state, string $screen): void
    {
        if (! $state) {
            return;
        }

        $state->screen = $screen;
        $this->touch($state);
    }

    /**
     * Merge a patch into data (null values remove keys).
     * Requires WhatsappFlowState::mergeData(array $patch) to handle removals.
     */
    public function merge(?WhatsappFlowState $state, array $patch): void
    {
        if (! $state) {
            return;
        }

        $state->mergeData($patch);
        $this->touch($state);
    }

    /** Remove specific keys from data. */
    public function clear(?WhatsappFlowState $state, array $keys): void
    {
        if (! $state || ! $keys) {
            return;
        }

        $data = (array) ($state->data ?? []);
        foreach ($keys as $k) {
            unset($data[$k]);
        }
        $state->data = $data;
        $this->touch($state);
    }

    /** Hard delete. */
    public function destroy(?WhatsappFlowState $state): void
    {
        if (! $state) {
            return;
        }

        $state->delete();
    }
}
