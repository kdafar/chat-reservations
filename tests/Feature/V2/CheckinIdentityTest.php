<?php

namespace Tests\Feature\V2;

use App\Models\Booking;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

/**
 * Phase 3 — "reception confirms the match". A booking on a phone that already
 * belongs to a patient, but with a DIFFERENT name, must be flagged for review
 * (phones get reassigned). Reception then either confirms (keep) or splits
 * (new patient). Locks both the detection and the two resolution endpoints.
 */
class CheckinIdentityTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        $this->resetScopeCaches();
    }

    /**
     * The branch/partner scope traits memoize admin-status + accessible-ids in
     * per-user static caches. RefreshDatabase reuses user ids across tests, so
     * a stale entry would misjudge a fresh user — clear them between tests.
     */
    private function resetScopeCaches(): void
    {
        foreach ([[Booking::class, ['bbsAdminCache', 'bbsBranchIdsCache', 'bbsDoctorIdCache']],
            [Patient::class, ['bpsAdminCache', 'bpsPartnerIdsCache']]] as [$class, $props]) {
            foreach ($props as $p) {
                try {
                    $rp = new \ReflectionProperty($class, $p);
                    $rp->setAccessible(true);
                    $rp->setValue(null, []);
                } catch (\Throwable) { /* renamed — ignore */ }
            }
        }
    }

    /** Admin satisfies abortIfNotReception() and bypasses branch scope on route binding. */
    private function receptionUser(): User
    {
        Role::findOrCreate('admin', 'web');
        $u = User::create([
            'name' => 'Reception', 'email' => 'recep-'.uniqid().'@t.local',
            'password' => Hash::make('password'), 'status' => 'active',
        ]);
        $u->assignRole('admin');

        return $u;
    }

    /** Build a booking (+optional visit) for an existing patient, flagged for identity review. */
    private function flaggedBooking(Patient $existing, string $proposedName): Booking
    {
        $f = $this->seedClinicFixtures();

        return Booking::create([
            'branch_id' => $f['branch']->id,
            'patient_id' => $existing->id,
            'doctor_id' => $f['doctor']->id,
            'msisdn' => $existing->phone,
            'party_size' => 1,
            'res_date' => now()->toDateString(),
            'res_time' => '10:00:00',
            'res_start' => now(),
            'status' => 'confirmed',
            'booking_code' => 'IDT'.random_int(1000, 9999),
            'meta' => ['identity_review' => [
                'matched_patient_id' => $existing->id,
                'matched_patient_name' => $existing->name,
                'proposed_name' => $proposedName,
                'phone' => $existing->phone,
            ]],
        ]);
    }

    public function test_booking_service_flags_name_mismatch_on_known_phone(): void
    {
        $f = $this->seedClinicFixtures();
        $phone = '+96599'.random_int(100000, 999999);
        Patient::create(['partner_id' => $f['partner']->id, 'name' => 'Ahmad Original', 'phone' => $phone]);

        $svc = app(BookingService::class);
        $resolve = new \ReflectionMethod($svc, 'resolveOrCreatePatientId');
        $resolve->setAccessible(true);
        $review = new \ReflectionProperty($svc, 'identityReview');
        $review->setAccessible(true);

        // Same name → no flag.
        $resolve->invoke($svc, $phone, $f['branch'], ['name' => 'Ahmad Original']);
        $this->assertNull($review->getValue($svc));

        // Different name → flagged (but still resolves to the existing patient).
        $id = $resolve->invoke($svc, $phone, $f['branch'], ['name' => 'Sara Different']);
        $flag = $review->getValue($svc);
        $this->assertNotNull($flag);
        $this->assertSame('Sara Different', $flag['proposed_name']);

        // Blank name → no flag (nothing to compare).
        $resolve->invoke($svc, $phone, $f['branch'], ['name' => '']);
        $this->assertNull($review->getValue($svc));
    }

    public function test_confirm_identity_clears_flag_and_keeps_patient(): void
    {
        $f = $this->seedClinicFixtures();
        $existing = Patient::create(['partner_id' => $f['partner']->id, 'name' => 'Ahmad', 'phone' => '+96599123123']);
        $booking = $this->flaggedBooking($existing, 'Sara');

        $this->actingAs($this->receptionUser())
            ->postJson(route('v2.api.checkin.confirm-identity', ['booking' => $booking->id]))
            ->assertOk()->assertJsonStructure(['booking']);

        $booking->refresh();
        $this->assertEmpty($booking->meta['identity_review'] ?? null, 'flag should be cleared');
        $this->assertSame($existing->id, $booking->patient_id, 'patient unchanged on confirm');
    }

    public function test_split_patient_creates_new_patient_and_repoints_booking_and_visit(): void
    {
        $f = $this->seedClinicFixtures();
        $existing = Patient::create(['partner_id' => $f['partner']->id, 'name' => 'Ahmad', 'phone' => '+96599456456']);
        $booking = $this->flaggedBooking($existing, 'Sara New');
        $visit = Visit::create([
            'booking_id' => $booking->id, 'patient_id' => $existing->id,
            'doctor_id' => $f['doctor']->id, 'branch_id' => $f['branch']->id,
            'status' => 'awaiting_doctor', 'checked_in_at' => now(),
        ]);

        $this->actingAs($this->receptionUser())
            ->postJson(route('v2.api.checkin.split-patient', ['booking' => $booking->id]))
            ->assertOk()->assertJsonStructure(['booking']);

        $booking->refresh();
        $visit->refresh();

        $this->assertNotSame($existing->id, $booking->patient_id, 'booking repointed to a new patient');
        $newPatient = Patient::find($booking->patient_id);
        $this->assertSame('Sara New', $newPatient->name);
        $this->assertSame($existing->phone, $newPatient->phone, 'new patient keeps the phone (reassignment)');
        $this->assertSame($booking->patient_id, $visit->patient_id, 'visit repointed too');
        $this->assertEmpty($booking->meta['identity_review'] ?? null, 'flag cleared after split');

        // Two distinct patients now legitimately share the phone.
        $this->assertSame(2, Patient::where('phone', $existing->phone)->count());
    }
}
