<?php

namespace App\Policies\Clinic;

use App\Models\ClinicPackage;

class ClinicPackagePolicy extends BaseClinicFilamentPolicy
{
    protected static string $resourceKey = 'clinic_packages';
}
