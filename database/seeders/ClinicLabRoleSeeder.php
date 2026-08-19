<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Lab assistant / technician role (clinic_lab) + the bench permissions nurses
 * need to cover the lab in a smaller branch.
 *
 * The lab role is deliberately narrow. A lab assistant needs to know who the
 * sample belongs to and what was asked for, then type results back — nothing
 * else. So they get:
 *
 *   read     patients · visits · doctors · lab test catalogue
 *   work     lab orders + lab order items (view/create/update — no delete)
 *   files    view + upload patient files (the analyser printout / scan)
 *   self     own attendance + leave requests
 *
 * and explicitly NOT: billing, payments, accounting, insurance, stock, payroll,
 * users/roles, or any delete verb. A released report is a clinical record — the
 * lab can never remove one, which is why delete is missing everywhere here.
 *
 * Nurses also get update_lab_orders / update_lab_order_items: in a single-branch
 * clinic the nurse IS the lab assistant, and without the update verb the bench
 * actions (collect sample, enter results) are all read-only for them. A clinic
 * with a dedicated technician can revoke it from clinic_nurse.
 *
 * Additive throughout — never strips a permission from a working role.
 */
class ClinicLabRoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedLabRole();
        $this->extendNurseToBench();
    }

    protected function seedLabRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'clinic_lab', 'guard_name' => 'web']);

        $names = collect()
            // Context the bench needs to identify the sample and the request.
            ->merge($this->verbs(['view'], [
                'patients', 'doctors', 'visits', 'lab_tests',
            ]))
            // The work itself. create_* lets the lab add a test the doctor
            // forgot to an order that is still open.
            ->merge($this->verbs(['view', 'create', 'update'], [
                'lab_orders', 'lab_order_items',
            ]))
            // Own attendance + leave (controllers scope non-HR users to "self").
            ->merge($this->verbs(['view'], ['staff_attendances', 'staff_leaves']))
            ->merge($this->verbs(['create'], ['staff_leaves']))
            // Attach the analyser printout / scanned report to the order.
            ->merge(['patient_files_view', 'patient_files_upload'])
            ->unique();

        $this->grant($role, $names->all());
    }

    protected function extendNurseToBench(): void
    {
        $nurse = Role::where('name', 'clinic_nurse')->where('guard_name', 'web')->first();
        if (! $nurse) {
            return;
        }

        $this->grant($nurse, [
            'update_lab_orders', 'create_lab_orders',
            'update_lab_order_items', 'create_lab_order_items',
        ]);
    }

    /** Additive grant: union of what the role already has and what we asked for. */
    protected function grant(Role $role, array $permissionNames): void
    {
        $perms = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $existing = $role->permissions()->pluck('id')->all();

        $role->syncPermissions(array_values(array_unique(array_merge($existing, $perms))));
    }

    /**
     * Same verb→permission-name expansion the other clinic role seeders use, so
     * the grants line up with the Filament permission catalogue.
     */
    protected function verbs(array $verbs, array $resources): \Illuminate\Support\Collection
    {
        $names = collect();

        foreach ($resources as $r) {
            foreach ($verbs as $v) {
                match ($v) {
                    'view' => $names->push("view_any_{$r}", "view_{$r}"),
                    'create' => $names->push("create_{$r}"),
                    'update' => $names->push("update_{$r}"),
                    default => null,
                };
            }
        }

        return $names;
    }
}
