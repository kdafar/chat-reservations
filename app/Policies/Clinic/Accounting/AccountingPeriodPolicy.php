<?php

namespace App\Policies\Clinic\Accounting;

use App\Policies\Clinic\BaseClinicFilamentPolicy;

class AccountingPeriodPolicy extends BaseClinicFilamentPolicy
{
    protected static string $resourceKey = 'accounting_periods';
}
