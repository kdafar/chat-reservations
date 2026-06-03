<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Lightweight live counters for the v2 topbar status chips. Polled client-side
 * (see SnapshotChips.vue) every ~45s. Three cheap, indexed COUNT queries — all
 * branch-scoped automatically by BelongsToBranchScope on Visit/Booking, so each
 * user only ever sees their own branches' numbers.
 */
class SummaryController extends Controller
{
    public function summary(): JsonResponse
    {
        // These are operational queue/billing counts. Staff without visit
        // access (e.g. an accounting-only role) get zeros rather than a 403 —
        // the poller runs on every page, so a hard abort would noisily break
        // the topbar. Reception / doctors / managers / admins all hold this.
        if (! auth()->user()?->can('view_any_visits')) {
            return response()->json(['waiting' => 0, 'unpaid' => 0, 'bookings_today' => 0]);
        }

        $tz = config('app.timezone', 'Asia/Kuwait');
        $today = Carbon::today($tz)->toDateString();

        // Live queue: anyone checked in and not yet paid/done. Must mirror the
        // Waiting Patients page's queueQuery exactly (same statuses + the
        // completed_at guard) or the chip and the page disagree — a stale visit
        // left in an active status with completed_at set would otherwise be
        // counted here but excluded from the page.
        $waiting = Visit::query()
            ->whereIn('status', [
                Visit::STATUS_AWAITING_DOCTOR,
                Visit::STATUS_IN_PROGRESS,
                Visit::STATUS_AWAITING_STOCK,
            ])
            ->whereNull('completed_at')
            ->count();

        // Visits sitting in "awaiting payment" — money still to collect today.
        $unpaid = Visit::query()
            ->where('status', Visit::STATUS_AWAITING_PAYMENT)
            ->count();

        // Everything booked for today, regardless of status.
        $bookingsToday = Booking::query()
            ->whereDate('res_date', $today)
            ->count();

        return response()->json([
            'waiting' => $waiting,
            'unpaid' => $unpaid,
            'bookings_today' => $bookingsToday,
        ]);
    }
}
