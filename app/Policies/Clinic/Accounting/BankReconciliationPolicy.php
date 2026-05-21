<?php

namespace App\Policies\Clinic\Accounting;

use App\Policies\Clinic\BaseClinicFilamentPolicy;

class BankReconciliationPolicy extends BaseClinicFilamentPolicy
{
    protected static string $resourceKey = 'accounting_bank_reconciliations';
}
