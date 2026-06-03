<?php

namespace Tests\Feature\V2;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Partner;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Locks the multi-tenancy invariants we built:
 *  - Patients carry partner_id and are scoped by BelongsToPartnerScope.
 *  - Branch-backed models (bookings/…) are scoped by BelongsToBranchScope.
 *  - Only admin / super_admin bypass scoping; everyone else is confined to
 *    the clinic(s)/branch(es) they belong to.
 * Regression net for the cross-clinic PHI hole that was fixed.
 */
class ClinicScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The scope traits memoize admin-status / accessible-ids in per-request
        // static caches keyed by user id. RefreshDatabase reuses ids across
        // tests, so clear them between tests (a non-issue in prod: one request =
        // one process = fresh cache).
        $this->resetScopeCaches();
    }

    private function resetScopeCaches(): void
    {
        $targets = [
            [Patient::class, ['bpsAdminCache', 'bpsPartnerIdsCache']],
            [Booking::class, ['bbsAdminCache', 'bbsBranchIdsCache', 'bbsDoctorIdCache']],
        ];
        foreach ($targets as [$class, $props]) {
            foreach ($props as $p) {
                try {
                    $rp = new \ReflectionProperty($class, $p);
                    $rp->setAccessible(true);
                    $rp->setValue(null, []);
                } catch (\Throwable) { /* property name changed — ignore */ }
            }
        }
    }

    /** Two independent clinics, each with one branch + one patient. */
    private function twoClinics(): array
    {
        $pa = Partner::create(['name' => 'Clinic A', 'slug' => 'clinic-a-'.uniqid()]);
        $pb = Partner::create(['name' => 'Clinic B', 'slug' => 'clinic-b-'.uniqid()]);
        $ba = Branch::create(['partner_id' => $pa->id, 'name' => ['en' => 'A Br'], 'slug' => 'a-'.uniqid(), 'is_available' => true]);
        $bb = Branch::create(['partner_id' => $pb->id, 'name' => ['en' => 'B Br'], 'slug' => 'b-'.uniqid(), 'is_available' => true]);
        $patA = Patient::create(['partner_id' => $pa->id, 'name' => 'Patient A', 'phone' => '+96591'.random_int(100000, 999999)]);
        $patB = Patient::create(['partner_id' => $pb->id, 'name' => 'Patient B', 'phone' => '+96592'.random_int(100000, 999999)]);

        return compact('pa', 'pb', 'ba', 'bb', 'patA', 'patB');
    }

    private function user(string $email): User
    {
        return User::create(['name' => 'U', 'email' => $email.'-'.uniqid().'@t.local', 'password' => Hash::make('password')]);
    }

    public function test_non_admin_only_sees_their_clinics_patients(): void
    {
        $c = $this->twoClinics();
        $u = $this->user('staff');
        // Attach the user to Clinic A's branch only.
        DB::table('branch_user')->insert(['user_id' => $u->id, 'branch_id' => $c['ba']->id]);

        $this->actingAs($u);

        $names = Patient::query()->pluck('name')->all();
        $this->assertContains('Patient A', $names, 'should see own clinic patient');
        $this->assertNotContains('Patient B', $names, 'must NOT see another clinic patient');
        $this->assertCount(2, Patient::withoutGlobalScopes()->get(), 'both exist globally');
    }

    public function test_admin_sees_every_clinics_patients(): void
    {
        $c = $this->twoClinics();
        Role::findOrCreate('admin', 'web');
        $admin = $this->user('admin');
        $admin->assignRole('admin');

        $this->actingAs($admin);

        $names = Patient::query()->pluck('name')->all();
        $this->assertContains('Patient A', $names);
        $this->assertContains('Patient B', $names);
    }

    public function test_no_clinic_membership_sees_no_patients(): void
    {
        $this->twoClinics();
        $u = $this->user('orphan'); // no branch_user / partner_user / doctor row
        $this->actingAs($u);

        $this->assertCount(0, Patient::query()->get(), 'a user with no clinic sees nothing');
    }

    public function test_branch_scope_isolates_bookings(): void
    {
        $c = $this->twoClinics();
        $bkA = Booking::create(['branch_id' => $c['ba']->id, 'patient_id' => $c['patA']->id, 'msisdn' => '', 'party_size' => 1, 'status' => 'confirmed', 'booking_code' => 'A'.uniqid(), 'res_date' => now()->toDateString(), 'res_time' => '09:00']);
        $bkB = Booking::create(['branch_id' => $c['bb']->id, 'patient_id' => $c['patB']->id, 'msisdn' => '', 'party_size' => 1, 'status' => 'confirmed', 'booking_code' => 'B'.uniqid(), 'res_date' => now()->toDateString(), 'res_time' => '09:00']);

        $u = $this->user('recep');
        DB::table('branch_user')->insert(['user_id' => $u->id, 'branch_id' => $c['ba']->id]);
        $this->actingAs($u);

        $ids = Booking::query()->pluck('id')->all();
        $this->assertContains($bkA->id, $ids);
        $this->assertNotContains($bkB->id, $ids, 'must not see another branch booking');
    }
}
