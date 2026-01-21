<?php

namespace App\Policies\Clinic;

class BookingPolicy extends BaseClinicFilamentPolicy
{
    protected static string $resourceKey = 'bookings';
}
