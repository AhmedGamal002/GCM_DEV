<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * GCM-side roles only for Week 1. Client/contractor roles
 * (client_project_manager, client_project_auditor, contractor_user) are
 * added in Week 4-5 once Company/Contractor exist.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['system_admin', 'data_entry', 'auditor', 'driver'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
