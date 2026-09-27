<?php

namespace Tests\Feature\Vehicles;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Same regression class as UserListPaginationTest/
 * DriverListPaginationAndStatsTest — the Vehicles list had the identical
 * per_page=1000-fetched-once bug (its stat cards were already a proper
 * server endpoint, see VehicleManagementTest's stats coverage — only the
 * table itself needed this fix).
 */
class VehicleListPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);
    }

    public function test_a_small_per_page_still_reports_the_true_total(): void
    {
        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $category = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();
        Vehicle::factory()->count(15)->create(['vehicle_category_id' => $category->id]);

        $response = $this->actingAs($admin, 'web')->getJson('/api/v1/vehicles?per_page=5&page=1');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertSame(15, $response->json('meta.total'));
    }

    public function test_sort_by_plate_letters_desc_actually_reverses_the_order(): void
    {
        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $category = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();
        Vehicle::factory()->count(10)->create(['vehicle_category_id' => $category->id]);

        $asc = $this->actingAs($admin, 'web')
            ->getJson('/api/v1/vehicles?per_page=50&sort_by=plate_letters&sort_dir=asc')
            ->json('data.*.plate_letters');
        $desc = $this->actingAs($admin, 'web')
            ->getJson('/api/v1/vehicles?per_page=50&sort_by=plate_letters&sort_dir=desc')
            ->json('data.*.plate_letters');

        $this->assertSame($asc, array_reverse($desc));
    }

    /**
     * Vehicle::plate() displays "AAA 1234" (letters + space + numbers,
     * see VehicleResource) but the columns are stored separately — a plain
     * per-column LIKE never matched a search for the combined displayed
     * string. Regression for that: searching exactly what's on screen
     * must find the row, plus the no-space variant a user might also type.
     */
    public function test_searching_the_displayed_plate_with_a_space_finds_the_vehicle(): void
    {
        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $category = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();
        $target = Vehicle::factory()->create([
            'vehicle_category_id' => $category->id,
            'plate_letters' => 'AAA',
            'plate_numbers' => '0001',
        ]);
        Vehicle::factory()->create([
            'vehicle_category_id' => $category->id,
            'plate_letters' => 'BBB',
            'plate_numbers' => '0002',
        ]);

        foreach (['AAA 0001', 'AAA0001', 'AAA', '0001'] as $search) {
            $response = $this->actingAs($admin, 'web')
                ->getJson('/api/v1/vehicles?search='.urlencode($search));

            $response->assertOk();
            $this->assertSame([$target->id], $response->json('data.*.id'), "search term '{$search}' should match the vehicle");
        }
    }

    /**
     * Backs the driver form's "Default Vehicle" dropdown — a vehicle
     * already claimed as another driver's default must not be offered.
     * `exclude_default_of_driver` re-includes one specific driver's own
     * already-assigned vehicle (the edit page's own case).
     */
    public function test_unassigned_as_default_hides_vehicles_already_taken_by_a_driver(): void
    {
        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $category = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();
        $free = Vehicle::factory()->create(['vehicle_category_id' => $category->id]);
        $taken = Vehicle::factory()->create(['vehicle_category_id' => $category->id]);

        $driverUser = User::factory()->create();
        $driverUser->assignRole('driver');
        $driver = \App\Models\Driver::create([
            'user_id' => $driverUser->id,
            'default_vehicle_id' => $taken->id,
            'residence_number' => 'RES', 'residence_valid_to' => '2030-01-01',
            'license_number' => 'LIC', 'license_valid_to' => '2030-01-01',
            'operational_license_number' => 'OPL', 'operational_license_valid_to' => '2030-01-01',
            'insurance_number' => 'INS', 'insurance_valid_to' => '2030-01-01',
        ]);

        $withoutExclusion = $this->actingAs($admin, 'web')
            ->getJson('/api/v1/vehicles?unassigned_as_default=1')
            ->json('data.*.id');
        $this->assertContains($free->id, $withoutExclusion);
        $this->assertNotContains($taken->id, $withoutExclusion, 'a vehicle already taken as a default must be hidden');

        $keptForOwner = $this->actingAs($admin, 'web')
            ->getJson('/api/v1/vehicles?unassigned_as_default=1&exclude_default_of_driver='.$driver->id)
            ->json('data.*.id');
        $this->assertContains($taken->id, $keptForOwner, "the owning driver's own edit form must still see their own vehicle");
    }
}
