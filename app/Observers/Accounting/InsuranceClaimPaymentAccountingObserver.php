<?php

namespace App\Observers\Accounting;

use App\Models\Insurance\InsuranceClaimPayment;
use App\Services\Accounting\AccountingService;

/**
 * Mirrors VisitPaymentAccountingObserver: created fires the JE post,
 * deleted (soft) reverses it. Wire-up lives in AppServiceProvider.
 */
class InsuranceClaimPaymentAccountingObserver
{
    public function __construct(protected AccountingService $accounting) {}

    public function created(InsuranceClaimPayment $payment): void
    {
        $this->accounting->recordInsurerPayment($payment);
    }

    public function deleted(InsuranceClaimPayment $payment): void
    {
        // Soft-delete on the model — reverse the posted entry, if any.
        $this->accounting->recordInsurerPaymentReversal($payment, 'Insurer payment deleted');
    }
}
