<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The four GCM-side roles, the two client-company roles (FRD V01.14 §1.4:
 * Project Manager / Project Auditor) and the contractor-user role (§1.4:
 * "مستخدم متعهد" — a view/export-only monitor of one contractor's resources).
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['system_admin', 'data_entry', 'auditor', 'driver', 'client_project_manager', 'client_project_auditor', 'contractor_user'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
