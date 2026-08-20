<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Grants clinic_admin the FULL permission catalogue.
 *
 * clinic_admin originally shipped as a branch-OPERATIONS role: no Platform
 * section, no payroll, read-only accounting. On this install the clinic owner
 * asked for the role to be unrestricted, so it now mirrors `admin`.
 *
 * Consequence to keep in mind: this is a ROLE-level grant, so every user that
 * ever gets clinic_admin becomes a full administrator — including audit log,
 * gateway credentials, and Roles & Permissions (which is itself a
 * privilege-escalation surface). If clinic_admin is later handed to staff who
 * should NOT have that reach, split them onto a narrower role rather than
 * trimming this one back.
 *
 * Note ClinicRoleStructureSeeder::pruneLegacyOverGrants() revokes a set of
 * permissions from clinic_admin (payroll, accounting writes, doctor pay
 * ledgers). This seeder MUST run after it or those revokes will win.
 *
 * Idempotent: re-running is a no-op once every permission is attached.
 */
class ClinicAdminFullAccessSeeder extends Seeder
{
    private const ROLE = 'clinic_admin';

    public function run(): void
    {
        $role = Role::where('name', self::ROLE)->where('guard_name', 'web')->first();

        if (! $role) {
            $this->command?->warn(self::ROLE.' role not found — nothing to do.');

            return;
        }

        $before = $role->permissions()->count();

        $role->syncPermissions(Permission::where('guard_name', 'web')->get());

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $after = $role->permissions()->count();

        $this->command?->info(self::ROLE." permissions: {$before} -> {$after}.");
    }
}
