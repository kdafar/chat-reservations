<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookingAudienceResolver
{
    /**
     * Return the set of users that should receive a notification for a
     * newly created booking. Mirrors the visibility logic in
     * App\Models\Concerns\BelongsToBranchScope so each user only gets
     * notified for bookings they would actually see in the UI.
     *
     * Returns:
     *  - all admin / super_admin users
     *  - branch staff (branch_user pivot) of the booking's branch
     *  - partner managers (partner_user pivot) of the booking's branch's partner
     *  - the linked user of the booking's doctor (if any)
     */
    public function for(Booking $booking): Collection
    {
        $userIds = collect();

        // 1. Admin / super_admin (only roles that actually exist)
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

        return User::whereIn('id', $ids)->get();
    }
}
