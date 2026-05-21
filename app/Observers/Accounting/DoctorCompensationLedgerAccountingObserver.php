<?php

namespace App\Observers\Accounting;

use App\Models\DoctorCompensationLedger;
use App\Services\Accounting\AccountingService;

class DoctorCompensationLedgerAccountingObserver
{
    public function __construct(protected AccountingService $accounting) {}

    public function saved(DoctorCompensationLedger $ledger): void
    {
        // The ledger is upserted on every VisitCostingService::compute() pass.
        // We re-post on every save so that recomputes are reflected in the GL.
        $this->accounting->recordDoctorCompensation($ledger);
    }
}
