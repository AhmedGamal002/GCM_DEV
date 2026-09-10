<?php

namespace Tests\Feature\Drivers;

use App\Models\Driver;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Focused coverage for the "one driver per default vehicle" rule on the
 * update path (DriverCreationTest covers the same rule on create) — this
 * project previously had no dedicated update Feature test at all.
 */
class DriverUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $systemAdmin;

    private VehicleCategory $vehicleCategory;

    private Vehicle $vehicleA;

    private Vehicle $vehicleB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);

        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $this->systemAdmin = User::factory()->create();
        $this->systemAdmin->assignRole('system_admin');

        $this->vehicleCategory = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();
        $this->vehicleA = Vehicle::factory()->create(['vehicle_category_id' => $this->vehicleCategory->id]);
        $this->vehicleB = Vehicle::factory()->create(['vehicle_category_id' => $this->vehicleCategory->id]);
    }

    private function makeDriver(int $defaultVehicleId): Driver
    {
        $user = User::factory()->create();
        $user->assignRole('driver');

        return Driver::create([
            'user_id' => $user->id,
            'default_vehicle_id' => $defaultVehicleId,
            'residence_number' => 'RES-000', 'residence_valid_to' => '2030-01-01',
            'license_number' => 'LIC-000', 'license_valid_to' => '2030-01-01',
            'operational_license_number' => 'OPL-000', 'operational_license_valid_to' => '2030-01-01',
            'insurance_number' => 'INS-000', 'insurance_valid_to' => '2030-01-01',
        ]);
    }

    private function updatePayload(int $defaultVehicleId): array
    {
        return [
            'name' => 'Updated Driver',
            'phone' => '01000000000',
            'status' => 'active',
            'vehicle_category_ids' => [$this->vehicleCategory->id],
            'default_vehicle_id' => $defaultVehicleId,
            'residence_number' => 'RES-001',
            'residence_valid_to' => '2030-01-01',
            'license_number' => 'LIC-001',
            'license_valid_to' => '2030-01-01',
            'operational_license_number' => 'OP-001',
            'operational_license_valid_to' => '2030-01-01',
            'insurance_number' => 'INS-001',
            'insurance_valid_to' => '2030-01-01',
        ];
    }

    public function test_re_saving_a_drivers_own_default_vehicle_unchanged_does_not_reject_itself(): void
    {
        $driver = $this->makeDriver($this->vehicleA->id);

        $response = $this->actingAs($this->systemAdmin, 'web')
            ->post("/api/v1/drivers/{$driver->id}", array_merge($this->updatePayload($this->vehicleA->id), ['_method' => 'PATCH']), ['Accept' => 'application/json']);

        $response->assertOk();
    }

    public function test_switching_to_a_vehicle_already_taken_by_another_driver_is_rejected(): void
    {
        $driver = $this->makeDriver($this->vehicleA->id);
        $this->makeDriver($this->vehicleB->id); // another driver already owns vehicleB

        $response = $this->actingAs($this->systemAdmin, 'web')
            ->post("/api/v1/drivers/{$driver->id}", array_merge($this->updatePayload($this->vehicleB->id), ['_method' => 'PATCH']), ['Accept' => 'application/json']);

        $response->assertUnprocessable()->assertJsonValidationErrors('default_vehicle_id');
    }
}
