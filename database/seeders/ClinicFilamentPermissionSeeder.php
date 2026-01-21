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
        Role::firstOrCreate(['name' => 'clinic_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'clinic_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'clinic_doctor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'clinic_reception', 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function perm(string $name): void
    {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
}
