<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Pre-launch demo logins for the v2 admin.
 *
 * Creates a branch manager (clinic_admin) and an accountant, both scoped to the
 * SAME branch the existing demo doctor/reception users sit on so all three see
 * one consistent clinic's data. Scoping is via the branch_user pivot only — a
 * single branch, not partner_user (which would unlock every branch of the
 * clinic). See ResolvesAccessibleClinics::accessibleBranchIds().
 *
 * Companion to the existing admin@platform.com / admin@doctor.com /
 * admin@reception.com accounts. All passwords are `password`.
 */
class DemoStaffUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Land on the branch the existing demo doctor/reception use, so the new
        // accounts share the same seeded data. Fall back to the hub, then first.
        $branchId = (int) (DB::table('branch_user')
            ->join('users', 'users.id', '=', 'branch_user.user_id')
            ->where('users.email', 'admin@reception.com')
            ->orderBy('branch_user.branch_id')
            ->value('branch_user.branch_id')
            ?: Branch::query()->withoutGlobalScopes()->where('is_hub', true)->value('id')
            ?: Branch::query()->withoutGlobalScopes()->orderBy('id')->value('id'));

        if (! $branchId) {
            $this->command?->warn('DemoStaffUsersSeeder: no branch found — skipping.');

            return;
        }

        $accounts = [
            [
                'name' => 'Branch Manager',
                'email' => 'admin@branch.com',
                'role' => 'clinic_admin',
            ],
            [
                'name' => 'Clinic Accountant',
                'email' => 'admin@accountant.com',
                'role' => 'accountant',
            ],
            [
                'name' => 'Lab Assistant',
                'email' => 'admin@lab.com',
                'role' => 'clinic_lab',
            ],
        ];

        foreach ($accounts as $acct) {
            $user = User::firstOrCreate(
                ['email' => $acct['email']],
                [
                    'name' => $acct['name'],
                    'password' => bcrypt('password'),
                    'status' => 'active',
                ],
            );

            // Keep it usable even if the row pre-existed inactive / unverified.
            $user->forceFill([
                'status' => 'active',
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            // Single-branch scope via branch_user.
            $user->branchLinks()->syncWithoutDetaching([$branchId]);

            // Exactly the requested role.
            $user->syncRoles([$acct['role']]);

            $this->command?->info("DemoStaffUsersSeeder: {$acct['email']} ({$acct['role']}) -> branch {$branchId}");
        }
    }
}
