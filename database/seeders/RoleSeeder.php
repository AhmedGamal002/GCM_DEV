<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The four GCM-side roles plus the two client-company roles (FRD V01.14
 * §1.4: Project Manager / Project Auditor). The contractor-user role is
 * added with the Contractor module.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['system_admin', 'data_entry', 'auditor', 'driver', 'client_project_manager', 'client_project_auditor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
