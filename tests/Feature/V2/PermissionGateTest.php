<?php

namespace Tests\Feature\V2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The v2 routes sit behind only EnsureCanAccessAdminPanel (auth + active +
 * has-a-role) — every controller must self-gate. These lock two gates:
 *  - a named-permission gate (doctors needs view_any_doctors)
 *  - a role gate (bookings needs reception/admin via canManageBooking)
 */
class PermissionGateTest extends TestCase
{
    use RefreshDatabase;

    private function staffUser(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $u = User::create([
            'name' => 'Staff', 'email' => 'staff-'.uniqid().'@t.local',
            'password' => Hash::make('password'), 'status' => 'active',
        ]);
        $u->assignRole($role);

        return $u;
    }

    public function test_doctors_page_requires_view_any_doctors_permission(): void
    {
        $u = $this->staffUser('clinic_doctor'); // has a role, but not the permission

        $this->actingAs($u)->get('/admin/v2/doctors')->assertForbidden();

        Permission::findOrCreate('view_any_doctors', 'web');
        $u->givePermissionTo('view_any_doctors');
        $u->forgetCachedPermissions();

        $this->actingAs($u->fresh())->get('/admin/v2/doctors')->assertOk();
    }

    public function test_bookings_require_reception_or_admin_role(): void
    {
        // A plain clinical role is NOT reception → blocked.
        $doctor = $this->staffUser('clinic_doctor');
        $this->actingAs($doctor)->get('/admin/v2/bookings')->assertForbidden();

        // Reception desk → allowed.
        $reception = $this->staffUser('clinic_reception');
        $this->actingAs($reception)->get('/admin/v2/bookings')->assertOk();
    }

    public function test_unauthenticated_is_redirected_off_v2(): void
    {
        $this->get('/admin/v2/dashboard')->assertRedirect();
    }
}
