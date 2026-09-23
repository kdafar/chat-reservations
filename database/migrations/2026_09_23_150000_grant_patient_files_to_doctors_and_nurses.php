<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Doctors and nurses view and upload patient files (before/after photos,
 * consent, reports) from the visit — the workspace's Files tab. Same grant as
 * ClinicRoleStructureSeeder::seedClinicalFileAccess(), for installs that
 * already exist. Additive and idempotent: nothing is revoked, and a role or
 * permission that doesn't exist here is skipped.
 */
return new class extends Migration
{
    private const ROLES = ['clinic_doctor', 'clinic_nurse'];

    private const PERMISSIONS = ['patient_files_view', 'patient_files_upload'];

    public function up(): void
    {
        $roles = DB::table('roles')->where('guard_name', 'web')->whereIn('name', self::ROLES)->pluck('id');
        $perms = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', self::PERMISSIONS)->pluck('id');
        foreach ($roles as $roleId) {
            foreach ($perms as $permId) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permId]);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Left in place: removing file access could hide files staff already rely on.
    }
};
