<?php

namespace Tests\Feature\Clinic;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchAvailabilityRule;
use App\Models\Doctor;
use App\Models\Partner;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\Clinic\WorkingHoursService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regressions for the scheduling audit.
 *
 * These all share one theme: the slot grid and the booking guard have to agree,
 * and neither may quietly answer a different question than it was asked —
 * because the caller's own role narrowed a query, because a row was soft-deleted
 * out from under it, or because the branch was never a restaurant to begin with.
 */
class SchedulingGuardsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Doctor $doctor;

    private Doctor $colleague;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $partner = Partner::create([
            'name' => ['en' => 'Clinic', 'ar' => 'عيادة'],
            'slug' => 'clinic-'.uniqid(),
        ]);
        $this->branch = Branch::create([
            'partner_id' => $partner->id,
            'name' => ['en' => 'Main', 'ar' => 'الرئيسي'],
            'slug' => 'main-'.uniqid(),
            'is_available' => true,
            'max_booking_days' => 30,
        ]);

        // Open every day 09:00–17:00 on a 15-minute grid.
        foreach (range(0, 6) as $dow) {
            BranchAvailabilityRule::create([
                'branch_id' => $this->branch->id,
                'day_of_week' => $dow,
                'is_open' => true,
                'open_at' => '09:00:00',
                'close_at' => '17:00:00',
                'slot_length_minutes' => 15,
                'slot_step_minutes' => 15,
                'lead_time_minutes' => 0,
            ]);
        }

        $hours = array_map(
            fn ($d) => ['day' => $d, 'start' => '09:00', 'end' => '17:00'],
            range(0, 6),
        );

        $this->doctor = $this->makeDoctor($partner->id, 'Dr One', $hours);
        $this->colleague = $this->makeDoctor($partner->id, 'Dr Two', $hours);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@guards.local',
            'password' => 'secret123', 'status' => 'active',
        ]);
        $role = Role::findOrCreate('admin', 'web');
        foreach (['view_any_doctors', 'update_doctors', 'view_any_branch', 'view_any_booking'] as $p) {
            $role->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        $this->admin->assignRole($role);
    }

    private function makeDoctor(int $partnerId, string $name, array $hours): Doctor
    {
        return Doctor::create([
            'partner_id' => $partnerId,
            'branch_id' => $this->branch->id,
            'name' => $name,
            'specialty' => 'General',
            'consultation_fee' => 10,
            'is_active' => true,
            'working_hours' => $hours,
        ]);
    }

    private function svc(): WorkingHoursService
    {
        return app(WorkingHoursService::class);
    }

    private function avail(): AvailabilityService
    {
        return app(AvailabilityService::class);
    }

    private function soon(): string
    {
        return Carbon::now(config('app.timezone'))->addDays(3)->toDateString();
    }

    /* ---------------- the branch scope must not narrow a schedule ---------------- */

    public function test_a_doctor_user_can_see_a_colleagues_slots(): void
    {
        $user = User::create([
            'name' => 'Dr One', 'email' => 'one@guards.local',
            'password' => 'secret123', 'status' => 'active',
        ]);
        $this->doctor->update(['user_id' => $user->id]);

        $asGuest = $this->avail()->timesFor($this->branch->id, $this->soon(), 1, $this->colleague->id);
        $this->assertNotEmpty($asGuest, 'sanity: the colleague has slots');

        $this->actingAs($user);

        $this->assertCount(
            count($asGuest),
            $this->avail()->timesFor($this->branch->id, $this->soon(), 1, $this->colleague->id),
            'A doctor-role user saw an empty grid for a colleague: the branch scope narrowed the doctors table to themselves.',
        );
    }

    public function test_a_doctor_user_still_sees_a_colleagues_clashes(): void
    {
        $user = User::create([
            'name' => 'Dr One', 'email' => 'one2@guards.local',
            'password' => 'secret123', 'status' => 'active',
        ]);
        $this->doctor->update(['user_id' => $user->id]);

        $date = $this->soon();
        Booking::create([
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->colleague->id,
            'msisdn' => '96500000000',
            'party_size' => 1,
            'res_date' => $date,
            'res_time' => '10:00:00',
            'res_start' => $date.' 10:00:00',
            'res_end' => $date.' 10:15:00',
            'status' => Booking::S_CONFIRMED,
            'booking_code' => 'CLASH001',
        ]);

        $this->actingAs($user);

        $times = array_column($this->avail()->timesFor($this->branch->id, $date, 1, $this->colleague->id), 'value');

        $this->assertNotContains('10:00:00', $times,
            "The bookings scope hid a colleague's appointment, so the taken slot was offered again.");
    }

    /* ---------------- who may be booked ---------------- */

    public function test_a_deactivated_doctor_cannot_be_booked(): void
    {
        $this->doctor->update(['is_active' => false]);

        $this->assertNotNull(
            $this->svc()->bookingProblem($this->branch->id, $this->doctor->id, $this->soon(), '10:00'),
            'A deactivated doctor was still bookable through the guard.',
        );
        $this->assertEmpty($this->avail()->timesFor($this->branch->id, $this->soon(), 1, $this->doctor->id));
    }

    public function test_a_soft_deleted_doctor_cannot_be_booked(): void
    {
        $this->doctor->delete();

        $this->assertNotNull(
            $this->svc()->bookingProblem($this->branch->id, $this->doctor->id, $this->soon(), '10:00'),
            'withoutGlobalScopes() drops SoftDeletingScope too, so a deleted doctor passed the guard.',
        );
    }

    public function test_a_disabled_branch_cannot_be_booked(): void
    {
        $this->branch->update(['is_available' => false]);

        $this->assertNotNull(
            $this->svc()->bookingProblem($this->branch->id, $this->doctor->id, $this->soon(), '10:00'),
            'A branch switched off still accepted bookings.',
        );
        $this->assertEmpty($this->avail()->timesFor($this->branch->id, $this->soon(), 1, $this->doctor->id));
    }

    /* ---------------- the grid decides the calendar ---------------- */

    public function test_bookable_dates_do_not_depend_on_restaurant_tables(): void
    {
        // A clinic has no tables at all. The date list must still fill up from
        // the doctors' schedules rather than from table capacity.
        $this->assertNotEmpty(
            $this->avail()->nextDates($this->branch->id, 14, 1),
            'A clinic with no restaurant_tables rows had no bookable dates.',
        );
    }

    public function test_bookable_dates_ignore_party_size_for_a_clinic(): void
    {
        $one = $this->avail()->nextDates($this->branch->id, 14, 1);
        $many = $this->avail()->nextDates($this->branch->id, 14, 6);

        $this->assertSame(array_keys($one), array_keys($many),
            'Party size changed a clinic calendar, which only table capacity should ever do.');
    }

    public function test_a_date_with_no_doctor_on_shift_is_not_offered(): void
    {
        $friday = Carbon::now(config('app.timezone'))->next(Carbon::FRIDAY);
        $dow = (int) $friday->dayOfWeek;

        foreach ([$this->doctor, $this->colleague] as $d) {
            $d->update([
                'working_hours' => array_values(array_filter(
                    $d->working_hours,
                    fn ($r) => (int) $r['day'] !== $dow,
                )),
            ]);
        }

        $this->assertArrayNotHasKey($friday->toDateString(), $this->avail()->nextDates($this->branch->id, 14, 1),
            'A day nobody works was still offered as a bookable date.');
    }

    /* ---------------- the guard accepts exactly what the grid offers ---------------- */

    public function test_the_guard_accepts_every_offered_slot(): void
    {
        $date = $this->soon();
        $offered = array_column($this->avail()->timesFor($this->branch->id, $date, 1, $this->doctor->id), 'value');

        $this->assertNotEmpty($offered);

        foreach ($offered as $slot) {
            $this->assertNull(
                $this->svc()->bookingProblem($this->branch->id, $this->doctor->id, $date, substr($slot, 0, 5)),
                "The grid offered {$slot} but the guard rejected it.",
            );
        }
    }

    public function test_an_off_grid_time_is_rejected(): void
    {
        $this->assertNotNull(
            $this->svc()->bookingProblem($this->branch->id, $this->doctor->id, $this->soon(), '11:07'),
            'A time that is not on the 15-minute grid was accepted, and would straddle two slots.',
        );
    }

    public function test_the_guard_matches_the_grid_at_the_doctors_own_length(): void
    {
        // 20 minutes does not divide the hour, so an absolute-clock grid and a
        // window-relative one disagree from the third slot onwards.
        $this->doctor->update(['default_slot_minutes' => 20]);

        $date = $this->soon();
        $offered = array_column($this->avail()->timesFor($this->branch->id, $date, 1, $this->doctor->id), 'value');

        $this->assertContains('09:40:00', $offered);

        foreach ($offered as $slot) {
            $this->assertNull(
                $this->svc()->bookingProblem($this->branch->id, $this->doctor->id, $date, substr($slot, 0, 5)),
                "The grid offered {$slot} but the guard rejected it.",
            );
        }

        $this->assertNotNull(
            $this->svc()->bookingProblem($this->branch->id, $this->doctor->id, $date, '09:45'),
            '09:45 is not on a 20-minute grid but was accepted.',
        );
    }

    /* ---------------- clashes and races ---------------- */

    public function test_a_booking_at_another_branch_still_blocks_the_doctor(): void
    {
        $other = Branch::create([
            'partner_id' => $this->branch->partner_id,
            'name' => ['en' => 'Other', 'ar' => 'آخر'],
            'slug' => 'other-'.uniqid(),
            'is_available' => true,
        ]);

        $date = $this->soon();
        Booking::create([
            'branch_id' => $other->id,
            'doctor_id' => $this->doctor->id,
            'msisdn' => '96500000000',
            'party_size' => 1,
            'res_date' => $date,
            'res_time' => '10:00:00',
            'res_start' => $date.' 10:00:00',
            'res_end' => $date.' 10:15:00',
            'status' => Booking::S_CONFIRMED,
            'booking_code' => 'XBRANCH01',
        ]);

        $times = array_column($this->avail()->timesFor($this->branch->id, $date, 1, $this->doctor->id), 'value');

        $this->assertNotContains('10:00:00', $times,
            'The grid ignored a booking the guard blocks on, so it offered a slot that could never be taken.');
    }

    public function test_guarded_booking_lets_only_one_writer_through(): void
    {
        $date = $this->soon();
        $write = fn (string $code) => fn () => Booking::create([
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'msisdn' => '96500000000',
            'party_size' => 1,
            'res_date' => $date,
            'res_time' => '09:00:00',
            'res_start' => $date.' 09:00:00',
            'res_end' => $date.' 09:15:00',
            'status' => Booking::S_CONFIRMED,
            'booking_code' => $code,
        ]);

        [$first] = $this->svc()->guardedBooking($this->branch->id, $this->doctor->id, $date, '09:00', null, $write('RACE0001'));
        [$second, $problem] = $this->svc()->guardedBooking($this->branch->id, $this->doctor->id, $date, '09:00', null, $write('RACE0002'));

        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertNotNull($problem);
        $this->assertSame(1, Booking::whereIn('booking_code', ['RACE0001', 'RACE0002'])->count());
    }

    /* ---------------- reporting ---------------- */

    public function test_a_booking_that_overruns_closing_counts_as_outside_hours(): void
    {
        $date = $this->soon();
        Booking::create([
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'msisdn' => '96500000000',
            'party_size' => 1,
            'res_date' => $date,
            'res_time' => '16:55:00',   // starts inside 09:00–17:00 …
            'res_start' => $date.' 16:55:00',
            'res_end' => $date.' 17:10:00', // … but runs past it
            'status' => Booking::S_CONFIRMED,
            'booking_code' => 'OVERRUN01',
        ]);

        $this->assertSame(1, $this->svc()->countBookingsOutsideHours($this->branch->id),
            'Only the start time was checked, so an appointment finishing after closing looked like it fitted.');
    }

    /* ---------------- endpoints ---------------- */

    public function test_reassigning_to_a_deactivated_doctor_is_rejected(): void
    {
        $date = $this->soon();
        $booking = Booking::create([
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'msisdn' => '96500000000',
            'party_size' => 1,
            'res_date' => $date,
            'res_time' => '10:00:00',
            'res_start' => $date.' 10:00:00',
            'res_end' => $date.' 10:15:00',
            'status' => Booking::S_CONFIRMED,
            'booking_code' => 'REASSIGN1',
        ]);

        $this->colleague->update(['is_active' => false]);

        $this->actingAs($this->admin)
            ->putJson("/admin/v2/api/bookings/{$booking->id}", ['doctor_id' => $this->colleague->id])
            ->assertStatus(422);

        $this->assertSame($this->doctor->id, $booking->fresh()->doctor_id);
    }

    public function test_reassigning_onto_a_taken_slot_is_rejected(): void
    {
        $date = $this->soon();

        $mine = Booking::create([
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'msisdn' => '96500000000',
            'party_size' => 1,
            'res_date' => $date,
            'res_time' => '10:00:00',
            'res_start' => $date.' 10:00:00',
            'res_end' => $date.' 10:15:00',
            'status' => Booking::S_CONFIRMED,
            'booking_code' => 'REASSIGN2',
        ]);

        Booking::create([
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->colleague->id,
            'msisdn' => '96500000001',
            'party_size' => 1,
            'res_date' => $date,
            'res_time' => '10:00:00',
            'res_start' => $date.' 10:00:00',
            'res_end' => $date.' 10:15:00',
            'status' => Booking::S_CONFIRMED,
            'booking_code' => 'REASSIGN3',
        ]);

        $this->actingAs($this->admin)
            ->putJson("/admin/v2/api/bookings/{$mine->id}", ['doctor_id' => $this->colleague->id])
            ->assertStatus(422);

        $this->assertSame($this->doctor->id, $mine->fresh()->doctor_id);
    }

    public function test_a_clean_reassignment_still_goes_through(): void
    {
        $date = $this->soon();
        $booking = Booking::create([
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'msisdn' => '96500000000',
            'party_size' => 1,
            'res_date' => $date,
            'res_time' => '10:00:00',
            'res_start' => $date.' 10:00:00',
            'res_end' => $date.' 10:15:00',
            'status' => Booking::S_CONFIRMED,
            'booking_code' => 'REASSIGN4',
        ]);

        $this->actingAs($this->admin)
            ->putJson("/admin/v2/api/bookings/{$booking->id}", ['doctor_id' => $this->colleague->id])
            ->assertOk();

        $this->assertSame($this->colleague->id, $booking->fresh()->doctor_id);
    }

    public function test_the_slots_endpoint_reports_a_disabled_branch(): void
    {
        $this->branch->update(['is_available' => false]);

        $this->actingAs($this->admin)
            ->getJson('/admin/v2/api/bookings/slots?doctor_id='.$this->doctor->id.'&branch_id='.$this->branch->id.'&date='.$this->soon())
            ->assertOk()
            ->assertJsonPath('slots', [])
            ->assertJsonPath('reason', 'branch_disabled');
    }
}
