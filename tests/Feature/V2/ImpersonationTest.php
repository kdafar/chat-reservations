<?php

namespace Tests\Feature\V2;

use App\Models\User;
use App\Services\Auth\ImpersonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Log in as" for global admins. Locks who may impersonate whom, that the
 * way back always works, and that the audit trail names the real actor.
 */
class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = null, array $attrs = []): User
    {
        $u = User::create(array_merge([
            'name' => ucfirst($role ?? 'nobody').' '.uniqid(),
            'email' => ($role ?? 'nobody').'-'.uniqid().'@t.local',
            'password' => Hash::make('password'), 'status' => 'active',
        ], $attrs));
        if ($role) {
            $u->assignRole(Role::findOrCreate($role, 'web'));
        }

        return $u->fresh();
    }

    private function admin(): User
    {
        $a = $this->user('admin');
        $a->givePermissionTo(Permission::findOrCreate('view_any_user', 'web'));

        return $a->fresh();
    }

    private function startAs(User $admin, User $target)
    {
        return $this->actingAs($admin)->post("/admin/v2/users/{$target->id}/impersonate");
    }

    public function test_admin_can_log_in_as_a_staff_user_and_back(): void
    {
        $admin = $this->admin();
        $reception = $this->user('clinic_reception');

        $this->startAs($admin, $reception)->assertRedirect(route('v2.dashboard'));
        $this->assertAuthenticatedAs($reception);
        $this->assertSame($admin->id, session(ImpersonationService::SESSION_KEY));
        $this->assertDatabaseHas('activity_log', [
            'event' => 'impersonation_started', 'causer_id' => $admin->id, 'subject_id' => $reception->id,
        ]);

        $this->post('/admin/v2/impersonation/stop')->assertRedirect(route('v2.users.index'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session(ImpersonationService::SESSION_KEY));
        $this->assertDatabaseHas('activity_log', ['event' => 'impersonation_ended', 'causer_id' => $admin->id]);
    }

    public function test_actions_while_impersonating_are_stamped_with_the_real_admin(): void
    {
        $admin = $this->admin();
        $doctor = $this->user('clinic_doctor');
        $this->startAs($admin, $doctor);

        activity('test')->causedBy($doctor)->log('did something');

        $row = Activity::query()->where('log_name', 'test')->firstOrFail();
        $this->assertSame($doctor->id, (int) $row->causer_id);
        $this->assertSame($admin->id, (int) $row->properties['impersonated_by']);
    }

    public function test_banner_prop_is_shared_only_while_impersonating(): void
    {
        $admin = $this->admin();
        $reception = $this->user('clinic_reception');
        $reception->givePermissionTo('view_any_user'); // any v2 Inertia page will do

        $this->actingAs($admin)->get('/admin/v2/users')
            ->assertInertia(fn (AssertableInertia $p) => $p->where('auth.impersonator', null));

        $this->startAs($admin, $reception);
        $this->get('/admin/v2/users')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('auth.user.id', $reception->id)
                ->where('auth.impersonator.id', $admin->id));
    }

    public function test_only_global_admins_may_impersonate(): void
    {
        $target = $this->user('clinic_reception');

        foreach (['clinic_admin', 'clinic_doctor', 'clinic_reception'] as $role) {
            $actor = $this->user($role);
            $this->startAs($actor, $target);
            $this->assertAuthenticatedAs($actor);
            $this->assertNull(session(ImpersonationService::SESSION_KEY), "{$role} must not impersonate");
        }

        // super_admin counts as a global admin.
        $super = $this->user('super_admin');
        $this->startAs($super, $target);
        $this->assertAuthenticatedAs($target);
    }

    public function test_refused_targets(): void
    {
        $admin = $this->admin();
        $cases = [
            'another admin' => $this->user('admin'),
            'a super admin' => $this->user('super_admin'),
            'an inactive user' => $this->user('clinic_doctor', ['status' => 'inactive']),
            'a user with no role' => $this->user(null),
            'yourself' => $admin,
        ];

        foreach ($cases as $label => $target) {
            $this->startAs($admin, $target)->assertSessionHas('error');
            $this->assertAuthenticatedAs($admin);
            $this->assertNull(session(ImpersonationService::SESSION_KEY), "must refuse {$label}");
        }
        $this->assertSame(0, Activity::where('event', 'impersonation_started')->count());
    }

    public function test_no_nesting(): void
    {
        $admin = $this->admin();
        $first = $this->user('clinic_reception');
        $second = $this->user('clinic_nurse');
        $this->startAs($admin, $first);

        // The impersonated account isn't an admin, and even the service's
        // own nesting guard would refuse — either way we stay put.
        $this->post("/admin/v2/users/{$second->id}/impersonate");
        $this->assertAuthenticatedAs($first);
        $this->assertSame($admin->id, session(ImpersonationService::SESSION_KEY));
    }

    public function test_way_back_works_after_the_account_loses_access(): void
    {
        $admin = $this->admin();
        $doctor = $this->user('clinic_doctor');
        $this->startAs($admin, $doctor);

        // Deactivated mid-session: every v2 page now 403s for this account…
        $doctor->update(['status' => 'inactive']);
        $this->app['auth']->forgetGuards(); // next request reloads the user, like a real one
        $this->get('/admin/v2/dashboard')->assertForbidden();

        // …but the return route still works.
        $this->post('/admin/v2/impersonation/stop')->assertRedirect(route('v2.users.index'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_demoted_admin_is_logged_out_instead_of_restored(): void
    {
        $admin = $this->admin();
        $doctor = $this->user('clinic_doctor');
        $this->startAs($admin, $doctor);

        $admin->removeRole('admin');

        $this->post('/admin/v2/impersonation/stop');
        $this->assertGuest();
    }

    public function test_stop_without_impersonating_changes_nothing(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/v2/impersonation/stop')->assertRedirect(route('v2.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_users_list_flags_who_can_be_impersonated(): void
    {
        $admin = $this->admin();
        $reception = $this->user('clinic_reception');
        $otherAdmin = $this->user('admin');

        $this->actingAs($admin)->get('/admin/v2/users')
            ->assertInertia(function (AssertableInertia $p) use ($admin, $reception, $otherAdmin) {
                $rows = collect($p->toArray()['props']['page']['data'])->keyBy('id');
                $this->assertTrue($rows[$reception->id]['can_impersonate']);
                $this->assertFalse($rows[$otherAdmin->id]['can_impersonate']);
                $this->assertFalse($rows[$admin->id]['can_impersonate']);
            });
    }
}
