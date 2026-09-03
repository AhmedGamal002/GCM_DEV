<?php

namespace Tests\Feature\Vehicles;

use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FRD §1.5.3 — a vehicle's embedded container capacity is picked from the
 * capacities the fleet can actually field for that (vehicle category +
 * container/tank kind), derived through the asset pool. No matching
 * stock => the capacity is rejected, not silently allowed.
 */
class VehicleEmbeddedCapacityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private int $hookLiftId;

    private int $waterTankerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->admin = User::factory()->create(['email' => 'admin@gcm.test']);
        $this->admin->assignRole('system_admin');

        $this->hookLiftId = VehicleCategory::where('slug', 'hook_lift')->value('id');
        $this->waterTankerId = VehicleCategory::where('slug', 'water_tanker')->value('id');
    }

    /**
     * @return array{0: AssetCapacityCategory, 1: AssetCapacityCategory}
     */
    private function seedPool(): array
    {
        // A container capacity fieldable by hook_lift.
        $containerCap = AssetCapacityCategory::factory()->container()->create(['name' => 'Roll-on/off']);
        Asset::factory()->create(['asset_type' => 'container', 'asset_capacity_category_id' => $containerCap->id])
            ->compatibleVehicleCategories()->attach($this->hookLiftId);

        // A tank capacity fieldable by water_tanker only.
        $tankCap = AssetCapacityCategory::factory()->tank()->create(['name' => 'Water tank']);
        Asset::factory()->create(['asset_type' => 'tank', 'asset_capacity_category_id' => $tankCap->id])
            ->compatibleVehicleCategories()->attach($this->waterTankerId);

        return [$containerCap, $tankCap];
    }

    private function vehiclePayload(array $overrides): array
    {
        return array_merge([
            'plate_letters' => 'ABC',
            'plate_numbers' => '1234',
            'vehicle_category_id' => $this->hookLiftId,
            'has_embedded_container' => '1',
            'embedded_container_type' => 'container',
            'operational_status' => 'active',
            'affiliation' => 'gcm',
            'documents' => [
                'registration_card' => ['number' => '11', 'valid_to' => '2027-01-01'],
                'fitness_document' => ['number' => '12', 'valid_to' => '2027-01-01'],
                'inspection_certificate' => ['number' => '13', 'valid_to' => '2027-01-01'],
                'insurance' => ['number' => '14', 'valid_to' => '2027-01-01'],
            ],
        ], $overrides);
    }

    public function test_endpoint_returns_only_capacities_fieldable_for_the_category_and_kind(): void
    {
        [$containerCap, $tankCap] = $this->seedPool();

        $forHookLiftContainers = $this->actingAs($this->admin, 'web')
            ->getJson("/api/v1/asset-capacity-categories?vehicle_category_id={$this->hookLiftId}&asset_type=container")
            ->assertOk()->json('data.*.id');
        $this->assertSame([$containerCap->id], $forHookLiftContainers);

        // Same vehicle category, tank kind => nothing (no hook_lift tank asset).
        $forHookLiftTanks = $this->actingAs($this->admin, 'web')
            ->getJson("/api/v1/asset-capacity-categories?vehicle_category_id={$this->hookLiftId}&asset_type=tank")
            ->assertOk()->json('data');
        $this->assertSame([], $forHookLiftTanks);

        // Different vehicle category => the tank capacity shows up.
        $forWaterTankerTanks = $this->actingAs($this->admin, 'web')
            ->getJson("/api/v1/asset-capacity-categories?vehicle_category_id={$this->waterTankerId}&asset_type=tank")
            ->assertOk()->json('data.*.id');
        $this->assertSame([$tankCap->id], $forWaterTankerTanks);
    }

    public function test_creating_a_vehicle_with_a_compatible_embedded_capacity_succeeds(): void
    {
        [$containerCap] = $this->seedPool();

        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->vehiclePayload([
                'embedded_asset_capacity_category_id' => $containerCap->id,
            ]), ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame($containerCap->id, Vehicle::firstOrFail()->embedded_asset_capacity_category_id);
    }

    public function test_capacity_not_fieldable_for_the_vehicle_category_is_rejected(): void
    {
        [, $tankCap] = $this->seedPool();

        // hook_lift vehicle asking for the water-tanker-only tank capacity.
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->vehiclePayload([
                'embedded_container_type' => 'tank',
                'embedded_asset_capacity_category_id' => $tankCap->id,
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['embedded_asset_capacity_category_id']);
    }

    public function test_capacity_with_no_recorded_asset_is_rejected(): void
    {
        // Capacity exists but nothing in the pool references it.
        $lonelyCap = AssetCapacityCategory::factory()->container()->create(['name' => 'Unused band']);

        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->vehiclePayload([
                'embedded_asset_capacity_category_id' => $lonelyCap->id,
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['embedded_asset_capacity_category_id']);
    }

    public function test_kind_mismatch_is_rejected_even_with_a_compatible_asset(): void
    {
        [$containerCap] = $this->seedPool();

        // The container capacity is fieldable for hook_lift as a *container*,
        // but the vehicle form says tank here.
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->vehiclePayload([
                'embedded_container_type' => 'tank',
                'embedded_asset_capacity_category_id' => $containerCap->id,
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['embedded_asset_capacity_category_id']);
    }

    public function test_non_embedded_vehicle_skips_the_check_entirely(): void
    {
        $this->seedPool();

        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->vehiclePayload([
                'has_embedded_container' => '0',
                'embedded_container_type' => null,
                'embedded_asset_capacity_category_id' => null,
            ]), ['Accept' => 'application/json'])
            ->assertCreated();
    }
}
