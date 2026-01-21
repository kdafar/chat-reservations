<?php

namespace App\Policies\Clinic;

class BranchBlackoutPolicy extends BaseClinicFilamentPolicy
{
    protected static string $resourceKey = 'branch_blackout';
}
