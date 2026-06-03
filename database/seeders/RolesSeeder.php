<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // Canonical base roles. Clinic staff roles (clinic_admin / clinic_doctor /
        // clinic_reception / clinic_nurse / accountant) and their permissions are
        // owned by ClinicRoleStructureSeeder.
        foreach (['admin', 'partner_owner', 'customer'] as $r) {
            Role::firstOrCreate(['name' => $r]);
        }
    }
}
