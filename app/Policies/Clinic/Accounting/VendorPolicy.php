<?php

namespace App\Policies\Clinic\Accounting;

use App\Policies\Clinic\BaseClinicFilamentPolicy;

class VendorPolicy extends BaseClinicFilamentPolicy
{
    protected static string $resourceKey = 'accounting_vendors';
}
