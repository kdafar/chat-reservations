<?php

namespace App\Policies\Clinic\Accounting;

use App\Policies\Clinic\BaseClinicFilamentPolicy;

class AccountPolicy extends BaseClinicFilamentPolicy
{
    protected static string $resourceKey = 'accounting_accounts';
}
