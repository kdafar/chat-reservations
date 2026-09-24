<?php

namespace Tests\Feature\V2;

use App\Models\Accounting\JournalEntry;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\DoctorCompensationProfile;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCharge;
use App\Models\VisitItem;
use App\Models\VisitPayment;
use App\Services\Clinic\DoctorCompensationService;
use Database\Seeders\ClinicPaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

/**
 * A doctor with consultation_fee = 0 works free of charge. Locks the whole
 * chain: the form only accepts 0 behind the explicit free_of_charge toggle,
 * check-in skips the fee gate, nothing consultation-related is billed or
 * posted, and the visit still bills + posts its other lines and discharges.
 * Paid doctors are asserted alongside so the free path can't loosen them.
 */
class FreeDoctorFlowTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        $this->seed(ClinicPaymentMethodSeeder::class);
        config(['clinic.visit_financials_enabled' => true]);
        $this->resetScopeCaches();
    }

    /** Branch/partner scope traits memoize per-user in statics; clear between tests. */
    private function resetScopeCaches(): void
    {
        foreach ([[Booking::class, ['bbsAdminCache', 'bbsBranchIdsCache', 'bbsDoctorIdCache']],
            [\App\Models\Patient::class, ['bpsAdminCache', 'bpsPartnerIdsCache']]] as [$class, $props]) {
            foreach ($props as $p) {
                try {
                    $rp = new \ReflectionProperty($class, $p);
                    $rp->setAccessible(true);
                    $rp->setValue(null, []);
                } catch (\Throwable) { /* renamed — ignore */ }
            }
        }
    }

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('clinic_doctor', 'web');
        foreach (['view_any_doctors', 'update_doctors', 'view_any_visits'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@t.local',
            'password' => Hash::make('password'), 'status' => 'active',
        ]);
        $u->assignRole('admin');
        $u->givePermissionTo('view_any_doctors', 'update_doctors', 'view_any_visits');

        return $u->fresh();
    }

    private function doctorPayload(array $overrides = []): array
    {
        $f = $this->seedClinicFixtures();

        return array_merge([
            'name' => 'Dr. Volunteer',
            'specialty' => 'General Practice',
            'email' => 'doc-'.uniqid().'@clinic.local',
            'partner_id' => $f['partner']->id,
            'branch_id' => $f['branch']->id,
        ], $overrides);
    }

    private function freeDoctor(): Doctor
    {
        $f = $this->seedClinicFixtures();

        return Doctor::create([
            'partner_id' => $f['partner']->id, 'branch_id' => $f['branch']->id,
            'name' => 'Dr. Free', 'specialty' => 'GP', 'consultation_fee' => 0, 'is_active' => true,
        ]);
    }

    private function bookingFor(Doctor $doctor): Booking
    {
        $f = $this->seedClinicFixtures();

        return Booking::create([
            'branch_id' => $f['branch']->id,
            'patient_id' => $f['patient']->id,
            'doctor_id' => $doctor->id,
            'msisdn' => $f['patient']->phone,
            'party_size' => 1,
            'res_date' => now()->toDateString(),
            'res_time' => '10:00:00',
            'res_start' => now(),
            'status' => 'confirmed',
            'booking_code' => 'FREE'.random_int(10000, 99999),
        ]);
    }

    // ── Doctor form ──────────────────────────────────────────────────────

    public function test_free_of_charge_toggle_saves_a_zero_fee_doctor(): void
    {
        $payload = $this->doctorPayload(['free_of_charge' => true, 'consultation_fee' => 12]);

        $this->actingAs($this->admin())->post('/admin/v2/doctors', $payload)
            ->assertSessionHasNoErrors();

        $doctor = Doctor::where('email', $payload['email'])->firstOrFail();
        // The toggle wins over a stale fee left in the hidden input.
        $this->assertEqualsWithDelta(0.0, (float) $doctor->consultation_fee, 0.0001);
    }

    public function test_zero_fee_without_the_toggle_is_still_rejected(): void
    {
        foreach ([['consultation_fee' => 0], ['consultation_fee' => 0, 'free_of_charge' => false], []] as $extra) {
            $this->actingAs($this->admin())
                ->post('/admin/v2/doctors', $this->doctorPayload($extra))
                ->assertSessionHasErrors('consultation_fee');
        }
        $this->assertSame(1, Doctor::count(), 'only the fixture doctor exists');
    }

    public function test_doctor_can_switch_between_paid_and_free(): void
    {
        $f = $this->seedClinicFixtures();
        $doctor = $f['doctor']; // fee 25
        $admin = $this->admin();
        $base = $this->doctorPayload(['name' => $doctor->name, 'email' => null]);

        $this->actingAs($admin)->put("/admin/v2/doctors/{$doctor->id}", $base + ['free_of_charge' => true])
            ->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(0.0, (float) $doctor->fresh()->consultation_fee, 0.0001);

        $this->actingAs($admin)->put("/admin/v2/doctors/{$doctor->id}", $base + ['free_of_charge' => false, 'consultation_fee' => 7.5])
            ->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(7.5, (float) $doctor->fresh()->consultation_fee, 0.0001);
    }

    // ── Check-in ─────────────────────────────────────────────────────────

    public function test_free_doctor_checks_in_without_any_payment(): void
    {
        $booking = $this->bookingFor($this->freeDoctor());
        $admin = $this->admin();

        $this->actingAs($admin)->getJson("/admin/v2/api/checkin/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('booking.fee', 0);

        $this->actingAs($admin)->postJson("/admin/v2/api/checkin/bookings/{$booking->id}/check-in")
            ->assertOk();

        $this->assertNotNull($booking->fresh()->checked_in_at);
        $visit = Visit::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame(Visit::STATUS_AWAITING_DOCTOR, $visit->status);
        $this->assertSame(0, VisitCharge::where('visit_id', $visit->id)->count(), 'no consultation charge billed');
        $this->assertSame(0, VisitPayment::count());
    }

    public function test_paid_doctor_still_requires_the_fee_before_check_in(): void
    {
        $f = $this->seedClinicFixtures();
        $booking = $this->bookingFor($f['doctor']); // fee 25

        $this->actingAs($this->admin())->postJson("/admin/v2/api/checkin/bookings/{$booking->id}/check-in")
            ->assertStatus(422);
        $this->assertNull($booking->fresh()->checked_in_at);
    }

    public function test_fee_collection_is_refused_for_a_free_doctor(): void
    {
        $booking = $this->bookingFor($this->freeDoctor());

        $this->actingAs($this->admin())
            ->postJson("/admin/v2/api/checkin/bookings/{$booking->id}/collect-fee", ['amount' => 5, 'method' => 'cash'])
            ->assertStatus(422);

        $this->assertSame(0, VisitPayment::count());
        $this->assertSame(0, VisitCharge::count(), 'no stray zero-value charge row');
    }

    // ── Full visit: bill, pay, post, discharge ───────────────────────────

    public function test_free_visit_runs_end_to_end_and_posts_only_real_revenue(): void
    {
        $booking = $this->bookingFor($this->freeDoctor());
        $admin = $this->admin();

        $this->actingAs($admin)->postJson("/admin/v2/api/checkin/bookings/{$booking->id}/check-in")->assertOk();
        $visit = Visit::where('booking_id', $booking->id)->firstOrFail();

        $this->actingAs($admin)->postJson("/admin/v2/api/visits/{$visit->id}/start")->assertOk()->assertJson(['ok' => true]);

        // Show the visit sheet — this is the payload the console renders from.
        $this->actingAs($admin)->getJson("/admin/v2/api/visits/{$visit->id}")->assertOk();

        $this->actingAs($admin)->postJson("/admin/v2/api/visits/{$visit->id}/complete")->assertOk()->assertJson(['ok' => true]);

        // Consultation-only free visit owes nothing → discharges with no payment.
        $this->actingAs($admin)->postJson("/admin/v2/api/visits/{$visit->id}/discharge")->assertOk()->assertJson(['ok' => true]);

        $visit->refresh();
        $this->assertSame(Visit::STATUS_COMPLETED, $visit->status);
        $this->assertSame(0, JournalEntry::count(), 'nothing to post for a free consultation');
        $this->assertBooksBalance();
    }

    public function test_free_visit_with_billable_item_still_bills_and_posts(): void
    {
        $booking = $this->bookingFor($this->freeDoctor());
        $admin = $this->admin();

        $this->actingAs($admin)->postJson("/admin/v2/api/checkin/bookings/{$booking->id}/check-in")->assertOk();
        $visit = Visit::where('booking_id', $booking->id)->firstOrFail();
        $visit->update(['status' => Visit::STATUS_AWAITING_PAYMENT]);

        VisitItem::create([
            'visit_id' => $visit->id, 'clinic_item_id' => $this->makeClinicItem()->id,
            'branch_id' => $visit->branch_id, 'qty' => 1,
            'unit_cost_snapshot' => 2.000, 'unit_price_snapshot' => 10.000,
            'line_cost_total' => 2.000, 'line_price_total' => 10.000, 'discount_amount' => 0,
        ]);

        // Unpaid item blocks discharge — the free consultation doesn't waive the rest.
        $this->actingAs($admin)->postJson("/admin/v2/api/visits/{$visit->id}/discharge")->assertStatus(422);

        // Overpaying is refused (owed is 10, not 10 + a phantom fee).
        $this->actingAs($admin)->postJson("/admin/v2/api/visits/{$visit->id}/payments", [
            'amount' => 15.0, 'kind' => 'medicines', 'method' => 'cash',
        ])->assertStatus(422);

        $this->actingAs($admin)->postJson("/admin/v2/api/visits/{$visit->id}/payments", [
            'amount' => 10.0, 'kind' => 'medicines', 'method' => 'cash',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->actingAs($admin)->postJson("/admin/v2/api/visits/{$visit->id}/discharge")->assertOk()->assertJson(['ok' => true]);

        // Payment (cash → 1140) + discharge accrual (1140 → 4210 medicines revenue).
        $this->assertSame(2, JournalEntry::where('status', JournalEntry::STATUS_POSTED)->count());
        $this->assertEqualsWithDelta(10.0, abs($this->account('4210')->balanceAt(now()->toDateString())), 0.001, 'item revenue booked');
        $this->assertEqualsWithDelta(0.0, $this->account('4110')->balanceAt(now()->toDateString()), 0.001, 'no consultation revenue (4110)');
        $this->assertBooksBalance();
    }

    // ── Doctor compensation ──────────────────────────────────────────────

    public function test_compensation_on_a_free_visit(): void
    {
        config(['clinic.doctor_comp_enabled' => true, 'clinic.doctor_comp_only_on_completed' => true]);
        $doctor = $this->freeDoctor();
        $svc = app(DoctorCompensationService::class);

        $profile = DoctorCompensationProfile::create([
            'doctor_id' => $doctor->id, 'type' => 'percentage', 'basis' => 'fees_only',
            'percentage_rate' => 40.0, 'is_active' => 1,
        ]);
        $visit = $this->makeVisit([
            'doctor_id' => $doctor->id, 'status' => 'completed',
            'fees_total' => 0, 'discount_total' => 0, 'items_cost_total' => 0,
            'items_price_total' => 0, 'profit_total' => 0,
        ]);
        $ledger = $svc->sync($visit);
        $this->assertEqualsWithDelta(0.0, (float) ($ledger?->doctor_cut_amount ?? 0), 0.001, '40% of nothing');

        // net_profit basis: a free doctor who sells items still earns on them.
        $profile->update(['basis' => 'net_profit']);
        $visit2 = $this->makeVisit([
            'doctor_id' => $doctor->id, 'status' => 'completed',
            'fees_total' => 0, 'discount_total' => 0, 'items_cost_total' => 2,
            'items_price_total' => 10, 'profit_total' => 8,
        ]);
        $ledger2 = $svc->sync($visit2);
        $this->assertNotNull($ledger2);
        $this->assertEqualsWithDelta(3.2, (float) $ledger2->doctor_cut_amount, 0.001, '40% of the 8 profit');

        // Salaried: no per-visit cut either way.
        $profile->update(['type' => 'salary']);
        $visit3 = $this->makeVisit(['doctor_id' => $doctor->id, 'status' => 'completed', 'fees_total' => 0, 'profit_total' => 0]);
        $this->assertEqualsWithDelta(0.0, (float) ($svc->sync($visit3)?->doctor_cut_amount ?? 0), 0.001);
        $this->assertBooksBalance();
    }

    // ── Changing the doctor re-prices the consultation ───────────────────

    private function consultationCharge(Visit $visit): ?VisitCharge
    {
        return VisitCharge::where('visit_id', $visit->id)->where('label', 'Consultation Fee')->first();
    }

    private function checkedInFreeVisit(): Visit
    {
        $booking = $this->bookingFor($this->freeDoctor());
        $this->actingAs($this->admin())->postJson("/admin/v2/api/checkin/bookings/{$booking->id}/check-in")->assertOk();

        return Visit::where('booking_id', $booking->id)->firstOrFail();
    }

    public function test_moving_a_free_visit_to_a_paid_doctor_bills_the_fee(): void
    {
        $f = $this->seedClinicFixtures(); // Dr. Test, fee 25
        $visit = $this->checkedInFreeVisit();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson("/admin/v2/api/visits/{$visit->id}/reassign-doctor", ['doctor_id' => $f['doctor']->id])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertEqualsWithDelta(25.0, (float) $this->consultationCharge($visit)?->line_total, 0.001);
        $this->assertSame($f['doctor']->id, $visit->fresh()->doctor_id);

        // The fee now has to be paid before the patient can leave.
        $visit->update(['status' => Visit::STATUS_AWAITING_PAYMENT]);
        $this->actingAs($admin)->postJson("/admin/v2/api/visits/{$visit->id}/discharge")->assertStatus(422);
        $this->actingAs($admin)->getJson("/admin/v2/api/visits/{$visit->id}")
            ->assertOk()->assertJsonPath('visit.fee.amount', 25);
    }

    public function test_moving_an_unpaid_visit_to_a_free_doctor_drops_the_fee(): void
    {
        $f = $this->seedClinicFixtures();
        $visit = $this->makeVisit(['status' => Visit::STATUS_AWAITING_DOCTOR, 'completed_at' => null]);
        VisitCharge::create([
            'visit_id' => $visit->id, 'branch_id' => $visit->branch_id, 'label' => 'Consultation Fee',
            'qty' => 1, 'unit_price_snapshot' => 25, 'line_total' => 25,
        ]);
        $free = $this->freeDoctor();

        $this->actingAs($this->admin())->postJson("/admin/v2/api/visits/{$visit->id}/reassign-doctor", ['doctor_id' => $free->id])
            ->assertOk();

        $this->assertNull($this->consultationCharge($visit), 'free doctor → no consultation charge');
        $this->assertSame($free->id, $visit->fresh()->doctor_id);
    }

    public function test_moving_a_paid_up_visit_to_a_free_doctor_is_refused(): void
    {
        $f = $this->seedClinicFixtures();
        $visit = $this->makeVisit(['status' => Visit::STATUS_AWAITING_DOCTOR, 'completed_at' => null]);
        VisitCharge::create([
            'visit_id' => $visit->id, 'branch_id' => $visit->branch_id, 'label' => 'Consultation Fee',
            'qty' => 1, 'unit_price_snapshot' => 25, 'line_total' => 25,
        ]);
        VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 25, 'method' => 'cash', 'status' => 'paid',
            'kind' => 'consultation', 'paid_at' => now(),
        ]);

        $this->actingAs($this->admin())->postJson("/admin/v2/api/visits/{$visit->id}/reassign-doctor", ['doctor_id' => $this->freeDoctor()->id])
            ->assertStatus(422);

        // Nothing moved: the money still has a charge to sit against.
        $this->assertSame($f['doctor']->id, $visit->fresh()->doctor_id);
        $this->assertEqualsWithDelta(25.0, (float) $this->consultationCharge($visit)?->line_total, 0.001);
    }

    public function test_changing_a_prepaid_bookings_doctor_reprices_or_refuses(): void
    {
        $f = $this->seedClinicFixtures();
        $admin = $this->admin();

        // Real opening hours so the doctor swap passes the slot guard and
        // only the money rules decide the outcome.
        foreach (range(0, 6) as $dow) {
            \App\Models\BranchAvailabilityRule::create([
                'branch_id' => $f['branch']->id, 'day_of_week' => $dow, 'is_open' => true,
                'open_at' => '09:00:00', 'close_at' => '17:00:00',
                'slot_length_minutes' => 15, 'slot_step_minutes' => 15, 'lead_time_minutes' => 0,
            ]);
        }
        $hours = array_map(fn ($d) => ['day' => $d, 'start' => '09:00', 'end' => '17:00'], range(0, 6));
        $f['doctor']->update(['working_hours' => $hours]);
        $free = $this->freeDoctor();
        $free->update(['working_hours' => $hours]);
        $pricier = Doctor::create([
            'partner_id' => $f['partner']->id, 'branch_id' => $f['branch']->id,
            'name' => 'Dr. Senior', 'specialty' => 'GP', 'consultation_fee' => 30, 'is_active' => true,
            'working_hours' => $hours,
        ]);

        $booking = $this->bookingFor($f['doctor']); // fee 25
        $booking->update(['res_date' => now()->addDay()->toDateString(), 'res_time' => '10:00:00', 'res_start' => now()->addDay()->setTime(10, 0)]);

        $this->actingAs($admin)->postJson("/admin/v2/api/checkin/bookings/{$booking->id}/collect-fee", ['amount' => 25, 'method' => 'cash'])
            ->assertOk();
        $visit = Visit::where('booking_id', $booking->id)->firstOrFail();

        // To a free doctor with 25 already taken → refused, nothing changes.
        $this->actingAs($admin)->putJson("/admin/v2/api/bookings/{$booking->id}", ['doctor_id' => $free->id])
            ->assertStatus(422)->assertJsonFragment(['ok' => false])
            ->assertSee('already paid 25.000 KWD');
        $this->assertSame($f['doctor']->id, $booking->fresh()->doctor_id);
        $this->assertSame($f['doctor']->id, $visit->fresh()->doctor_id);

        // To a pricier doctor → the charge follows; 5 more is due.
        $this->actingAs($admin)->putJson("/admin/v2/api/bookings/{$booking->id}", ['doctor_id' => $pricier->id])
            ->assertOk();
        $this->assertSame($pricier->id, $booking->fresh()->doctor_id);
        $this->assertSame($pricier->id, $visit->fresh()->doctor_id);
        $this->assertEqualsWithDelta(30.0, (float) $this->consultationCharge($visit)?->line_total, 0.001);
        $this->assertEqualsWithDelta(5.0, app(\App\Services\Clinic\VisitBalanceService::class)->outstanding($visit->fresh()), 0.001);
    }

    public function test_fee_already_billed_survives_the_doctor_turning_free(): void
    {
        $f = $this->seedClinicFixtures();
        $booking = $this->bookingFor($f['doctor']); // fee 25
        $admin = $this->admin();

        $this->actingAs($admin)->postJson("/admin/v2/api/checkin/bookings/{$booking->id}/collect-fee", ['amount' => 25, 'method' => 'cash'])
            ->assertOk();
        $f['doctor']->update(['consultation_fee' => 0]); // doctor switched to free afterwards

        // The patient's card still shows what they were billed and paid.
        $this->actingAs($admin)->getJson("/admin/v2/api/checkin/bookings/{$booking->id}")
            ->assertOk()->assertJsonPath('booking.fee', 25)->assertJsonPath('booking.consultation_paid', true);
        $this->actingAs($admin)->postJson("/admin/v2/api/checkin/bookings/{$booking->id}/check-in")->assertOk();
    }

    // ── Insurance ────────────────────────────────────────────────────────

    public function test_insured_patient_on_a_zero_bill_visit_discharges_without_a_claim(): void
    {
        $visit = $this->checkedInFreeVisit();
        $visit->update(['status' => Visit::STATUS_AWAITING_PAYMENT]);
        $admin = $this->admin();

        $insurer = \App\Models\Insurance\Insurer::create(['name' => 'Gulf', 'code' => 'G-'.uniqid(), 'is_active' => true]);
        $plan = \App\Models\Insurance\InsurancePlan::create(['insurer_id' => $insurer->id, 'name' => 'Gold', 'code' => 'P-'.uniqid(), 'is_active' => true]);
        $this->actingAs($admin)->postJson("/admin/v2/api/visits/{$visit->id}/insurance/attach", [
            'civil_id' => '290010112345', 'insurer_id' => $insurer->id, 'plan_id' => $plan->id, 'policy_number' => 'POL-1',
        ])->assertOk();

        $this->actingAs($admin)->postJson("/admin/v2/api/visits/{$visit->id}/discharge")->assertOk()->assertJson(['ok' => true]);
    }

    public function test_insured_patient_with_a_bill_still_needs_the_insurance_decision(): void
    {
        $visit = $this->checkedInFreeVisit();
        $visit->update(['status' => Visit::STATUS_AWAITING_PAYMENT]);
        $admin = $this->admin();
        VisitItem::create([
            'visit_id' => $visit->id, 'clinic_item_id' => $this->makeClinicItem()->id,
            'branch_id' => $visit->branch_id, 'qty' => 1,
            'unit_cost_snapshot' => 2, 'unit_price_snapshot' => 10,
            'line_cost_total' => 2, 'line_price_total' => 10, 'discount_amount' => 0,
        ]);
        VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 10, 'method' => 'cash', 'status' => 'paid',
            'kind' => 'medicines', 'paid_at' => now(),
        ]);

        $insurer = \App\Models\Insurance\Insurer::create(['name' => 'Gulf', 'code' => 'G-'.uniqid(), 'is_active' => true]);
        $plan = \App\Models\Insurance\InsurancePlan::create(['insurer_id' => $insurer->id, 'name' => 'Gold', 'code' => 'P-'.uniqid(), 'is_active' => true]);
        $this->actingAs($admin)->postJson("/admin/v2/api/visits/{$visit->id}/insurance/attach", [
            'civil_id' => '290010112345', 'insurer_id' => $insurer->id, 'plan_id' => $plan->id, 'policy_number' => 'POL-2',
        ])->assertOk();

        $this->actingAs($admin)->postJson("/admin/v2/api/visits/{$visit->id}/discharge")
            ->assertStatus(422)->assertJson(['reason' => 'insurance_decision_pending']);
    }

    // ── Screens & reports render with free + paid visits side by side ────

    public function test_screens_and_reports_render_with_a_free_doctor(): void
    {
        $f = $this->seedClinicFixtures();
        $free = $this->freeDoctor();
        $admin = $this->admin();

        // Today: one free booking waiting, one free visit done, one paid visit done.
        $this->bookingFor($free);
        $this->makeVisit(['doctor_id' => $free->id, 'fees_total' => 0, 'profit_total' => 0]);
        $paid = $this->makeVisit(['doctor_id' => $f['doctor']->id, 'fees_total' => 25, 'profit_total' => 25]);
        VisitCharge::create([
            'visit_id' => $paid->id, 'branch_id' => $paid->branch_id, 'label' => 'Consultation Fee',
            'qty' => 1, 'unit_price_snapshot' => 25, 'line_total' => 25,
        ]);
        VisitPayment::create([
            'visit_id' => $paid->id, 'amount' => 25, 'method' => 'cash', 'status' => 'paid',
            'kind' => 'consultation', 'paid_at' => now(),
        ]);

        $from = now()->subDays(7)->toDateString();
        $to = now()->addDay()->toDateString();
        $q = "?from={$from}&to={$to}&date_from={$from}&date_to={$to}";

        $urls = [
            '/admin/v2/doctors', '/admin/v2/bookings', '/admin/v2/waiting-patients', '/admin/v2/checkin',
            '/admin/v2/visits', '/admin/v2/reports',
            '/admin/v2/reports/doctors', '/admin/v2/reports/bookings', '/admin/v2/reports/executive',
            '/admin/v2/reports/daily-closing', '/admin/v2/reports/daily-reconciliation',
            '/admin/v2/reports/discounts', '/admin/v2/reports/patients', '/admin/v2/reports/payroll',
            '/admin/v2/reports/packages', '/admin/v2/reports/insurance',
            '/admin/v2/reports/accounting/profit-loss', '/admin/v2/reports/accounting/trial-balance',
            '/admin/v2/reports/accounting/general-ledger', '/admin/v2/reports/accounting/cash-flow',
            '/admin/v2/reports/accounting/balance-sheet',
        ];

        $failures = [];
        foreach ($urls as $url) {
            $status = $this->actingAs($admin)->get($url.$q)->getStatusCode();
            if ($status >= 500) {
                $failures[] = "{$url} → {$status}";
            }
        }
        $this->assertSame([], $failures, 'no screen or report may 500 with a free doctor present');
    }
}
