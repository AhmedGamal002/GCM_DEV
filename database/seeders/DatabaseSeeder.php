<?php

namespace Database\Seeders;

use App\Models\Driver;
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
        ]);

        $tenant = Tenant::create([
            'name' => 'GCM',
            'slug' => 'gcm',
            'status' => 'active',
        ]);

        // (Tenant::created has just seeded this tenant's five default
        // vehicle categories.)

        // BelongsToTenant's creating() auto-stamp needs a bound tenant to
        // create rows outside an HTTP request/EnsureTenant context.
        app()->instance('tenant', $tenant);

        // Tenant-scoped reference data — needs the bound tenant above.
        $this->call([
            AssetCapacityCategorySeeder::class,
            AssetSeeder::class,
            IntermediateFacilitySeeder::class,
        ]);

        User::factory()->create(['name' => 'System Admin', 'email' => 'admin@gcm.test'])
            ->assignRole('system_admin');

        User::factory()->create(['name' => 'Data Entry', 'email' => 'dataentry@gcm.test'])
            ->assignRole('data_entry');

        User::factory()->create(['name' => 'Auditor', 'email' => 'auditor@gcm.test'])
            ->assignRole('auditor');

        // A driver needs a matching `drivers` row (residence/license/
        // insurance), not just the role — assignRole() alone reproduced
        // the real bug this seeder is now written to avoid: a driver
        // invisible on the Drivers page because no Driver row exists.
        // See StoreUserRequest's docblock / CreateDriverAction.
        $driverUser = User::factory()->create(['name' => 'Driver', 'email' => 'driver@gcm.test']);
        $driverUser->assignRole('driver');
        Driver::create([
            'user_id' => $driverUser->id,
            'residence_number' => 'RES-0001',
            'residence_valid_to' => now()->addYear(),
            'license_number' => 'LIC-0001',
            'license_valid_to' => now()->addYear(),
            'operational_license_number' => 'OPL-0001',
            'operational_license_valid_to' => now()->addYear(),
            'insurance_number' => 'INS-0001',
            'insurance_valid_to' => now()->addYear(),
        ]);
    }
}
