<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'partner_owner', 'branch_manager', 'branch_staff', 'customer'] as $r) {
            Role::firstOrCreate(['name' => $r]);
        }
    }
}
