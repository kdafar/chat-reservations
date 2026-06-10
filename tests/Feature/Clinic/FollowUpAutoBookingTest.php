<?php

namespace Tests\Feature\Clinic;

use App\Models\Booking;
use App\Models\BranchAvailabilityRule;
use App\Models\FollowUpPlan;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\Clinic\FollowUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

/**
 * Phase 7 — when a doctor sets a follow-up date, auto-book the first FREE slot
 * that day against the doctor's real availability; if the doctor is off or fully
 * booked, flag the plan needs_scheduling rather than creating a bad booking.
 */
class FollowUpAutoBookingTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        config(['clinic.follow_up_enabled' => true]);
    }

    /** A date a week out (avoids today's lead-time truncation) + its 0-based DOW. */
    private function futureDate(): array
    {
        $date = Carbon::today(config('app.timezone'))->addDays(7);
        $dow = $date->dayOfWeekIso;          // 1..7
        $dowZero = $dow === 7 ? 0 : $dow;    // 0..6

        return [$date->toDateString(), $dowZero];
    }

    private function openBranchOn(int $dowZero): void
    {
        $f = $this->seedClinicFixtures();
        BranchAvailabilityRule::create([
            'branch_id' => $f['branch']->id,
            'day_of_week' => $dowZero,
            'is_open' => true,
            'open_at' => '09:00',
            'close_at' => '17:00',
            'slot_step_minutes' => 30,
            'slot_length_minutes' => 30,
            'max_party_size' => 10,
            'lead_time_minutes' => 0,
        ]);
    }

    private function doctorWorksOn(int $dowZero): void
    {
        $f = $this->seedClinicFixtures();
        $f['doctor']->forceFill(['working_hours' => [['day' => $dowZero, 'start' => '09:00', 'end' => '17:00']]])->save();
    }

    public function test_auto_books_the_first_free_slot_when_doctor_is_available(): void
    {
        [$date, $dowZero] = $this->futureDate();
        $this->openBranchOn($dowZero);
        $this->doctorWorksOn($dowZero);

        $visit = $this->makeVisit(['follow_up_date' => $date]);

        $plan = app(FollowUpService::class)->syncFromVisit($visit, true);

        $this->assertSame('booked', $plan->status);
        $this->assertNotNull($plan->booking_id);

        $booking = Booking::find($plan->booking_id);
        $this->assertSame($date, $booking->res_date->toDateString());
        $this->assertSame('09:00:00', $booking->res_time);   // first slot of the day
        $this->assertSame('follow_up', $booking->source);
        $this->assertSame((int) $visit->doctor_id, (int) $booking->doctor_id);
    }

    public function test_flags_needs_scheduling_when_doctor_not_working_that_day(): void
    {
        [$date, $dowZero] = $this->futureDate();
        // Branch open, but the doctor has NO working hours that day → no free slots.
        $this->openBranchOn($dowZero);

        $visit = $this->makeVisit(['follow_up_date' => $date]);

        $plan = app(FollowUpService::class)->syncFromVisit($visit, true);

        $this->assertSame('needs_scheduling', $plan->status);
        $this->assertNull($plan->booking_id);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_resync_preserves_booking_and_reschedules_on_date_change(): void
    {
        $f = $this->seedClinicFixtures();
        [$date1, $dow1] = $this->futureDate();
        $date2 = Carbon::parse($date1)->addDay()->toDateString();
        $dow2 = Carbon::parse($date2)->dayOfWeekIso;
        $dow2 = $dow2 === 7 ? 0 : $dow2;

        $this->openBranchOn($dow1);
        $this->openBranchOn($dow2);
        $f['doctor']->forceFill(['working_hours' => [
            ['day' => $dow1, 'start' => '09:00', 'end' => '17:00'],
            ['day' => $dow2, 'start' => '09:00', 'end' => '17:00'],
        ]])->save();

        $svc = app(FollowUpService::class);
        $visit = $this->makeVisit(['follow_up_date' => $date1]);

        $plan = $svc->syncFromVisit($visit, true);
        $this->assertSame('booked', $plan->status);
        $firstBooking = $plan->booking_id;
        $this->assertNotNull($firstBooking);

        // Re-sync with the SAME schedule → must stay booked on the SAME booking
        // (the bug: it used to reset to 'open' and leave the booking stale).
        $plan = $svc->syncFromVisit($visit, true);
        $this->assertSame('booked', $plan->status);
        $this->assertSame($firstBooking, $plan->booking_id);

        // Change the follow-up date (in memory) → reschedule: old booking cancelled, new one made.
        $visit->follow_up_date = $date2;
        $plan = $svc->syncFromVisit($visit, true);
        $this->assertSame('booked', $plan->status);
        $this->assertNotSame($firstBooking, $plan->booking_id, 'a new booking should back the new date');
        $this->assertSame('cancelled', Booking::find($firstBooking)->status, 'stale booking must be cancelled');
        $this->assertSame($date2, Booking::find($plan->booking_id)->res_date->toDateString());
    }

    public function test_skips_when_patient_has_no_phone(): void
    {
        [$date, $dowZero] = $this->futureDate();
        $this->openBranchOn($dowZero);
        $this->doctorWorksOn($dowZero);

        $f = $this->seedClinicFixtures();
        $noPhone = Patient::create(['partner_id' => $f['partner']->id, 'name' => 'No Phone', 'phone' => '']);
        $visit = Visit::create([
            'patient_id' => $noPhone->id,
            'doctor_id' => $f['doctor']->id,
            'branch_id' => $f['branch']->id,
            'status' => 'completed',
            'checked_in_at' => now()->subHour(),
            'completed_at' => now(),
            'follow_up_date' => $date,
        ]);

        $plan = app(FollowUpService::class)->syncFromVisit($visit, true);

        $this->assertSame('open', $plan->status); // not booked, not flagged — just left open
        $this->assertNull($plan->booking_id);
        $this->assertDatabaseCount('bookings', 0);
    }
}
