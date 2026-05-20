<?php

namespace App\Policies\Clinic;

class VisitStockRequestPolicy extends BaseClinicFilamentPolicy
{
    protected static string $resourceKey = 'visit_stock_request';
}
