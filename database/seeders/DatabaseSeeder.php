<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            PlatformAdminSeeder::class,
            // Global reference data — no tenant context needed.
            VehicleCategorySeeder::class,
        ]);

        $tenant = Tenant::create([
            'name' => 'GCM',
            'slug' => 'gcm',
            'status' => 'active',
        ]);

        // BelongsToTenant's creating() auto-stamp needs a bound tenant to
        // create rows outside an HTTP request/EnsureTenant context.
        app()->instance('tenant', $tenant);

        // Tenant-scoped reference data — needs the bound tenant above.
        $this->call([
            AssetCapacityCategorySeeder::class,
        ]);

        User::factory()->create(['name' => 'System Admin', 'email' => 'admin@gcm.test'])
            ->assignRole('system_admin');

        User::factory()->create(['name' => 'Data Entry', 'email' => 'dataentry@gcm.test'])
            ->assignRole('data_entry');

        User::factory()->create(['name' => 'Auditor', 'email' => 'auditor@gcm.test'])
            ->assignRole('auditor');

        User::factory()->create(['name' => 'Driver', 'email' => 'driver@gcm.test'])
            ->assignRole('driver');
    }
}
