<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Canonical clinic role structure. Runs AFTER ClinicFilamentPermissionSeeder
 * (which builds the permission catalog + the existing role grants). This seeder:
 *
 *   1. Adds the Nurse / clinical-assistant role (clinic_nurse) with a curated,
 *      additive permission set.
 *   2. Consolidates the role list — migrates any users off the redundant
 *      branch_manager / clinic_manager / branch_staff roles onto the canonical
 *      roles, then removes those roles.
 *
 * Canonical roles after this runs:
 *   Staff:     admin · clinic_admin (manager) · clinic_doctor · clinic_reception
 *              · clinic_nurse · accountant
 *   Non-staff: customer (patient portal) · partner_owner (multi-clinic owner)
 *
 * Grants are additive (no syncPermissions that would strip a working role).
 */
class ClinicRoleStructureSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedNurse();
        $this->seedClinicalLibrary();
        $this->consolidateRoles();
    }

    /**
     * Clinical Library (quick phrases + drug formulary): managers and doctors
     * can curate the catalogs; nurses get read access. Admin already has every
     * permission from ClinicFilamentPermissionSeeder. Additive.
     */
    protected function seedClinicalLibrary(): void
    {
        $resources = ['clinical_phrases', 'medications'];

        $grants = [
            'clinic_admin' => $this->verbs(['view', 'create', 'update', 'delete'], $resources),
            'clinic_doctor' => $this->verbs(['view', 'create', 'update', 'delete'], $resources),
            'clinic_nurse' => $this->verbs(['view'], $resources),
        ];

        foreach ($grants as $roleName => $names) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $perms = Permission::where('guard_name', 'web')->whereIn('name', $names->all())->get();
            $existing = $role->permissions()->pluck('id')->all();
            $role->syncPermissions(array_values(array_unique(array_merge($existing, $perms->pluck('id')->all()))));
        }
    }

    /**
     * Nurse = clinical assistant: triage/vitals, queue, prep stock for procedures,
     * read clinical + lab context. NOT billing, accounting, insurance admin, or
     * staff/role management.
     */
    protected function seedNurse(): void
    {
        $nurse = Role::firstOrCreate(['name' => 'clinic_nurse', 'guard_name' => 'web']);

        $names = collect()
            // Read clinical + operational context.
            ->merge($this->verbs(['view'], [
                'patients', 'doctors', 'visits', 'visit_items', 'visit_charges',
                'visit_stock_request', 'clinic_items', 'clinic_item_stocks',
                'clinic_stock_movement', 'clinic_packages', 'lab_tests', 'lab_orders',
                'lab_order_items', 'follow_up_plans', 'admissions', 'wards', 'beds',
                'waiting_patients', 'nurse_station', 'check-in-scanner',
                'clinic-dashboard', 'doctor-schedule',
            ]))
            // Update the visit (vitals / triage / queue) — visit perms are
            // whole-record, so this also lets a nurse touch clinical notes; that's
            // an accepted granularity trade-off for trusted clinical staff.
            ->merge($this->verbs(['update'], ['visits']))
            // Record consumables used + request stock during a procedure.
            ->merge($this->verbs(['create'], ['visit_items', 'visit_stock_request']))
            // Own attendance + leave (controllers scope non-HR to "self").
            ->merge($this->verbs(['view'], ['staff_attendances', 'staff_leaves']))
            ->merge($this->verbs(['create'], ['staff_leaves']))
            // Capability perms.
            ->merge(['patient_files_view', 'patient_files_upload'])
            ->unique();

        $perms = Permission::where('guard_name', 'web')->whereIn('name', $names->all())->get();

        // Additive: keep anything already on the role.
        $existing = $nurse->permissions()->pluck('id')->all();
        $nurse->syncPermissions(array_values(array_unique(array_merge($existing, $perms->pluck('id')->all()))));
    }

    /**
     * Remove the redundant/legacy roles. Any users on them are first moved to the
     * closest canonical role so nobody loses access.
     */
    protected function consolidateRoles(): void
    {
        $migrations = [
            'branch_manager' => 'clinic_admin',   // manager → clinic manager
            'clinic_manager' => 'clinic_admin',
            'branch_staff' => 'clinic_reception', // generic staff → front desk
        ];

        foreach ($migrations as $from => $to) {
            $oldRole = Role::where('name', $from)->where('guard_name', 'web')->first();
            if (! $oldRole) {
                continue;
            }

            $target = Role::where('name', $to)->where('guard_name', 'web')->first();
            if ($target) {
                foreach ($oldRole->users as $user) {
                    if (! $user->hasRole($target->name)) {
                        $user->assignRole($target->name);
                    }
                    $user->removeRole($oldRole->name);
                }
            }

            $oldRole->delete();
        }
    }

    /**
     * Expand (verbs × resources) to permission names. 'view' → view_any_X + view_X,
     * 'create' → create_X, 'update' → update_X, 'delete' → delete/restore family.
     */
    protected function verbs(array $verbs, array $resources): Collection
    {
        $names = collect();
        foreach ($resources as $r) {
            foreach ($verbs as $v) {
                match ($v) {
                    'view' => $names->push("view_any_{$r}", "view_{$r}"),
                    'create' => $names->push("create_{$r}"),
                    'update' => $names->push("update_{$r}"),
                    'delete' => $names->push("delete_{$r}", "delete_any_{$r}", "force_delete_{$r}", "restore_{$r}"),
                    default => null,
                };
            }
        }

        return $names;
    }
}
