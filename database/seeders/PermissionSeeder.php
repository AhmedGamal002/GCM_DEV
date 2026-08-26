<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Minimal set matching what exists today (Users management only —
 * Fleet/Drivers/etc. land in later weeks and will extend this without
 * restructuring). {resource}.{action} dot-notation, matching Spatie's
 * own convention.
 *
 * Not yet wired to any Gate check this week — UserPolicy gates
 * tenant-side abilities by role (hasRole('system_admin')), not by
 * permission. These rows exist so the Platform "assign permissions to a
 * role" picker has real data to render; permission-based authorization
 * is a future refinement.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'users.view_any',
            'users.view',
            'users.create',
            'users.update',
            'users.update_status',
            'users.export',
            'roles.manage',
            'permissions.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}
