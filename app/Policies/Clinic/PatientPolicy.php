<?php

namespace App\Policies\Clinic;

class PatientPolicy extends BaseClinicFilamentPolicy
{
    protected static string $resourceKey = 'patients';
}
