<?php

namespace App\Observers\Accounting;

use App\Models\Insurance\InsuranceClaim;
use App\Services\Accounting\AccountingService;

/**
 * Keeps the insurer-coverage reclassification in step with a claim's
 * lifecycle. When a claim becomes (partially) approved the covered portion of
 * the patient receivable is moved into the insurer's AR; when it leaves an
 * approved state or the approved amount changes, the service re-states or
 * reverses the entry. Idempotent + error-swallowing inside the service.
 */
class InsuranceClaimAccountingObserver
{
    public function __construct(protected AccountingService $accounting) {}

    public function saved(InsuranceClaim $claim): void
    {
        // Only react when the decision status or the approved amount moved —
        // drafting a claim on every visit completion must stay GL-neutral.
        if (! $claim->wasChanged(['status', 'approved_amount'])) {
            return;
        }

        $this->accounting->recordClaimApprovalReclass($claim);
    }
}
