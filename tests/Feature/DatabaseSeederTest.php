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

    public function test_seeded_client_accounts_belong_to_a_company_with_the_right_project_access(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('email', 'client.manager@gcm.test')->firstOrFail();
        $auditor = User::where('email', 'client.auditor@gcm.test')->firstOrFail();

        $this->assertTrue($manager->hasRole('client_project_manager'));
        $this->assertTrue($manager->all_projects);
        $this->assertSame('ALN', $manager->company->prefix);
        $this->assertSame($manager->id, $manager->company->representative_id);

        $this->assertTrue($auditor->hasRole('client_project_auditor'));
        $this->assertSame(['Al Noor Tower'], $auditor->projects()->pluck('name')->all());
        $this->assertSame($auditor->id, $auditor->projects()->first()->representative_id);
    }
}
