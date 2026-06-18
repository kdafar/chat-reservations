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
        $this->seedStockTransfers();
        $this->seedPurchaseOrders();
        $this->restrictDoctorCatalogToReadOnly();
        $this->pruneLegacyOverGrants();
        $this->consolidateRoles();
    }

    /**
     * Inter-branch stock transfers: clinical/front-desk staff can REQUEST a
     * transfer (view+create); dispatching it (update — moves hub stock) and
     * cancelling are limited to clinic_admin + reception (the hub/front desk).
     * Admin bypasses. Additive.
     */
    protected function seedStockTransfers(): void
    {
        $grants = [
            'clinic_admin' => $this->verbs(['view', 'create', 'update', 'delete'], ['stock_transfers']),
            'clinic_reception' => $this->verbs(['view', 'create', 'update', 'delete'], ['stock_transfers']),
            // Doctors do NOT manage stock transfers (revoked below in
            // pruneLegacyOverGrants for already-seeded DBs).
            'clinic_nurse' => $this->verbs(['view', 'create'], ['stock_transfers']),
        ];

        foreach ($grants as $roleName => $names) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $perms = Permission::where('guard_name', 'web')->whereIn('name', $names->all())->get();
            $existing = $role->permissions()->pluck('id')->all();
            $role->syncPermissions(array_values(array_unique(array_merge($existing, $perms->pluck('id')->all()))));
        }
    }

    /**
     * Purchasing: clinic_admin can do everything (create/approve/receive/pay/
     * cancel = view+create+update+delete). Reception can raise + manage POs
     * (view+create+update) but not the destructive delete family. Doctors get
     * read-only visibility. Admin bypasses. Additive.
     */
    protected function seedPurchaseOrders(): void
    {
        // Custom permissions beyond the generated verb set, for segregation of
        // duties: approving a PO and paying a vendor are gated separately from
        // raising (create) and operating (update: send/receive/etc.) it.
        foreach (['approve_purchase_orders', 'pay_purchase_orders'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $grants = [
            // Admin: full control incl. approve + pay.
            'clinic_admin' => $this->verbs(['view', 'create', 'update', 'delete'], ['purchase_orders'])
                ->push('approve_purchase_orders', 'pay_purchase_orders'),
            // Reception/storekeeper: raise + operate (send/receive/cancel) but
            // CANNOT approve POs or pay vendors (separation of duties).
            'clinic_reception' => $this->verbs(['view', 'create', 'update'], ['purchase_orders']),
            // Doctors get NO purchasing visibility (revoked below for already-seeded DBs).
            // Accountant: read + settle vendor payments (finance), no approval/ops.
            'accountant' => $this->verbs(['view'], ['purchase_orders'])
                ->push('pay_purchase_orders'),
        ];

        foreach ($grants as $roleName => $names) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $perms = Permission::where('guard_name', 'web')->whereIn('name', $names->all())->get();
            $existing = $role->permissions()->pluck('id')->all();
            $role->syncPermissions(array_values(array_unique(array_merge($existing, $perms->pluck('id')->all()))));
        }

        // The platform super-role syncs "all permissions" earlier in
        // ClinicFilamentPermissionSeeder — before these custom perms existed —
        // so grant them explicitly here too.
        if ($admin = Role::where('name', 'admin')->where('guard_name', 'web')->first()) {
            $admin->givePermissionTo('approve_purchase_orders', 'pay_purchase_orders');
        }
    }

    /**
     * Strip legacy/over-broad permissions that accumulated on non-admin roles:
     *  - clinic_doctor could read EVERY doctor's pay (compensation ledgers +
     *    profiles) — an orphan grant from old seeding. Revoke.
     *  - clinic_doctor held the destructive delete family (delete_any /
     *    force_delete / restore / delete) on the shared medications + quick-phrase
     *    catalogs (verbs('delete') over-expands). Doctors keep view/create/update
     *    only; hard catalog management stays with admin/clinic_admin.
     *  - clinic_reception could DELETE lab orders + results (clinical records);
     *    front desk should create/update only.
     */
    protected function pruneLegacyOverGrants(): void
    {
        $revoke = function (string $roleName, array $names): void {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                return;
            }
            foreach ($names as $name) {
                if (($p = Permission::where('guard_name', 'web')->where('name', $name)->first()) && $role->hasPermissionTo($p)) {
                    $role->revokePermissionTo($p);
                }
            }
        };

        // Doctor: no staff pay-data, no destructive catalog ops, and no
        // back-office stock/purchasing surfaces (purchase orders, packages,
        // stock transfers). Doctors keep read-only Items + Stock so they can
        // check availability during a visit.
        $doctorRevoke = [
            'view_any_doctor_compensation_ledgers', 'view_doctor_compensation_ledgers',
            'view_any_doctor_compensation_profiles', 'view_doctor_compensation_profiles',
            'view_any_purchase_orders', 'view_purchase_orders',
            'view_any_clinic_packages', 'view_clinic_packages',
            'view_any_stock_transfers', 'view_stock_transfers', 'create_stock_transfers',
        ];
        foreach (['medications', 'clinical_phrases'] as $r) {
            $doctorRevoke[] = "delete_{$r}";
            $doctorRevoke[] = "delete_any_{$r}";
            $doctorRevoke[] = "force_delete_{$r}";
            $doctorRevoke[] = "force_delete_any_{$r}";
            $doctorRevoke[] = "restore_{$r}";
            $doctorRevoke[] = "restore_any_{$r}";
        }
        $revoke('clinic_doctor', $doctorRevoke);

        // Reception: cannot delete clinical lab records.
        $revoke('clinic_reception', ['delete_lab_orders', 'delete_lab_order_items']);

        // Accounting audit trail: posted journal entries + accounting periods must
        // never be destroyed (they have no soft-delete column, so any delete is a
        // hard delete). The correct correction is a reversing entry / re-open, not
        // deletion. Strip the whole delete family from finance roles; keep
        // view/create/update.
        $glDelete = [];
        foreach (['accounting_journal_entries', 'accounting_periods'] as $r) {
            foreach (['delete', 'delete_any', 'force_delete', 'force_delete_any', 'restore', 'restore_any'] as $v) {
                $glDelete[] = "{$v}_{$r}";
            }
        }
        $revoke('accountant', $glDelete);
        $revoke('clinic_admin', $glDelete);

        // clinic_admin is a BRANCH-OPERATIONS role with financial OVERSIGHT. It
        // may SEE the whole general ledger and every financial report (a branch
        // manager needs visibility), but the accountant owns the writes: posting
        // journals, opening/closing periods, running payroll, paying vendors.
        // So clinic_admin is trimmed to READ-ONLY on accounting, loses payroll
        // entirely (sensitive salary data), and loses vendor payment + branch
        // record edits. KEEPS: all accounting *views* + every report, plus the
        // operational stack (patients, visits, stock, purchasing raise/approve,
        // insurance ops, inpatient, lab, clinical library).
        $mutationVerbs = ['create', 'update', 'delete', 'delete_any',
            'force_delete', 'force_delete_any', 'restore', 'restore_any', 'reorder'];

        $branchOpsRevoke = [];
        // General ledger + vendor master: keep view/list, drop every WRITE verb.
        // Financial statement report pages (trial balance, P&L, balance sheet,
        // cash flow, general ledger) are view-only perms and are intentionally
        // left untouched so the branch manager keeps full report visibility.
        foreach ([
            'accounting_accounts', 'accounting_bank_reconciliations',
            'accounting_expenses', 'accounting_journal_entries',
            'accounting_periods', 'accounting_vendors',
        ] as $r) {
            foreach ($mutationVerbs as $v) {
                $branchOpsRevoke[] = "{$v}_{$r}";
            }
        }
        // Payroll + staff compensation: sensitive pay data → accountant only.
        // Removed outright, including view.
        foreach ([
            'payroll_runs', 'staff_compensation_profiles', 'staff_loans',
            'staff_settlements', 'staff_leave_entitlements',
        ] as $r) {
            foreach (array_merge(['view_any', 'view'], $mutationVerbs) as $v) {
                $branchOpsRevoke[] = "{$v}_{$r}";
            }
        }
        // Doctor pay ledgers, vendor payment, and editing/deleting branch records.
        $branchOpsRevoke = array_merge($branchOpsRevoke, [
            'view_any_doctor_compensation_ledgers', 'view_doctor_compensation_ledgers',
            'pay_purchase_orders',
            'branches.update', 'branches.delete',
        ]);
        $revoke('clinic_admin', $branchOpsRevoke);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Doctors may VIEW the items / stock / packages catalogs (to check
     * availability) but must not edit pricing or adjust inventory — that's
     * management/pharmacy work. Revoke every non-view verb on those resources
     * from clinic_doctor while keeping read access. (Legacy seeding had granted
     * doctors update_clinic_items / update_clinic_item_stocks.)
     */
    protected function restrictDoctorCatalogToReadOnly(): void
    {
        $doctor = Role::where('name', 'clinic_doctor')->where('guard_name', 'web')->first();
        if (! $doctor) {
            return;
        }

        foreach (['clinic_items', 'clinic_item_stocks', 'clinic_packages'] as $r) {
            // Revoke create/update/delete/restore/reorder — everything but view.
            $toRevoke = Permission::query()
                ->where('guard_name', 'web')
                ->where('name', 'like', '%_'.$r)
                ->whereNotIn('name', ["view_any_{$r}", "view_{$r}"])
                ->get();
            foreach ($toRevoke as $perm) {
                if ($doctor->hasPermissionTo($perm)) {
                    $doctor->revokePermissionTo($perm);
                }
            }

            // Ensure read access stays.
            foreach (["view_any_{$r}", "view_{$r}"] as $name) {
                if ($p = Permission::where('guard_name', 'web')->where('name', $name)->first()) {
                    $doctor->givePermissionTo($p);
                }
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
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
            // Doctors contribute phrases/drugs but don't destructively manage the
            // shared catalogs — view/create/update only (no delete family).
            'clinic_doctor' => $this->verbs(['view', 'create', 'update'], $resources),
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
