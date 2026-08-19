<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Dedicated permissions for the module reports.
 *
 * These reports were first gated as "the module permission OR view_clinic_reports",
 * which quietly widened access: a branch manager holds view_clinic_reports but not
 * view_any_payroll_runs, so the fallback let them open the payroll report and read
 * every salary, leave liability and end-of-service figure in the estate. Reception
 * picked up vendor spend and payables the same way.
 *
 * A report is not the same object as the records behind it — seeing a stock list is
 * not the same as seeing what the inventory is worth, and seeing a claim is not the
 * same as seeing an insurer scorecard. Each report therefore gets its own
 * permission, granted deliberately, so the sensitivity is stated rather than
 * inferred from a boolean chain.
 */
class ClinicReportPermissionsSeeder extends Seeder
{
    /** permission => the roles that should hold it. */
    private const GRANTS = [
        // Financial / commercial — management and finance only.
        'view_stock_reports' => ['admin', 'super_admin', 'accountant', 'clinic_admin'],
        'view_purchasing_reports' => ['admin', 'super_admin', 'accountant', 'clinic_admin'],
        'view_insurance_reports' => ['admin', 'super_admin', 'accountant', 'clinic_admin'],
        'view_package_reports' => ['admin', 'super_admin', 'accountant', 'clinic_admin'],
        'view_discount_reports' => ['admin', 'super_admin', 'accountant', 'clinic_admin'],
        'view_patient_reports' => ['admin', 'super_admin', 'accountant', 'clinic_admin'],

        // Salaries, loans and end-of-service. Finance and top-level admin only —
        // deliberately NOT clinic_admin, who manages a branch but has no business
        // reading their colleagues' pay.
        'view_payroll_reports' => ['admin', 'super_admin', 'accountant'],

        // Doctor earnings and commission side by side. A doctor has My Earnings
        // for their own figures and should not see the rest of the panel.
        'view_doctor_reports' => ['admin', 'super_admin', 'accountant', 'clinic_admin'],

        // Attendance and no-shows are the front desk's own scoreboard.
        'view_booking_reports' => ['admin', 'super_admin', 'accountant', 'clinic_admin', 'clinic_reception'],

        // Turnaround and backlog belong to the people running the bench.
        'view_lab_reports' => ['admin', 'super_admin', 'accountant', 'clinic_admin', 'clinic_lab', 'clinic_doctor'],

        // Who voided, deleted, discounted or opened a patient file, across the
        // whole estate. activity_log carries no branch column, so this cannot be
        // scoped to one branch — it is all-or-nothing, which is why a branch
        // manager does not get it.
        'view_audit_reports' => ['admin', 'super_admin', 'accountant'],
    ];

    public function run(): void
    {
        $guard = DB::table('permissions')->value('guard_name') ?: 'web';
        $granted = 0;

        foreach (self::GRANTS as $permName => $roleNames) {
            $permission = Permission::firstOrCreate(['name' => $permName, 'guard_name' => $guard]);

            foreach ($roleNames as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
                if (! $role) {
                    continue; // e.g. super_admin may not exist in this install
                }
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                    $granted++;
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command?->info('ClinicReportPermissionsSeeder: '.count(self::GRANTS)." report permissions ensured, {$granted} role grants added.");
    }
}
