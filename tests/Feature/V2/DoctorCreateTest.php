<?php

namespace Tests\Feature\V2;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Locks the v2 doctor-create parity with the old Filament flow:
 *  - the email IS the login: a User is found-or-created and given the
 *    clinic_doctor role (no "pick a user" dropdown),
 *  - partner_id is taken from the chosen branch (never the client),
 *  - specialty + fee>0 are required.
 */
class DoctorCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_doctor_provisions_a_clinic_doctor_user(): void
    {
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('clinic_doctor', 'web'); // controller assigns this
        foreach (['view_any_doctors', 'update_doctors'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $partner = Partner::create(['name' => 'P', 'slug' => 'p-'.uniqid()]);
        $branch = Branch::create(['partner_id' => $partner->id, 'name' => ['en' => 'B'], 'slug' => 'b-'.uniqid(), 'is_available' => true]);

        $admin = User::create(['name' => 'A', 'email' => 'a-'.uniqid().'@t.local', 'password' => Hash::make('password'), 'status' => 'active']);
        $admin->assignRole('admin');
        $admin->givePermissionTo('view_any_doctors', 'update_doctors');

        $email = 'newdoc-'.uniqid().'@clinic.local';
        $resp = $this->actingAs($admin->fresh())->post('/admin/v2/doctors', [
            'name' => 'Dr. New',
            'specialty' => 'Cardiology',
            'email' => $email,
            'consultation_fee' => 15,
            'partner_id' => $partner->id,
            'branch_id' => $branch->id,
        ]);

        $resp->assertSessionHasNoErrors();
        $resp->assertRedirect();

        $doctor = Doctor::where('email', $email)->first();
        $this->assertNotNull($doctor, 'doctor row created');
        $this->assertSame($branch->id, $doctor->branch_id);
        $this->assertSame($partner->id, $doctor->partner_id, 'partner derived from branch');

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user, 'login user provisioned from email');
        $this->assertTrue($user->hasRole('clinic_doctor'), 'user got clinic_doctor role');
        $this->assertSame($user->id, $doctor->user_id, 'doctor linked to the new user');
    }

    public function test_doctor_requires_specialty_and_positive_fee(): void
    {
        Role::findOrCreate('admin', 'web');
        foreach (['view_any_doctors', 'update_doctors'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $partner = Partner::create(['name' => 'P', 'slug' => 'p-'.uniqid()]);
        $branch = Branch::create(['partner_id' => $partner->id, 'name' => ['en' => 'B'], 'slug' => 'b-'.uniqid(), 'is_available' => true]);
        $admin = User::create(['name' => 'A', 'email' => 'a-'.uniqid().'@t.local', 'password' => Hash::make('password'), 'status' => 'active']);
        $admin->assignRole('admin');
        $admin->givePermissionTo('view_any_doctors', 'update_doctors');

        $resp = $this->actingAs($admin->fresh())->post('/admin/v2/doctors', [
            'name' => 'Dr. NoSpec',
            'email' => 'x-'.uniqid().'@t.local',
            'consultation_fee' => 0, // invalid: must be > 0
            'partner_id' => $partner->id,
            'branch_id' => $branch->id,
        ]);

        $resp->assertSessionHasErrors(['specialty', 'consultation_fee']);
        $this->assertSame(0, Doctor::count(), 'nothing created on validation failure');
    }
}
