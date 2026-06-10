<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookingAudienceResolver
{
    /**
     * Roles whose holders can actually open and manage the bookings page.
     * Mirrors VisitAuthorization::canManageBooking() (admin / reception).
     * Booking notifications deep-link to /admin/v2/bookings, which aborts
     * for anyone outside this set, so we must not notify them.
     */
    protected const BOOKING_ROLES = ['super_admin', 'admin', 'clinic_admin', 'clinic_reception'];

    /**
     * Return the set of users that should receive a notification for a
     * newly created booking. Restricted to users who can actually open the
     * bookings page (see self::BOOKING_ROLES) AND are scoped to the booking
     * — so e.g. a doctor or nurse who happens to be branch staff does NOT
     * get a notification whose link they'd only get a 403 from.
     *
     * Returns the intersection of (booking-capable role) with:
     *  - all admin / super_admin users (global, every branch)
     *  - branch staff (branch_user pivot) of the booking's branch
     *  - partner managers (partner_user pivot) of the booking's branch's partner
     */
    public function for(Booking $booking): Collection
    {
        $userIds = collect();

        // 1. Global admins (admin / super_admin see every branch's bookings).
        $adminRoles = \Spatie\Permission\Models\Role::whereIn('name', ['admin', 'super_admin'])
            ->pluck('name')
            ->all();

        if (! empty($adminRoles)) {
            $userIds = $userIds->merge(
                User::role($adminRoles)->pluck('id')
            );
        }

        // 2. Branch staff
        if ($booking->branch_id) {
            $userIds = $userIds->merge(
                DB::table('branch_user')
                    ->where('branch_id', $booking->branch_id)
                    ->pluck('user_id')
            );

            // 3. Partner managers (via partner_user pivot)
            $partnerId = DB::table('branches')
                ->where('id', $booking->branch_id)
                ->value('partner_id');

            if ($partnerId) {
                $userIds = $userIds->merge(
                    DB::table('partner_user')
                        ->where('partner_id', $partnerId)
                        ->pluck('user_id')
                );
            }
        }

        // Note: the doctor's linked user is intentionally excluded here —
        // doctors are notified separately when the patient pays the
        // consultation fee (see VisitPaymentObserver).

        $ids = $userIds->filter()->unique()->values()->all();

        if (empty($ids)) {
            return collect();
        }

        // Final gate: only users who actually hold a booking-capable role.
        // This drops doctors / nurses / other branch staff who would land on
        // a 403 when opening the booking link. Filter to roles that exist so
        // Spatie's role() scope can't throw on a missing role name.
        $bookingRoles = \Spatie\Permission\Models\Role::whereIn('name', self::BOOKING_ROLES)
            ->pluck('name')
            ->all();

        if (empty($bookingRoles)) {
            return collect();
        }

        return User::whereIn('id', $ids)->role($bookingRoles)->get();
    }
}
