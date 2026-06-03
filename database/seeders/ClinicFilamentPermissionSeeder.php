<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ClinicFilamentPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $mapping = (array) config('clinic-filament-policies.mapping', []);
        $resources = array_values(array_unique(array_filter(array_values($mapping))));

        foreach ($resources as $resource) {
            $this->perm("view_any_{$resource}");
            $this->perm("view_{$resource}");
            $this->perm("create_{$resource}");
            $this->perm("update_{$resource}");
            $this->perm("delete_{$resource}");
            $this->perm("delete_any_{$resource}");
            $this->perm("force_delete_{$resource}");
            $this->perm("force_delete_any_{$resource}");
            $this->perm("restore_{$resource}");
            $this->perm("restore_any_{$resource}");
            $this->perm("reorder_{$resource}");
        }

        foreach ((array) config('clinic-filament-policies.pages', []) as $pageKey) {
            $this->perm("view_{$pageKey}");
        }

        // Keep admin always full access
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::where('guard_name', 'web')->get());

        // Optional clinic roles (create now, assign later)
        $clinicAdmin = Role::firstOrCreate(['name' => 'clinic_admin', 'guard_name' => 'web']);
        // clinic_manager intentionally NOT recreated — consolidated into clinic_admin
        // by ClinicRoleStructureSeeder. (branch_manager/branch_staff likewise removed.)
        Role::firstOrCreate(['name' => 'clinic_doctor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'clinic_reception', 'guard_name' => 'web']);

        // Accounting role: full access to all accounting resources + report pages
        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);

        $accountingPermissions = Permission::where('guard_name', 'web')
            ->where(function ($q) {
                $q->where('name', 'like', '%_accounting_%')
                    ->orWhere('name', 'like', 'view_accounting_%');
            })
            ->get();

        $accountant->syncPermissions($accountingPermissions);

        // clinic_admin also gets full accounting access (merged with anything it already has)
        $existing = $clinicAdmin->permissions()->pluck('id')->all();
        $clinicAdmin->syncPermissions(array_unique(array_merge($existing, $accountingPermissions->pluck('id')->all())));

        // ===== Insurance module permissions =====
        $insurancePermissionNames = [
            'insurance_view',
            'insurance_manage_policies',
            'insurance_submit_claim',
            'insurance_decide_claim',
            'insurance_record_payment',
            'insurance_writeoff',
        ];

        foreach ($insurancePermissionNames as $name) {
            $this->perm($name);
        }

        // Re-fetch admin's full perm set so the new insurance perms are included.
        $admin->syncPermissions(Permission::where('guard_name', 'web')->get());

        // clinic_admin: full insurance access (merge, preserve prior perms)
        $allInsurance = Permission::where('guard_name', 'web')
            ->whereIn('name', $insurancePermissionNames)
            ->get();

        $existing = $clinicAdmin->permissions()->pluck('id')->all();
        $clinicAdmin->syncPermissions(array_unique(array_merge($existing, $allInsurance->pluck('id')->all())));

        // clinic_reception (closest to a "reception" role): view, manage policies, submit claim
        $receptionRole = Role::where('name', 'clinic_reception')->where('guard_name', 'web')->first();
        if ($receptionRole) {
            $receptionPerms = Permission::where('guard_name', 'web')
                ->whereIn('name', [
                    'insurance_view',
                    'insurance_manage_policies',
                    'insurance_submit_claim',
                ])
                ->get();
            $existing = $receptionRole->permissions()->pluck('id')->all();
            $receptionRole->syncPermissions(array_unique(array_merge($existing, $receptionPerms->pluck('id')->all())));
        }

        // accountant (closest to a "finance" role): view, record payment, writeoff, decide claim
        $financeRole = Role::where('name', 'accountant')->where('guard_name', 'web')->first();
        if ($financeRole) {
            $financePerms = Permission::where('guard_name', 'web')
                ->whereIn('name', [
                    'insurance_view',
                    'insurance_record_payment',
                    'insurance_writeoff',
                    'insurance_decide_claim',
                ])
                ->get();
            $existing = $financeRole->permissions()->pluck('id')->all();
            $financeRole->syncPermissions(array_unique(array_merge($existing, $financePerms->pluck('id')->all())));
        }

        // ===== Patient Files / Medical Records module permissions =====
        $patientFilesPermissionNames = [
            'patient_files_view',
            'patient_files_upload',
            'patient_files_delete',
        ];

        foreach ($patientFilesPermissionNames as $name) {
            $this->perm($name);
        }

        // Re-fetch admin's full perm set so the new patient_files perms are included.
        $admin->syncPermissions(Permission::where('guard_name', 'web')->get());

        // clinic_admin: full patient files access (merge, preserve prior perms)
        $allPatientFiles = Permission::where('guard_name', 'web')
            ->whereIn('name', $patientFilesPermissionNames)
            ->get();

        $existing = $clinicAdmin->permissions()->pluck('id')->all();
        $clinicAdmin->syncPermissions(array_unique(array_merge($existing, $allPatientFiles->pluck('id')->all())));

        // clinic_reception: view + upload
        $receptionRole = Role::where('name', 'clinic_reception')->where('guard_name', 'web')->first();
        if ($receptionRole) {
            $receptionPatientFilesPerms = Permission::where('guard_name', 'web')
                ->whereIn('name', [
                    'patient_files_view',
                    'patient_files_upload',
                ])
                ->get();
            $existing = $receptionRole->permissions()->pluck('id')->all();
            $receptionRole->syncPermissions(array_unique(array_merge($existing, $receptionPatientFilesPerms->pluck('id')->all())));
        }

        // accountant: view only (for insurance card lookups)
        $accountantRole = Role::where('name', 'accountant')->where('guard_name', 'web')->first();
        if ($accountantRole) {
            $accountantPatientFilesPerms = Permission::where('guard_name', 'web')
                ->whereIn('name', [
                    'patient_files_view',
                ])
                ->get();
            $existing = $accountantRole->permissions()->pluck('id')->all();
            $accountantRole->syncPermissions(array_unique(array_merge($existing, $accountantPatientFilesPerms->pluck('id')->all())));
        }

        // ===== Inpatient module role assignments =====
        // Resource permissions (view_any_wards, etc.) are auto-created by the
        // mapping loop above. Here we just hand them out to the right roles.
        $admin->syncPermissions(Permission::where('guard_name', 'web')->get());

        $inpatientPerms = Permission::where('guard_name', 'web')
            ->where(function ($q) {
                $q->where('name', 'like', '%_wards')
                    ->orWhere('name', 'like', '%_beds')
                    ->orWhere('name', 'like', '%_admissions');
            })
            ->get();

        // clinic_admin: full inpatient access
        $existing = $clinicAdmin->permissions()->pluck('id')->all();
        $clinicAdmin->syncPermissions(array_unique(array_merge($existing, $inpatientPerms->pluck('id')->all())));

        // clinic_doctor: view + manage admissions (admit/transfer/discharge,
        // log rounds). Doesn't manage ward/bed setup.
        $doctorRole = Role::where('name', 'clinic_doctor')->where('guard_name', 'web')->first();
        if ($doctorRole) {
            $doctorAdmissionPerms = Permission::where('guard_name', 'web')
                ->whereIn('name', [
                    'view_any_admissions', 'view_admissions', 'create_admissions', 'update_admissions',
                    'view_any_wards', 'view_wards',
                    'view_any_beds', 'view_beds',
                ])
                ->get();
            $existing = $doctorRole->permissions()->pluck('id')->all();
            $doctorRole->syncPermissions(array_unique(array_merge($existing, $doctorAdmissionPerms->pluck('id')->all())));
        }

        // clinic_reception: view admissions + manage beds (housekeeping flips
        // bed status to cleaning/maintenance/available).
        $receptionRole = Role::where('name', 'clinic_reception')->where('guard_name', 'web')->first();
        if ($receptionRole) {
            $receptionInpatientPerms = Permission::where('guard_name', 'web')
                ->whereIn('name', [
                    'view_any_admissions', 'view_admissions',
                    'view_any_wards', 'view_wards',
                    'view_any_beds', 'view_beds', 'update_beds',
                ])
                ->get();
            $existing = $receptionRole->permissions()->pluck('id')->all();
            $receptionRole->syncPermissions(array_unique(array_merge($existing, $receptionInpatientPerms->pluck('id')->all())));
        }

        // ===== HR module (staff_leaves + staff_attendances) =====
        // Self-service: every authenticated user gets view + create on their
        // own row. The resource enforces the scoping (where user_id = $self).
        // HR managers (clinic_admin / branch_manager) get the manage variants.
        $hrSelfPerms = Permission::where('guard_name', 'web')
            ->whereIn('name', [
                'view_any_staff_leaves', 'view_staff_leaves', 'create_staff_leaves', 'update_staff_leaves', 'delete_staff_leaves',
                'view_any_staff_attendances', 'view_staff_attendances', 'create_staff_attendances', 'update_staff_attendances',
            ])
            ->get();
        foreach (['clinic_reception', 'clinic_doctor', 'clinic_manager', 'branch_staff', 'branch_manager', 'clinic_admin', 'accountant'] as $roleName) {
            $r = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($r) {
                $existing = $r->permissions()->pluck('id')->all();
                $r->syncPermissions(array_unique(array_merge($existing, $hrSelfPerms->pluck('id')->all())));
            }
        }

        // HR managers also get delete + force-delete + restore for everyone.
        $hrManagerExtra = Permission::where('guard_name', 'web')
            ->whereIn('name', [
                'delete_any_staff_leaves', 'force_delete_staff_leaves', 'restore_staff_leaves',
                'delete_staff_attendances', 'delete_any_staff_attendances', 'force_delete_staff_attendances', 'restore_staff_attendances',
            ])
            ->get();
        foreach (['branch_manager', 'clinic_admin'] as $roleName) {
            $r = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($r) {
                $existing = $r->permissions()->pluck('id')->all();
                $r->syncPermissions(array_unique(array_merge($existing, $hrManagerExtra->pluck('id')->all())));
            }
        }

        // ===== Lab module =====
        // Catalog (lab_tests) is admin-managed only. Orders + items can be
        // created/updated by doctors (ordering) and reception (recording
        // results, marking sample collected). View access is wide.
        $labViewPerms = Permission::where('guard_name', 'web')
            ->whereIn('name', [
                'view_any_lab_tests', 'view_lab_tests',
                'view_any_lab_orders', 'view_lab_orders',
                'view_any_lab_order_items', 'view_lab_order_items',
            ])->get();
        foreach (['clinic_reception', 'clinic_doctor', 'clinic_manager', 'clinic_admin', 'branch_manager'] as $roleName) {
            $r = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($r) {
                $existing = $r->permissions()->pluck('id')->all();
                $r->syncPermissions(array_unique(array_merge($existing, $labViewPerms->pluck('id')->all())));
            }
        }

        $labOpsPerms = Permission::where('guard_name', 'web')
            ->whereIn('name', [
                'create_lab_orders', 'update_lab_orders', 'delete_lab_orders',
                'create_lab_order_items', 'update_lab_order_items', 'delete_lab_order_items',
            ])->get();
        foreach (['clinic_reception', 'clinic_doctor', 'clinic_admin', 'branch_manager'] as $roleName) {
            $r = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($r) {
                $existing = $r->permissions()->pluck('id')->all();
                $r->syncPermissions(array_unique(array_merge($existing, $labOpsPerms->pluck('id')->all())));
            }
        }

        // lab_tests catalog management = admin only (already covered by admin
        // syncPermissions to ALL, but we also explicitly give clinic_admin).
        $labCatalogPerms = Permission::where('guard_name', 'web')
            ->whereIn('name', [
                'create_lab_tests', 'update_lab_tests', 'delete_lab_tests',
                'delete_any_lab_tests', 'force_delete_lab_tests', 'restore_lab_tests',
            ])->get();
        $r = Role::where('name', 'clinic_admin')->where('guard_name', 'web')->first();
        if ($r) {
            $existing = $r->permissions()->pluck('id')->all();
            $r->syncPermissions(array_unique(array_merge($existing, $labCatalogPerms->pluck('id')->all())));
        }

        // ===== Insurance Resources permissions =====
        // The Spatie-style insurance perms (insurance_view, insurance_*)
        // are already assigned above. Here we hand out the auto-generated
        // resource perms (view_any_insurance_claims, etc.) so the Filament
        // resource pages themselves are reachable.
        $insuranceResourcePerms = Permission::where('guard_name', 'web')
            ->where(function ($q) {
                $q->where('name', 'like', '%_insurers')
                    ->orWhere('name', 'like', '%_insurance_plans')
                    ->orWhere('name', 'like', '%_patient_insurance_policies')
                    ->orWhere('name', 'like', '%_insurance_preauthorizations')
                    ->orWhere('name', 'like', '%_insurance_claims');
            })->get();
        $r = Role::where('name', 'clinic_admin')->where('guard_name', 'web')->first();
        if ($r) {
            $existing = $r->permissions()->pluck('id')->all();
            $r->syncPermissions(array_unique(array_merge($existing, $insuranceResourcePerms->pluck('id')->all())));
        }
        // Reception can view policies + preauth + claims (the management
        // verbs are gated by the insurance_* perms above).
        $r = Role::where('name', 'clinic_reception')->where('guard_name', 'web')->first();
        if ($r) {
            $receptionInsResourcePerms = Permission::where('guard_name', 'web')
                ->whereIn('name', [
                    'view_any_patient_insurance_policies', 'view_patient_insurance_policies',
                    'create_patient_insurance_policies', 'update_patient_insurance_policies',
                    'view_any_insurance_preauthorizations', 'view_insurance_preauthorizations',
                    'create_insurance_preauthorizations', 'update_insurance_preauthorizations',
                    'view_any_insurance_claims', 'view_insurance_claims',
                ])->get();
            $existing = $r->permissions()->pluck('id')->all();
            $r->syncPermissions(array_unique(array_merge($existing, $receptionInsResourcePerms->pluck('id')->all())));
        }

        // ===== Activity log =====
        // Audit log = admin/super_admin only. Don't grant to clinic_admin
        // or others — staff shouldn't see who edited what across the org.
        $activityPerms = Permission::where('guard_name', 'web')
            ->whereIn('name', ['view_any_activity_log', 'view_activity_log'])->get();
        // admin already has ALL perms; nothing else to do.

        // =====================================================================
        // v2 sidebar access alignment: grant the permissions each role's job
        // implies it should reach, so the v2 UI never shows a link that 403s and
        // the Patients / Visit-console pages can be permission-gated safely.
        // =====================================================================
        $grant = function (array $roleNames, array $permNames) {
            $perms = Permission::where('guard_name', 'web')->whereIn('name', $permNames)->get();
            foreach ($roleNames as $rn) {
                $role = Role::where('name', $rn)->where('guard_name', 'web')->first();
                if (! $role) {
                    continue;
                }
                $existing = $role->permissions()->pluck('id')->all();
                $role->syncPermissions(array_unique(array_merge($existing, $perms->pluck('id')->all())));
            }
        };

        // Clinic / branch overseers: clinic reports + follow-up plans.
        $grant(['clinic_admin', 'branch_manager'], [
            'view_clinic_reports', 'view_clinic_closing_reports', 'view_executive-dashboard',
            'view_any_follow_up_plans',
        ]);

        // Reception looks up insurers + plans (already manages policies/preauth/claims).
        $grant(['clinic_reception'], ['view_any_insurers', 'view_any_insurance_plans']);

        // Patient directory: every clinical / management role needs it. The
        // PatientsController gates index + profile + quickView on view_any_patients,
        // and create/update of patient records on create_/update_patients.
        $grant(['clinic_doctor', 'clinic_reception', 'clinic_manager', 'clinic_admin', 'branch_manager'], [
            'view_any_patients', 'create_patients', 'update_patients',
        ]);

        // Visit oversight for clinic/branch managers (the visit console gates on
        // this; doctors + reception already hold it).
        $grant(['clinic_admin', 'branch_manager'], ['view_any_visits']);

        // Re-fetch admin's full perm set so all the new perms above are included.
        $admin->syncPermissions(Permission::where('guard_name', 'web')->get());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function perm(string $name): void
    {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
}
