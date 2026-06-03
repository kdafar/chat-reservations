<?php

namespace App\Services\Insurance;

use App\Models\Insurance\InsuranceClaim;
use InvalidArgumentException;

/**
 * State-transition guard for InsuranceClaim.
 *
 * Modelled after the post()/reverse() guards in JournalEntry — invalid
 * transitions throw immediately so callers can't accidentally jump a
 * claim from e.g. 'rejected' back to 'submitted'. Void is the only
 * sink-state reachable from anywhere except itself.
 */
class ClaimStateMachine
{
    /**
     * Allowed transitions: from => [to, to, ...]
     *
     * 'paid' → 'void' is permitted (admin reversal scenario) but callers
     * are expected to log the reason — the service layer will write a
     * state-log row so the trail is preserved.
     */
    public const TRANSITIONS = [
        InsuranceClaim::STATUS_DRAFT => [
            InsuranceClaim::STATUS_SUBMITTED,
            InsuranceClaim::STATUS_VOID,
        ],
        InsuranceClaim::STATUS_SUBMITTED => [
            InsuranceClaim::STATUS_UNDER_REVIEW,
            InsuranceClaim::STATUS_APPROVED,
            InsuranceClaim::STATUS_PARTIALLY_APPROVED,
            InsuranceClaim::STATUS_REJECTED,
            InsuranceClaim::STATUS_VOID,
        ],
        InsuranceClaim::STATUS_UNDER_REVIEW => [
            InsuranceClaim::STATUS_APPROVED,
            InsuranceClaim::STATUS_PARTIALLY_APPROVED,
            InsuranceClaim::STATUS_REJECTED,
            InsuranceClaim::STATUS_VOID,
        ],
        InsuranceClaim::STATUS_APPROVED => [
            InsuranceClaim::STATUS_PAID,
            InsuranceClaim::STATUS_VOID,
        ],
        InsuranceClaim::STATUS_PARTIALLY_APPROVED => [
            InsuranceClaim::STATUS_PAID,
            InsuranceClaim::STATUS_VOID,
        ],
        InsuranceClaim::STATUS_REJECTED => [
            InsuranceClaim::STATUS_VOID,
        ],
        // Voiding a paid claim is allowed but must be logged by the caller.
        InsuranceClaim::STATUS_PAID => [
            InsuranceClaim::STATUS_VOID,
        ],
        InsuranceClaim::STATUS_VOID => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        if (! array_key_exists($from, self::TRANSITIONS)) {
            return false;
        }

        return in_array($to, self::TRANSITIONS[$from], true);
    }

    /**
     * Throws if the transition isn't allowed. Use at the top of any
     * state-mutating service method.
     */
    public function assertTransition(string $from, string $to): void
    {
        if (! array_key_exists($from, self::TRANSITIONS)) {
            throw new InvalidArgumentException("Unknown claim status: {$from}");
        }

        if (! $this->canTransition($from, $to)) {
            $allowed = self::TRANSITIONS[$from];
            $allowedList = empty($allowed) ? '<none — terminal state>' : implode(', ', $allowed);

            throw new InvalidArgumentException(
                "Illegal claim transition: {$from} → {$to}. Allowed from {$from}: {$allowedList}."
            );
        }
    }

    /**
     * @return array<int, string>
     */
    public function allowedNextStates(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }
}
