<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression test for a real bug: the seeded demo driver used to be
     * created via User::factory()->create()->assignRole('driver') alone
     * — role but no matching `drivers` row, reproducing exactly the bug
     * a real user hit live (a driver invisible on the Drivers page). See
     * DriverRoleExclusionTest for the application-level fix that now
     * also prevents this from happening outside seeding.
     */
    public function test_seeded_demo_driver_has_a_matching_driver_row(): void
    {
        $this->seed(DatabaseSeeder::class);

        $driverUser = User::where('email', 'driver@gcm.test')->firstOrFail();

        $this->assertTrue($driverUser->hasRole('driver'));
        $this->assertNotNull($driverUser->driver, 'Seeded driver@gcm.test has no matching drivers row.');
    }
}
