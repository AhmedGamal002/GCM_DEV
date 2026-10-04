<?php

namespace Tests\Feature\Contractors;

use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\Contractor;
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
 * FRD V01.14 §1.3 / §1.5 / §1.7 — a driver, vehicle or asset belongs to GCM
 * or to a contractor; choosing a contractor shows the list of contractors.
 */
class ContractorAffiliationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Contractor $contractor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->admin = User::factory()->create(['email' => 'admin@gcm.test']);
        $this->admin->assignRole('system_admin');

        $this->contractor = Contractor::factory()->create(['name' => 'Desert Haulage', 'prefix' => 'DSH']);

        AssetCapacityCategory::factory()->container()->create(['name' => 'Container band']);
    }

    private function vehiclePayload(array $overrides = []): array
    {
        return array_merge([
            'plate_letters' => 'ABC',
            'plate_numbers' => '1234',
            'vehicle_category_id' => VehicleCategory::where('slug', 'hook_lift')->value('id'),
            'has_embedded_container' => '0',
            'operational_status' => 'active',
            'affiliation' => 'gcm',
            'documents' => [
                'registration_card' => ['number' => '1001', 'valid_to' => '2027-01-01'],
                'fitness_document' => ['number' => '1002', 'valid_to' => '2027-01-01'],
                'inspection_certificate' => ['number' => '1003', 'valid_to' => '2027-01-01'],
                'insurance' => ['number' => '1004', 'valid_to' => '2027-01-01'],
            ],
        ], $overrides);
    }

    private function assetPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Container A-01',
            'asset_type' => 'container',
            'asset_capacity_category_id' => AssetCapacityCategory::where('applies_to', 'container')->value('id'),
            'compatible_vehicle_category_ids' => [VehicleCategory::where('slug', 'hook_lift')->value('id')],
            'operational_status' => 'active',
            'affiliation' => 'gcm',
        ], $overrides);
    }

    private function driverPayload(array $overrides = []): array
    {
        $category = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();

        return array_merge([
            'name' => 'New Driver',
            'email' => 'driver@gcm.test',
            'phone' => '01000000000',
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
            'status' => 'active',
            'affiliation' => 'gcm',
            'vehicle_category_ids' => [$category->id],
            'residence_number' => 'RES-001',
            'residence_valid_to' => '2030-01-01',
            'license_number' => 'LIC-001',
            'license_valid_to' => '2030-01-01',
            'operational_license_number' => 'OP-001',
            'operational_license_valid_to' => '2030-01-01',
            'insurance_number' => 'INS-001',
            'insurance_valid_to' => '2030-01-01',
        ], $overrides);
    }

    // ------------------------------------------------------------------ vehicles

    public function test_a_vehicle_can_be_created_for_a_contractor(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->vehiclePayload(['affiliation' => 'contractor', 'contractor_id' => (string) $this->contractor->id]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.affiliation', 'contractor')
            ->assertJsonPath('data.entity_name', 'Desert Haulage')
            ->assertJsonPath('data.contractor.id', $this->contractor->id);

        $vehicle = Vehicle::firstOrFail();
        $this->assertSame('contractor', $vehicle->affiliation);
        $this->assertSame($this->contractor->id, $vehicle->contractor_id);
    }

    public function test_a_gcm_vehicle_never_keeps_a_contractor_id(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->vehiclePayload(['affiliation' => 'gcm', 'contractor_id' => (string) $this->contractor->id]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.entity_name', 'GCM')
            ->assertJsonPath('data.contractor', null);

        $this->assertNull(Vehicle::firstOrFail()->contractor_id);
    }

    public function test_choosing_contractor_requires_picking_one(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->vehiclePayload(['affiliation' => 'contractor']), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contractor_id']);
    }

    public function test_an_unknown_other_tenant_or_deactivated_contractor_is_refused_on_create(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $foreign = Contractor::factory()->create();
        app()->instance('tenant', $this->tenant);

        $deactivated = Contractor::factory()->deactivated()->create();

        foreach ([999999, $foreign->id, $deactivated->id] as $id) {
            $this->actingAs($this->admin, 'web')
                ->post('/api/v1/vehicles', $this->vehiclePayload(['affiliation' => 'contractor', 'contractor_id' => (string) $id]), ['Accept' => 'application/json'])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['contractor_id']);
        }
    }

    public function test_the_affiliation_must_be_gcm_or_contractor(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->vehiclePayload(['affiliation' => 'client']), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['affiliation']);
    }

    public function test_a_vehicle_can_move_between_gcm_and_a_contractor_on_edit(): void
    {
        $id = $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->vehiclePayload(), ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->admin, 'web')
            ->post("/api/v1/vehicles/{$id}", $this->vehiclePayload(['_method' => 'PATCH', 'affiliation' => 'contractor', 'contractor_id' => (string) $this->contractor->id]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.entity_name', 'Desert Haulage');

        $this->assertSame($this->contractor->id, Vehicle::findOrFail($id)->contractor_id);

        $this->actingAs($this->admin, 'web')
            ->post("/api/v1/vehicles/{$id}", $this->vehiclePayload(['_method' => 'PATCH', 'affiliation' => 'gcm']), ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertNull(Vehicle::findOrFail($id)->contractor_id);
    }

    public function test_a_vehicle_whose_contractor_was_deactivated_can_still_be_edited(): void
    {
        $vehicle = Vehicle::factory()->create(['affiliation' => 'contractor', 'contractor_id' => $this->contractor->id]);
        $this->contractor->forceFill(['operational_status' => 'deactivated'])->save();

        $payload = $this->vehiclePayload(['_method' => 'PATCH', 'affiliation' => 'contractor', 'contractor_id' => (string) $this->contractor->id]);

        $this->actingAs($this->admin, 'web')
            ->post("/api/v1/vehicles/{$vehicle->id}", $payload, ['Accept' => 'application/json'])
            ->assertOk();
    }

    public function test_the_vehicle_list_shows_and_filters_by_contractor(): void
    {
        Vehicle::factory()->create();
        Vehicle::factory()->create(['affiliation' => 'contractor', 'contractor_id' => $this->contractor->id]);

        $response = $this->actingAs($this->admin, 'web')->getJson('/api/v1/vehicles?affiliation=contractor');
        $response->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.entity_name', 'Desert Haulage');

        $this->actingAs($this->admin, 'web')->getJson("/api/v1/vehicles?contractor_id={$this->contractor->id}")
            ->assertJsonPath('meta.total', 1);

        $other = Contractor::factory()->create();
        $this->actingAs($this->admin, 'web')->getJson("/api/v1/vehicles?contractor_id={$other->id}")
            ->assertJsonPath('meta.total', 0);
    }

    // ------------------------------------------------------------------ assets

    public function test_an_asset_can_be_created_for_a_contractor_and_the_choice_is_locked(): void
    {
        $id = $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/assets', $this->assetPayload(['affiliation' => 'contractor', 'contractor_id' => $this->contractor->id]))
            ->assertCreated()
            ->assertJsonPath('data.entity_name', 'Desert Haulage')
            ->json('data.id');

        // FRD §1.7.4: only the name is editable — the affiliation never moves
        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/assets/{$id}", ['name' => 'Renamed', 'affiliation' => 'gcm', 'contractor_id' => null])
            ->assertOk();

        $asset = Asset::findOrFail($id);
        $this->assertSame('Renamed', $asset->name);
        $this->assertSame('contractor', $asset->affiliation);
        $this->assertSame($this->contractor->id, $asset->contractor_id);
    }

    public function test_an_asset_for_a_contractor_requires_picking_one(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/assets', $this->assetPayload(['affiliation' => 'contractor']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contractor_id']);
    }

    public function test_the_asset_list_filters_by_contractor(): void
    {
        Asset::factory()->create();
        Asset::factory()->create(['affiliation' => 'contractor', 'contractor_id' => $this->contractor->id]);

        $this->actingAs($this->admin, 'web')->getJson("/api/v1/assets?contractor_id={$this->contractor->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.entity_name', 'Desert Haulage');
    }

    // ------------------------------------------------------------------ drivers

    public function test_a_driver_can_be_created_for_a_contractor(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/drivers', $this->driverPayload(['affiliation' => 'contractor', 'contractor_id' => (string) $this->contractor->id]))
            ->assertCreated()
            ->assertJsonPath('data.affiliation', 'contractor')
            ->assertJsonPath('data.entity_name', 'Desert Haulage')
            ->assertJsonPath('data.contractor.id', $this->contractor->id);

        $driver = Driver::firstOrFail();
        // the account and the driver profile are kept in step
        $this->assertSame($this->contractor->id, $driver->contractor_id);
        $this->assertSame($this->contractor->id, $driver->user->contractor_id);
        $this->assertSame('contractor', $driver->user->affiliation);
    }

    public function test_a_contractor_driver_requires_a_contractor(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/drivers', $this->driverPayload(['affiliation' => 'contractor']), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contractor_id']);
    }

    public function test_a_driver_can_move_to_another_affiliation_on_edit(): void
    {
        $id = $this->actingAs($this->admin, 'web')
            ->post('/api/v1/drivers', $this->driverPayload())
            ->assertCreated()
            ->json('data.id');

        $update = $this->driverPayload(['_method' => 'PATCH', 'affiliation' => 'contractor', 'contractor_id' => (string) $this->contractor->id]);
        unset($update['email'], $update['password'], $update['password_confirmation']);

        $this->actingAs($this->admin, 'web')
            ->post("/api/v1/drivers/{$id}", $update)
            ->assertOk()
            ->assertJsonPath('data.entity_name', 'Desert Haulage');

        $driver = Driver::findOrFail($id);
        $this->assertSame($this->contractor->id, $driver->contractor_id);
        $this->assertSame($this->contractor->id, $driver->user->fresh()->contractor_id);
    }

    public function test_the_driver_list_shows_the_contractor_and_the_users_list_names_the_entity(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/drivers', $this->driverPayload(['affiliation' => 'contractor', 'contractor_id' => (string) $this->contractor->id]))
            ->assertCreated();

        $this->actingAs($this->admin, 'web')->getJson("/api/v1/drivers?contractor_id={$this->contractor->id}")
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.entity_name', 'Desert Haulage');

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/users?affiliation=contractor')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.entity_name', 'Desert Haulage');

        // and the contractor's own counters follow
        $this->actingAs($this->admin, 'web')->getJson("/api/v1/contractors/{$this->contractor->id}")
            ->assertJsonPath('data.drivers_count', 1);
    }
}
