<?php

namespace Tests\Feature\Drivers;

use App\Domain\Drivers\Actions\CreateDriverAction;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Same regression class as UserListPaginationTest — the Drivers list had
 * the identical per_page=1000-fetched-once-and-paged-client-side bug,
 * PLUS two bugs specific to this page found while fixing it:
 *  - The 4 stat cards were computed client-side from the loaded batch
 *    (GET /api/v1/drivers/stats didn't exist) — silently wrong past
 *    whatever page happened to be loaded.
 *  - The Affiliation filter dropdown existed in the UI but
 *    DriverController::index() never actually read an `affiliation`
 *    query param — it "worked" only because client-side filtering was
 *    still doing the real work on the loaded batch.
 */
class DriverListPaginationAndStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $systemAdmin;

    private int $vehicleCategoryId;

    private int $vehicleId;

    private int $phoneSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $this->systemAdmin = User::factory()->create(['email' => 'admin@gcm.test']);
        $this->systemAdmin->assignRole('system_admin');

        $category = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();
        $this->vehicleCategoryId = $category->id;
        $this->vehicleId = Vehicle::factory()->create(['vehicle_category_id' => $category->id])->id;

        $this->createDrivers(12, 'active');
        $this->createDrivers(3, 'on_vacation');
        $this->createDrivers(2, 'deactivated');
    }

    private function createDrivers(int $count, string $status): void
    {
        for ($i = 0; $i < $count; $i++) {
            app(CreateDriverAction::class)->execute(
                [
                    'name' => "Driver {$status} {$i}",
                    'email' => uniqid("driver.{$status}.{$i}.", true).'@gcm.test',
                    'phone' => '010'.str_pad((string) $this->phoneSequence++, 8, '0', STR_PAD_LEFT),
                    'password' => 'a-secure-password',
                    'status' => $status,
                    'vehicle_category_ids' => [$this->vehicleCategoryId],
                    'default_vehicle_id' => $this->vehicleId,
                    'residence_number' => 'RES', 'residence_valid_to' => '2030-01-01',
                    'license_number' => 'LIC', 'license_valid_to' => '2030-01-01',
                    'operational_license_number' => 'OPL', 'operational_license_valid_to' => '2030-01-01',
                    'insurance_number' => 'INS', 'insurance_valid_to' => '2030-01-01',
                ],
                null,
                [],
                []
            );
        }
    }

    public function test_a_small_per_page_still_reports_the_true_total(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->getJson('/api/v1/drivers?per_page=5&page=1');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertSame(17, $response->json('meta.total'));
    }

    public function test_stats_endpoint_counts_the_full_set_not_a_page_of_it(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')->getJson('/api/v1/drivers/stats');

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'available' => 12,
                'on_trips' => 0,
                'on_vacation' => 3,
                'deactivated' => 2,
            ],
        ]);
    }

    public function test_affiliation_filter_is_actually_applied_server_side(): void
    {
        // Every seeded driver here is 'gcm' (CreateDriverAction always
        // forces that — see its docblock). Filtering by 'contractor'
        // must return nothing; before the fix this param was silently
        // ignored server-side and would have returned everyone.
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->getJson('/api/v1/drivers?affiliation=contractor');

        $response->assertOk();
        $this->assertSame(0, $response->json('meta.total'));
    }

    /**
     * Same fix/reasoning as UserListPaginationTest's analogous test —
     * `code` lives on `users` here but is the list's own first column.
     */
    public function test_searching_by_the_displayed_code_finds_the_driver(): void
    {
        $driverUser = User::where('email', 'like', 'driver.active.0.%')->firstOrFail();

        $response = $this->actingAs($this->systemAdmin, 'web')
            ->getJson('/api/v1/drivers?search='.urlencode($driverUser->code));

        $response->assertOk();
        $this->assertSame([$driverUser->id], $response->json('data.*.user_id'));
    }

    public function test_sort_by_name_desc_actually_reverses_the_order(): void
    {
        $asc = $this->actingAs($this->systemAdmin, 'web')
            ->getJson('/api/v1/drivers?per_page=50&sort_by=name&sort_dir=asc')
            ->json('data.*.name');
        $desc = $this->actingAs($this->systemAdmin, 'web')
            ->getJson('/api/v1/drivers?per_page=50&sort_by=name&sort_dir=desc')
            ->json('data.*.name');

        $this->assertSame($asc, array_reverse($desc));
    }
}
