<?php

namespace Database\Seeders\Demo;

use App\Models\Booking;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives follow-up appointments an outcome.
 *
 * FollowUpService auto-books a slot whenever a doctor sets a follow-up date, so
 * the estate accumulated ~1,500 bookings on source `follow_up` that were never
 * resolved — every one still `pending`, more than a thousand of them in the
 * past. The appointments report showed the whole channel with a null show rate
 * and the follow-up funnel converted at zero, neither of which reflects how the
 * clinic actually runs.
 *
 * Two moves, both keeping bookings and visits consistent with each other:
 *   1. Re-attribute a slice of already-attended bookings to `follow_up`, but only
 *      where the patient genuinely had an earlier visit. Those carry a real visit
 *      behind them, so the channel gains attended volume without inventing one.
 *   2. Close out the stale pending ones as no-shows or cancellations, which is
 *      what an unactioned follow-up really is.
 */
class DemoFollowUpOutcomeSeeder extends Seeder
{
    public function run(): void
    {
        DB::connection()->disableQueryLog();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }

        $this->reattributeAttended();
        $this->closeStalePending();
    }

    /** Point a share of genuine repeat visits at the follow-up channel. */
    protected function reattributeAttended(): void
    {
        $already = Booking::query()->where('source', 'follow_up')->where('status', 'completed')->count();
        if ($already > 100) {
            $this->command?->warn('DemoFollowUpOutcomeSeeder: follow-up attendance already attributed — skipping.');

            return;
        }

        // A booking only counts as a follow-up if the patient had been seen
        // before it. Anything else would be a first visit mislabelled.
        $candidates = DB::table('bookings as b')
            ->join('visits as v', 'v.booking_id', '=', 'b.id')
            ->where('b.status', 'completed')
            ->whereIn('b.source', ['reception', 'phone', 'whatsapp'])
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('visits as prior')
                    ->whereColumn('prior.patient_id', 'v.patient_id')
                    ->whereColumn('prior.computed_at', '<', 'v.computed_at')
                    ->where('prior.status', 'completed');
            })
            ->orderBy('b.id')
            ->pluck('b.id')
            ->all();

        if (! $candidates) {
            $this->command?->warn('DemoFollowUpOutcomeSeeder: no repeat-visit bookings to attribute.');

            return;
        }

        // Roughly a third of repeat attendance comes from a booked follow-up;
        // the rest is the patient calling in on their own.
        $take = array_values(array_filter($candidates, fn ($id, $i) => $i % 3 === 0, ARRAY_FILTER_USE_BOTH));

        foreach (array_chunk($take, 500) as $chunk) {
            DB::table('bookings')->whereIn('id', $chunk)->update(['source' => 'follow_up']);
            DB::table('visits')->whereIn('booking_id', $chunk)->update(['source' => 'follow_up']);
        }

        $this->command?->info('DemoFollowUpOutcomeSeeder: attributed '.count($take).' attended bookings to the follow-up channel.');
    }

    /** A follow-up nobody acted on is a no-show or a cancellation, not "pending". */
    protected function closeStalePending(): void
    {
        $stale = Booking::query()
            ->where('source', 'follow_up')
            ->where('status', 'pending')
            ->where('res_start', '<', Carbon::now())
            ->orderBy('id')
            ->get(['id', 'res_start']);

        if ($stale->isEmpty()) {
            return;
        }

        $noShow = [];
        $cancelled = [];
        foreach ($stale as $i => $booking) {
            // Most lapsed follow-ups are quietly cancelled by reception when the
            // patient reschedules or stops answering; a minority are true
            // no-shows where the slot was held and wasted.
            if ($i % 5 < 2) {
                $noShow[] = $booking->id;
            } else {
                $cancelled[] = $booking->id;
            }
        }

        foreach (array_chunk($noShow, 500) as $chunk) {
            DB::table('bookings')->whereIn('id', $chunk)->update([
                'status' => 'no_show',
                'no_show_at' => DB::raw('res_start'),
            ]);
        }
        foreach (array_chunk($cancelled, 500) as $chunk) {
            DB::table('bookings')->whereIn('id', $chunk)->update([
                'status' => 'cancelled',
                'cancelled_at' => DB::raw('res_start'),
                'cancellation_reason_code' => 'patient_rescheduled',
            ]);
        }

        // Anything still ahead of today is a live appointment, not a loose end.
        DB::table('bookings')->where('source', 'follow_up')->where('status', 'pending')
            ->where('res_start', '>=', Carbon::now())->update(['status' => 'confirmed']);

        $this->command?->info(sprintf(
            'DemoFollowUpOutcomeSeeder: closed %d lapsed follow-ups (%d no-show, %d cancelled).',
            $stale->count(), count($noShow), count($cancelled),
        ));
    }
}
