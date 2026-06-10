<?php

namespace App\Policies\Clinic;

class ClinicPaymentMethodPolicy extends BaseClinicFilamentPolicy
{
    protected static string $resourceKey = 'clinic_payment_methods';
}
