<?php

namespace App\Policies\Clinic\Accounting;

use App\Policies\Clinic\BaseClinicFilamentPolicy;

class ExpensePolicy extends BaseClinicFilamentPolicy
{
    protected static string $resourceKey = 'accounting_expenses';
}
