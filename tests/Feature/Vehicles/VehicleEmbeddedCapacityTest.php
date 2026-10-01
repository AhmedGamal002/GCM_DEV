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
 * asset capacities that have been created, narrowed to the chosen kind:
 * a container takes the capacities that apply to containers, a tank those
 * that apply to tanks, and a capacity that applies to both fits either.
 * It does not depend on the vehicle category or on which assets exist.
 */
class VehicleEmbeddedCapacityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private int $hookLiftId;

    private AssetCapacityCategory $containerCap;

    private AssetCapacityCategory $tankCap;

    private AssetCapacityCategory $bothCap;

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

        // No assets at all — the list must not depend on the asset pool.
        $this->containerCap = AssetCapacityCategory::factory()->container()->create(['name' => 'A skip']);
        $this->tankCap = AssetCapacityCategory::factory()->tank()->create(['name' => 'B tank']);
        $this->bothCap = AssetCapacityCategory::factory()->create(['name' => 'C vacuum', 'applies_to' => 'both']);
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

    /** What the vehicle form's dropdown asks for: GET /asset-capacity-categories?for_type=… */
    public function test_the_dropdown_lists_the_capacities_of_the_chosen_type_plus_those_for_both(): void
    {
        $this->actingAs($this->admin, 'web');

        $forContainer = $this->getJson('/api/v1/asset-capacity-categories?for_type=container')->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([$this->containerCap->id, $this->bothCap->id], $forContainer);

        $forTank = $this->getJson('/api/v1/asset-capacity-categories?for_type=tank')->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([$this->tankCap->id, $this->bothCap->id], $forTank);
    }

    public function test_the_list_does_not_depend_on_the_asset_pool_or_the_vehicle_category(): void
    {
        // An asset compatible with a DIFFERENT vehicle category changes nothing…
        $waterTankerId = VehicleCategory::where('slug', 'water_tanker')->value('id');
        Asset::factory()->create(['asset_type' => 'tank', 'asset_capacity_category_id' => $this->tankCap->id])
            ->compatibleVehicleCategories()->attach($waterTankerId);

        // …a hook_lift vehicle can still carry the tank capacity as an embedded tank,
        // and a capacity no asset uses is fine too.
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->vehiclePayload([
                'embedded_container_type' => 'tank',
                'embedded_asset_capacity_category_id' => $this->tankCap->id,
            ]), ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame($this->tankCap->id, Vehicle::firstOrFail()->embedded_asset_capacity_category_id);
    }

    public function test_a_container_takes_a_container_capacity(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->vehiclePayload([
                'embedded_asset_capacity_category_id' => $this->containerCap->id,
            ]), ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame($this->containerCap->id, Vehicle::firstOrFail()->embedded_asset_capacity_category_id);
    }

    public function test_a_capacity_for_both_fits_a_container_and_a_tank(): void
    {
        $this->actingAs($this->admin, 'web');

        $this->post('/api/v1/vehicles', $this->vehiclePayload([
            'embedded_asset_capacity_category_id' => $this->bothCap->id,
        ]), ['Accept' => 'application/json'])->assertCreated();

        $this->post('/api/v1/vehicles', $this->vehiclePayload([
            'plate_numbers' => '5678',
            'embedded_container_type' => 'tank',
            'embedded_asset_capacity_category_id' => $this->bothCap->id,
        ]), ['Accept' => 'application/json'])->assertCreated();
    }

    public function test_a_tank_capacity_is_rejected_for_a_container(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->vehiclePayload([
                'embedded_container_type' => 'container',
                'embedded_asset_capacity_category_id' => $this->tankCap->id,
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['embedded_asset_capacity_category_id']);
    }

    public function test_a_container_capacity_is_rejected_for_a_tank(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->vehiclePayload([
                'embedded_container_type' => 'tank',
                'embedded_asset_capacity_category_id' => $this->containerCap->id,
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['embedded_asset_capacity_category_id']);
    }

    public function test_the_same_check_applies_on_edit(): void
    {
        $this->actingAs($this->admin, 'web');

        $id = $this->post('/api/v1/vehicles', $this->vehiclePayload([
            'embedded_asset_capacity_category_id' => $this->containerCap->id,
        ]), ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        // Switching the kind to tank while keeping a container-only capacity is refused…
        $this->patch("/api/v1/vehicles/{$id}", $this->vehiclePayload([
            'embedded_container_type' => 'tank',
            'embedded_asset_capacity_category_id' => $this->containerCap->id,
        ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['embedded_asset_capacity_category_id']);

        // …and accepted with a tank capacity.
        $this->patch("/api/v1/vehicles/{$id}", $this->vehiclePayload([
            'embedded_container_type' => 'tank',
            'embedded_asset_capacity_category_id' => $this->tankCap->id,
        ]), ['Accept' => 'application/json'])->assertOk();
    }

    /** "Has an embedded container" makes the type (container / tank) mandatory — on create and on edit. */
    public function test_the_type_is_required_once_the_vehicle_has_an_embedded_container(): void
    {
        $this->actingAs($this->admin, 'web');

        // Create: a capacity alone is not enough — the type must be chosen.
        $this->post('/api/v1/vehicles', $this->vehiclePayload([
            'embedded_container_type' => null,
            'embedded_asset_capacity_category_id' => $this->bothCap->id,
        ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['embedded_container_type']);

        $this->assertSame(0, Vehicle::count());

        // Edit: switching a plain vehicle to "has an embedded container" without a type.
        $id = $this->post('/api/v1/vehicles', $this->vehiclePayload([
            'has_embedded_container' => '0',
            'embedded_container_type' => null,
            'embedded_asset_capacity_category_id' => null,
        ]), ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $this->patch("/api/v1/vehicles/{$id}", $this->vehiclePayload([
            'embedded_container_type' => null,
            'embedded_asset_capacity_category_id' => $this->bothCap->id,
        ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['embedded_container_type']);

        $this->assertFalse(Vehicle::findOrFail($id)->has_embedded_container);
    }

    public function test_another_tenants_capacity_is_rejected(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $foreignCap = AssetCapacityCategory::factory()->container()->create(['name' => 'Foreign']);
        app()->instance('tenant', $this->tenant);

        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->vehiclePayload([
                'embedded_asset_capacity_category_id' => $foreignCap->id,
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['embedded_asset_capacity_category_id']);
    }

    public function test_non_embedded_vehicle_skips_the_check_entirely(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->vehiclePayload([
                'has_embedded_container' => '0',
                'embedded_container_type' => null,
                'embedded_asset_capacity_category_id' => null,
            ]), ['Accept' => 'application/json'])
            ->assertCreated();
    }
}
