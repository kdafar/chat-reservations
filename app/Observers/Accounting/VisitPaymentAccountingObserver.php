<?php

namespace App\Observers\Accounting;

use App\Models\VisitPayment;
use App\Services\Accounting\AccountingService;

class VisitPaymentAccountingObserver
{
    public function __construct(protected AccountingService $accounting) {}

    public function created(VisitPayment $payment): void
    {
        $this->accounting->recordVisitPayment($payment);
    }

    public function updated(VisitPayment $payment): void
    {
        // Status changes (paid → refunded / void) need to re-post or reverse.
        if ($payment->wasChanged('status')) {
            $this->accounting->recordVisitPayment($payment);
        }
    }
}
