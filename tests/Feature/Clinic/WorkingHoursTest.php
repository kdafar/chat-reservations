<?php

namespace Tests\Feature\Clinic;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchAvailabilityRule;
use App\Models\Doctor;
use App\Models\Partner;
use App\Models\Patient;
use App\Models\User;
use App\Services\Clinic\WorkingHoursService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Branch hours bound doctor hours, and both bound bookings.
 *
 * The branch is the outer window: a doctor can be scheduled inside it, never
 * past it, and never at all on a day the branch is shut. Every booking entry
 * point re-checks that server-side, because a stale tab or a crafted POST
 * won't have gone through the picker.
 */
class WorkingHoursTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Doctor $doctor;

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
            'max_booking_days' => 60,
        ]);

        // Sun–Thu 09:00–17:00, Fri closed, Sat absent (= closed).
        foreach ([0, 1, 2, 3, 4] as $dow) {
            $this->rule($dow, true, '09:00:00', '17:00:00');
        }
        $this->rule(5, false, '09:00:00', '17:00:00');

        $this->doctor = Doctor::create([
            'partner_id' => $partner->id,
            'branch_id' => $this->branch->id,
            'name' => 'Dr Test',
            'specialty' => 'General',
            'consultation_fee' => 10,
            'is_active' => true,
            'working_hours' => [['day' => 1, 'start' => '09:00', 'end' => '17:00']],
        ]);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local',
            'password' => 'secret123', 'status' => 'active',
        ]);
        $role = Role::findOrCreate('admin', 'web');
        foreach (['view_any_doctors', 'update_doctors', 'view_any_branch', 'view_any_booking'] as $p) {
            $role->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        $this->admin->assignRole($role);
    }

    private function rule(int $dow, bool $open, string $from, string $to, int $length = 30): BranchAvailabilityRule
    {
        return BranchAvailabilityRule::create([
            'branch_id' => $this->branch->id,
            'day_of_week' => $dow,
            'is_open' => $open,
            'open_at' => $from,
            'close_at' => $to,
            'slot_length_minutes' => $length,
            'slot_step_minutes' => $length,
            'lead_time_minutes' => 0,
        ]);
    }

    private function svc(): WorkingHoursService
    {
        return app(WorkingHoursService::class);
    }

    private function nextMonday(): string
    {
        return Carbon::now(config('app.timezone'))->next(Carbon::MONDAY)->toDateString();
    }

    private function doctorPayload(array $hours): array
    {
        return [
            'name' => $this->doctor->name,
            'specialty' => 'General',
            'consultation_fee' => 10,
            'partner_id' => $this->doctor->partner_id,
            'branch_id' => $this->branch->id,
            'is_active' => 1,
            'working_hours' => $hours,
        ];
    }

    /* ---------------- doctor hours vs branch hours ---------------- */

    public function test_doctor_hours_inside_the_branch_window_are_accepted(): void
    {
        $this->actingAs($this->admin)
            ->put("/admin/v2/doctors/{$this->doctor->id}", $this->doctorPayload([
                ['day' => 1, 'is_open' => true, 'start' => '10:00', 'end' => '16:00'],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            [['day' => 1, 'start' => '10:00', 'end' => '16:00']],
            $this->doctor->fresh()->working_hours,
        );
    }

    public function test_doctor_cannot_start_before_the_branch_opens(): void
    {
        $this->actingAs($this->admin)
            ->put("/admin/v2/doctors/{$this->doctor->id}", $this->doctorPayload([
                ['day' => 1, 'is_open' => true, 'start' => '07:00', 'end' => '16:00'],
            ]))
            ->assertSessionHasErrors();
    }

    public function test_doctor_cannot_run_past_the_branch_close(): void
    {
        $this->actingAs($this->admin)
            ->put("/admin/v2/doctors/{$this->doctor->id}", $this->doctorPayload([
                ['day' => 1, 'is_open' => true, 'start' => '10:00', 'end' => '19:00'],
            ]))
            ->assertSessionHasErrors();
    }

    public function test_doctor_cannot_work_on_a_day_the_branch_is_closed(): void
    {
        $this->actingAs($this->admin)
            ->put("/admin/v2/doctors/{$this->doctor->id}", $this->doctorPayload([
                ['day' => 5, 'is_open' => true, 'start' => '10:00', 'end' => '16:00'],
            ]))
            ->assertSessionHasErrors();
    }

    public function test_doctor_cannot_work_on_a_weekday_the_branch_never_configured(): void
    {
        // Saturday (6) has no rule row at all — treated as closed.
        $this->actingAs($this->admin)
            ->put("/admin/v2/doctors/{$this->doctor->id}", $this->doctorPayload([
                ['day' => 6, 'is_open' => true, 'start' => '10:00', 'end' => '16:00'],
            ]))
            ->assertSessionHasErrors();
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->put("/admin/v2/doctors/{$this->doctor->id}", $this->doctorPayload([
                ['day' => 1, 'is_open' => true, 'start' => '16:00', 'end' => '10:00'],
            ]))
            ->assertSessionHasErrors();
    }

    public function test_hours_exactly_matching_the_branch_window_are_allowed(): void
    {
        $this->actingAs($this->admin)
            ->put("/admin/v2/doctors/{$this->doctor->id}", $this->doctorPayload([
                ['day' => 1, 'is_open' => true, 'start' => '09:00', 'end' => '17:00'],
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_a_branch_with_no_hours_configured_imposes_no_window(): void
    {
        BranchAvailabilityRule::where('branch_id', $this->branch->id)->delete();

        $this->actingAs($this->admin)
            ->put("/admin/v2/doctors/{$this->doctor->id}", $this->doctorPayload([
                ['day' => 1, 'is_open' => true, 'start' => '06:00', 'end' => '22:00'],
            ]))
            ->assertSessionHasNoErrors();
    }

    /** An overnight branch window (17:00–01:00) still contains its own tail. */
    public function test_overnight_branch_window_accepts_after_midnight_hours(): void
    {
        BranchAvailabilityRule::where('branch_id', $this->branch->id)->where('day_of_week', 1)->delete();
        $this->rule(1, true, '17:00:00', '01:00:00');

        $this->assertSame([], $this->svc()->validateDoctorHours(
            [['day' => 1, 'start' => '23:00', 'end' => '01:00']], $this->branch->id,
        ));
        $this->assertNotSame([], $this->svc()->validateDoctorHours(
            [['day' => 1, 'start' => '16:00', 'end' => '20:00']], $this->branch->id,
        ));
    }

    /* ---------------- narrowing branch hours re-fits doctors ---------------- */

    public function test_narrowing_branch_hours_trims_existing_doctor_hours(): void
    {
        $this->doctor->update(['working_hours' => [
            ['day' => 1, 'start' => '09:00', 'end' => '17:00'],
            ['day' => 2, 'start' => '09:00', 'end' => '17:00'],
        ]]);

        // Branch now opens 12:00–18:00 on Monday and closes Tuesday entirely.
        $days = [];
        foreach (range(0, 6) as $dow) {
            $days[] = [
                'day' => $dow,
                'is_open' => $dow === 1,
                'open_at' => '12:00',
                'close_at' => '18:00',
            ];
        }
        $impact = $this->svc()->saveBranchHours($this->branch, $days, [
            'slot_length_minutes' => 30, 'slot_step_minutes' => 30, 'lead_time_minutes' => 0,
        ]);

        $this->assertSame(
            [['day' => 1, 'start' => '12:00', 'end' => '17:00']],
            $this->doctor->fresh()->working_hours,
            'Monday should clamp to the new opening time and Tuesday should drop.',
        );
        $this->assertContains('Dr Test', $impact['adjusted_doctors']);
    }

    public function test_closing_every_day_a_doctor_worked_clears_their_schedule(): void
    {
        $days = [];
        foreach (range(0, 6) as $dow) {
            $days[] = ['day' => $dow, 'is_open' => false, 'open_at' => '09:00', 'close_at' => '17:00'];
        }
        $impact = $this->svc()->saveBranchHours($this->branch, $days, [
            'slot_length_minutes' => 30, 'slot_step_minutes' => 30, 'lead_time_minutes' => 0,
        ]);

        $this->assertSame([], $this->doctor->fresh()->working_hours);
        $this->assertContains('Dr Test', $impact['closed_out_doctors']);
    }

    public function test_saving_branch_hours_mirrors_them_into_branch_opening_hours(): void
    {
        $days = [];
        foreach (range(0, 6) as $dow) {
            $days[] = ['day' => $dow, 'is_open' => $dow !== 5, 'open_at' => '08:30', 'close_at' => '20:00'];
        }
        $this->svc()->saveBranchHours($this->branch, $days, [
            'slot_length_minutes' => 30, 'slot_step_minutes' => 30, 'lead_time_minutes' => 0,
        ]);

        $this->assertDatabaseHas('branch_opening_hours', [
            'branch_id' => $this->branch->id, 'day_of_week' => 1,
            'opens_at' => '08:30:00', 'closes_at' => '20:00:00', 'is_closed' => 0,
        ]);
        $this->assertDatabaseHas('branch_opening_hours', [
            'branch_id' => $this->branch->id, 'day_of_week' => 5, 'is_closed' => 1,
        ]);
    }

    /* ---------------- booking guards ---------------- */

    private function book(string $date, string $time): \Illuminate\Testing\TestResponse
    {
        $patient = Patient::create([
            'name' => 'P '.uniqid(),
            'partner_id' => $this->branch->partner_id,
            'phone' => '9'.random_int(1000000, 9999999),
        ]);

        return $this->actingAs($this->admin)->postJson('/admin/v2/bookings', [
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'doctor_id' => $this->doctor->id,
            'res_date' => $date,
            'res_time' => $time,
            'party_size' => 1,
        ]);
    }

    public function test_booking_inside_both_windows_is_created(): void
    {
        $this->book($this->nextMonday(), '10:00')->assertOk();
        $this->assertDatabaseHas('bookings', ['res_time' => '10:00', 'doctor_id' => $this->doctor->id]);
    }

    public function test_booking_before_the_branch_opens_is_rejected(): void
    {
        $this->book($this->nextMonday(), '06:00')
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_whose_slot_would_run_past_closing_is_rejected(): void
    {
        // 16:45 + 30 min appointment = 17:15, past the 17:00 close.
        $this->book($this->nextMonday(), '16:45')->assertStatus(422);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_on_a_closed_day_is_rejected(): void
    {
        $friday = Carbon::now(config('app.timezone'))->next(Carbon::FRIDAY)->toDateString();
        $this->book($friday, '10:00')->assertStatus(422);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_in_the_past_is_rejected(): void
    {
        $past = Carbon::now(config('app.timezone'))->subWeek()->next(Carbon::MONDAY)->toDateString();
        $this->book($past, '10:00')->assertStatus(422);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_outside_the_doctors_own_hours_is_rejected(): void
    {
        // Branch is open 09:00–17:00 but this doctor only works mornings.
        $this->doctor->update(['working_hours' => [['day' => 1, 'start' => '09:00', 'end' => '12:00']]]);

        $this->book($this->nextMonday(), '14:00')->assertStatus(422);
        $this->book($this->nextMonday(), '10:00')->assertOk();
    }

    public function test_double_booking_the_same_doctor_is_rejected(): void
    {
        $this->book($this->nextMonday(), '10:00')->assertOk();
        $this->book($this->nextMonday(), '10:00')->assertStatus(422);
        $this->book($this->nextMonday(), '10:15')->assertStatus(422); // overlaps the 30-min slot
        $this->book($this->nextMonday(), '10:30')->assertOk();        // starts exactly as it ends
    }

    public function test_reschedule_is_held_to_the_same_rules(): void
    {
        $this->book($this->nextMonday(), '10:00')->assertOk();
        $booking = Booking::first();

        $this->actingAs($this->admin)
            ->postJson("/admin/v2/api/bookings/{$booking->id}/reschedule", [
                'res_date' => $this->nextMonday(), 'res_time' => '06:00',
            ])->assertStatus(422);

        // Moving within the window still works, and doesn't clash with itself.
        $this->actingAs($this->admin)
            ->postJson("/admin/v2/api/bookings/{$booking->id}/reschedule", [
                'res_date' => $this->nextMonday(), 'res_time' => '11:00',
            ])->assertOk();
    }

    /* ---------------- slot generation ---------------- */

    public function test_slots_never_fall_outside_the_branch_window(): void
    {
        $slots = $this->svc()->slotsFor($this->branch->id, $this->doctor->id, $this->nextMonday());

        $this->assertNotEmpty($slots);
        $this->assertSame('09:00', $slots[0]);
        $this->assertSame('16:30', end($slots), 'The last slot must end by 17:00.');
    }

    public function test_slots_are_empty_on_a_closed_day(): void
    {
        $friday = Carbon::now(config('app.timezone'))->next(Carbon::FRIDAY)->toDateString();
        $this->assertSame([], $this->svc()->slotsFor($this->branch->id, $this->doctor->id, $friday));
    }

    public function test_slots_shrink_to_the_doctors_own_hours(): void
    {
        $this->doctor->update(['working_hours' => [['day' => 1, 'start' => '13:00', 'end' => '15:00']]]);

        $slots = $this->svc()->slotsFor($this->branch->id, $this->doctor->id, $this->nextMonday());

        $this->assertSame(['13:00', '13:30', '14:00', '14:30'], $slots);
    }

    public function test_the_slots_endpoint_agrees_with_the_booking_guard(): void
    {
        $date = $this->nextMonday();
        $slots = $this->actingAs($this->admin)
            ->getJson("/admin/v2/api/bookings/slots?doctor_id={$this->doctor->id}&branch_id={$this->branch->id}&date={$date}")
            ->assertOk()
            ->json('slots');

        // Everything the picker offers must actually be bookable...
        foreach ($slots as $slot) {
            $this->assertNull(
                $this->svc()->bookingProblem($this->branch->id, $this->doctor->id, $date, $slot),
                "Slot {$slot} was offered but the guard rejects it.",
            );
        }
        // ...and times it doesn't offer must be refused.
        $this->assertNotNull($this->svc()->bookingProblem($this->branch->id, $this->doctor->id, $date, '08:00'));
    }

    /* ---------------- per-doctor appointment length ---------------- */

    public function test_a_doctor_without_their_own_length_inherits_the_branchs(): void
    {
        $this->assertSame(30, $this->svc()->slotLength($this->branch->id, 1, $this->doctor->id));
    }

    public function test_a_doctors_own_length_overrides_the_branchs(): void
    {
        $this->doctor->update(['default_slot_minutes' => 45]);

        $this->assertSame(45, $this->svc()->slotLength($this->branch->id, 1, $this->doctor->id));
        // The branch's own number is untouched for everyone else.
        $this->assertSame(30, $this->svc()->slotLength($this->branch->id, 1));
    }

    public function test_slots_run_back_to_back_at_the_doctors_own_length(): void
    {
        $this->doctor->update([
            'default_slot_minutes' => 45,
            'working_hours' => [['day' => 1, 'start' => '09:00', 'end' => '12:00']],
        ]);

        $slots = $this->svc()->slotsFor($this->branch->id, $this->doctor->id, $this->nextMonday());

        // 09:00–12:00 in 45-minute blocks: the 11:15 slot would end at 12:00 exactly.
        $this->assertSame(['09:00', '09:45', '10:30', '11:15'], $slots);
    }

    public function test_two_doctors_at_one_branch_get_different_grids(): void
    {
        $this->doctor->update([
            'default_slot_minutes' => 60,
            'working_hours' => [['day' => 1, 'start' => '09:00', 'end' => '12:00']],
        ]);
        $quick = Doctor::create([
            'partner_id' => $this->doctor->partner_id,
            'branch_id' => $this->branch->id,
            'name' => 'Dr Quick',
            'specialty' => 'General',
            'consultation_fee' => 5,
            'is_active' => true,
            'default_slot_minutes' => 15,
            'working_hours' => [['day' => 1, 'start' => '09:00', 'end' => '10:00']],
        ]);

        $this->assertSame(
            ['09:00', '10:00', '11:00'],
            $this->svc()->slotsFor($this->branch->id, $this->doctor->id, $this->nextMonday()),
        );
        $this->assertSame(
            ['09:00', '09:15', '09:30', '09:45'],
            $this->svc()->slotsFor($this->branch->id, $quick->id, $this->nextMonday()),
        );
    }

    public function test_the_booking_window_is_sized_by_the_doctors_length(): void
    {
        $this->doctor->update(['default_slot_minutes' => 60]);

        $this->book($this->nextMonday(), '10:00')->assertOk();

        $booking = Booking::first();
        $this->assertSame(
            60,
            (int) Carbon::parse($booking->res_start)->diffInMinutes(Carbon::parse($booking->res_end)),
        );

        // And the longer block blocks more: 10:30 now overlaps.
        $this->book($this->nextMonday(), '10:30')->assertStatus(422);
        $this->book($this->nextMonday(), '11:00')->assertOk();
    }

    public function test_a_long_appointment_cannot_overrun_the_doctors_end(): void
    {
        $this->doctor->update([
            'default_slot_minutes' => 60,
            'working_hours' => [['day' => 1, 'start' => '09:00', 'end' => '12:00']],
        ]);

        // 11:00 fits exactly; 11:30 would end at 12:30, past the doctor's day.
        $this->book($this->nextMonday(), '11:30')->assertStatus(422);
        $this->book($this->nextMonday(), '11:00')->assertOk();
    }

    public function test_the_doctors_length_is_saved_from_the_admin_form(): void
    {
        $this->actingAs($this->admin)
            ->put("/admin/v2/doctors/{$this->doctor->id}", $this->doctorPayload([
                ['day' => 1, 'is_open' => true, 'start' => '10:00', 'end' => '16:00'],
            ]) + ['default_slot_minutes' => 45])
            ->assertSessionHasNoErrors();

        $this->assertSame(45, (int) $this->doctor->fresh()->default_slot_minutes);

        // Clearing it hands the doctor back to the branch default.
        $this->actingAs($this->admin)
            ->put("/admin/v2/doctors/{$this->doctor->id}", $this->doctorPayload([
                ['day' => 1, 'is_open' => true, 'start' => '10:00', 'end' => '16:00'],
            ]) + ['default_slot_minutes' => null])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->doctor->fresh()->default_slot_minutes);
        $this->assertSame(30, $this->svc()->slotLength($this->branch->id, 1, $this->doctor->id));
    }

    public function test_the_slots_endpoint_explains_an_empty_result(): void
    {
        $friday = Carbon::now(config('app.timezone'))->next(Carbon::FRIDAY)->toDateString();

        $this->actingAs($this->admin)
            ->getJson("/admin/v2/api/bookings/slots?doctor_id={$this->doctor->id}&branch_id={$this->branch->id}&date={$friday}")
            ->assertOk()
            ->assertJsonPath('slots', [])
            ->assertJsonPath('reason', 'branch_closed');
    }
}
