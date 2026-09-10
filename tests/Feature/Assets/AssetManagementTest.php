<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehicleCategory;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $dataEntry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->admin = User::factory()->create(['email' => 'admin@gcm.test']);
        $this->admin->assignRole('system_admin');

        $this->dataEntry = User::factory()->create(['email' => 'de@gcm.test']);
        $this->dataEntry->assignRole('data_entry');

        AssetCapacityCategory::factory()->container()->create(['name' => 'Container band']);
        AssetCapacityCategory::factory()->tank()->create(['name' => 'Tank band']);
    }

    private function validPayload(array $overrides = []): array
    {
        $capacityId = AssetCapacityCategory::where('applies_to', 'container')->value('id');
        $hookLift = VehicleCategory::where('slug', 'hook_lift')->value('id');

        return array_merge([
            'name' => 'Container A-01',
            'asset_type' => 'container',
            'asset_capacity_category_id' => $capacityId,
            'compatible_vehicle_category_ids' => [$hookLift],
            'operational_status' => 'active',
            'affiliation' => 'gcm',
            'purchase_date' => '2024-01-01',
            'additional_data' => '<p>note</p>',
        ], $overrides);
    }

    public function test_system_admin_creates_a_full_asset(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/assets', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Container A-01')
            ->assertJsonPath('data.asset_type', 'container');

        $asset = Asset::firstOrFail();
        $this->assertSame('active', $asset->operational_status);
        $this->assertSame($this->admin->id, $asset->updated_by);
        $this->assertSame(1, $asset->compatibleVehicleCategories()->count());
    }

    public function test_data_entry_can_create_an_asset(): void
    {
        $this->actingAs($this->dataEntry, 'web')
            ->postJson('/api/v1/assets', $this->validPayload())
            ->assertCreated();
    }

    public function test_at_least_one_compatible_vehicle_category_is_required(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/assets', $this->validPayload(['compatible_vehicle_category_ids' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['compatible_vehicle_category_ids']);
    }

    public function test_capacity_must_match_the_asset_type(): void
    {
        $tankBand = AssetCapacityCategory::where('applies_to', 'tank')->value('id');

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/assets', $this->validPayload([
                'asset_type' => 'container',
                'asset_capacity_category_id' => $tankBand,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['asset_capacity_category_id']);
    }

    public function test_data_entry_cannot_create_a_deactivated_asset(): void
    {
        $this->actingAs($this->dataEntry, 'web')
            ->postJson('/api/v1/assets', $this->validPayload(['operational_status' => 'deactivated']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['operational_status']);
    }

    public function test_system_admin_can_create_a_deactivated_asset(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/assets', $this->validPayload(['operational_status' => 'deactivated']))
            ->assertCreated();

        $this->assertSame('deactivated', Asset::firstOrFail()->operational_status);
    }

    public function test_update_only_changes_the_name(): void
    {
        // Built via the factory (not a second acting user) — switching the
        // acting user inside one test method is unreliable for
        // $request->user() through the sanctum guard, see CLAUDE.md.
        $asset = Asset::factory()->create(['asset_type' => 'container', 'additional_data' => '<p>original</p>']);
        $originalType = $asset->asset_type;
        $originalCapacity = $asset->asset_capacity_category_id;

        $this->actingAs($this->dataEntry, 'web')
            ->patchJson("/api/v1/assets/{$asset->id}", [
                'name' => 'Renamed container',
                'asset_type' => 'tank',
                'asset_capacity_category_id' => 999,
                'additional_data' => 'hacked',
            ])
            ->assertOk();

        $asset->refresh();
        $this->assertSame('Renamed container', $asset->name);
        $this->assertSame($originalType, $asset->asset_type);
        $this->assertSame($originalCapacity, $asset->asset_capacity_category_id);
        $this->assertSame('<p>original</p>', $asset->additional_data);
        $this->assertSame($this->dataEntry->id, $asset->updated_by);
    }

    public function test_data_entry_can_set_maintenance_but_not_deactivate(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->dataEntry, 'web')
            ->patchJson("/api/v1/assets/{$asset->id}/status", ['status' => 'on_maintenance'])
            ->assertOk();
        $this->assertSame('on_maintenance', $asset->refresh()->operational_status);

        $this->actingAs($this->dataEntry, 'web')
            ->patchJson("/api/v1/assets/{$asset->id}/status", ['status' => 'deactivated'])
            ->assertStatus(422);
        $this->assertSame('on_maintenance', $asset->refresh()->operational_status);
    }

    public function test_data_entry_cannot_reactivate_a_deactivated_asset(): void
    {
        $asset = Asset::factory()->deactivated()->create();

        $this->actingAs($this->dataEntry, 'web')
            ->patchJson("/api/v1/assets/{$asset->id}/status", ['status' => 'active'])
            ->assertStatus(422);

        $this->assertSame('deactivated', $asset->refresh()->operational_status);
    }

    public function test_system_admin_can_reactivate_a_deactivated_asset(): void
    {
        $asset = Asset::factory()->deactivated()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/assets/{$asset->id}/status", ['status' => 'active'])
            ->assertOk();

        $this->assertSame('active', $asset->refresh()->operational_status);
    }

    public function test_stats_endpoint_returns_container_and_tank_counts(): void
    {
        $containerBand = AssetCapacityCategory::where('applies_to', 'container')->first();
        $tankBand = AssetCapacityCategory::where('applies_to', 'tank')->first();

        Asset::factory()->count(3)->create(['asset_type' => 'container', 'asset_capacity_category_id' => $containerBand->id]);
        Asset::factory()->onMaintenance()->create(['asset_type' => 'container', 'asset_capacity_category_id' => $containerBand->id]);
        Asset::factory()->create(['asset_type' => 'tank', 'asset_capacity_category_id' => $tankBand->id]);

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/assets/stats')
            ->assertOk()
            ->assertJsonPath('data.container.available', 3)
            ->assertJsonPath('data.container.on_maintenance', 1)
            ->assertJsonPath('data.container.in_projects', 0)
            ->assertJsonPath('data.tank.available', 1);
    }

    public function test_export_returns_xlsx_and_pdf(): void
    {
        Asset::factory()->count(2)->create();

        $this->actingAs($this->admin, 'web')->get('/api/v1/assets/export?format=xlsx')
            ->assertOk()->assertDownload('assets.xlsx');

        $this->actingAs($this->admin, 'web')->get('/api/v1/assets/export?format=pdf')->assertOk();
    }

    /**
     * Same normalization as the equivalent Vehicles/Drivers tests — an
     * untouched Quill editor submits `<p><br></p>`, not an empty string.
     * Assets only take additional_data at create time (edit is name-only
     * per FRD §1.7.3 — see UpdateAssetRequest's docblock), so this is the
     * only place it needs covering for this module.
     */
    public function test_an_empty_quill_editor_is_stored_as_null_not_empty_markup(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/assets', $this->validPayload(['additional_data' => '<p><br></p>']));

        $response->assertCreated();
        $this->assertNull(Asset::firstOrFail()->additional_data);
    }
}
