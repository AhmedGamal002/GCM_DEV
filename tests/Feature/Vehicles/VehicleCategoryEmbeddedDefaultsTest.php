<?php

namespace Tests\Feature\Vehicles;

use App\Models\AssetCapacityCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehicleCategory;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client request (outside the FRD): a vehicle category carries the
 * embedded-container values most of its vehicles share — has one or not,
 * container vs tank, which capacity. The create-vehicle form pre-fills them
 * once the category is picked (they stay editable per vehicle), so the API
 * has to store them, validate them like the vehicle's own fields, and hand
 * them out on the plain dropdown list every role reads.
 */
class VehicleCategoryEmbeddedDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private AssetCapacityCategory $containerCap;

    private AssetCapacityCategory $tankCap;

    private AssetCapacityCategory $bothCap;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->admin = User::factory()->create(['email' => 'admin@a.test']);
        $this->admin->assignRole('system_admin');

        $this->containerCap = AssetCapacityCategory::factory()->container()->create(['name' => 'Skip 8']);
        $this->tankCap = AssetCapacityCategory::factory()->tank()->create(['name' => 'Tank 10']);
        $this->bothCap = AssetCapacityCategory::factory()->create(['name' => 'Vacuum', 'applies_to' => 'both']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['name_en' => 'Crane truck', 'name_ar' => 'مركبات رافعة'], $overrides);
    }

    public function test_existing_and_default_categories_start_without_an_embedded_container(): void
    {
        $category = VehicleCategory::where('slug', 'hook_lift')->firstOrFail();

        $this->assertFalse($category->default_has_embedded_container);
        $this->assertNull($category->default_embedded_container_type);
        $this->assertNull($category->default_embedded_asset_capacity_category_id);
    }

    public function test_a_category_can_be_created_with_embedded_container_defaults(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/vehicle-categories', $this->payload([
                'default_has_embedded_container' => true,
                'default_embedded_container_type' => 'tank',
                'default_embedded_asset_capacity_category_id' => $this->tankCap->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.default_has_embedded_container', true)
            ->assertJsonPath('data.default_embedded_container_type', 'tank')
            ->assertJsonPath('data.default_embedded_asset_capacity_category_id', $this->tankCap->id);

        $this->assertDatabaseHas('vehicle_categories', [
            'name_en' => 'Crane truck',
            'default_has_embedded_container' => true,
            'default_embedded_container_type' => 'tank',
            'default_embedded_asset_capacity_category_id' => $this->tankCap->id,
        ]);
    }

    public function test_defaults_are_optional_and_a_category_without_them_has_no_embedded_container(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/vehicle-categories', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.default_has_embedded_container', false)
            ->assertJsonPath('data.default_embedded_container_type', null)
            ->assertJsonPath('data.default_embedded_asset_capacity_category_id', null);
    }

    /** The capacity may stay empty — "always a container" without a fixed size. */
    public function test_the_default_capacity_is_optional_but_the_type_is_not(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/vehicle-categories', $this->payload([
                'default_has_embedded_container' => true,
                'default_embedded_container_type' => 'container',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.default_embedded_container_type', 'container')
            ->assertJsonPath('data.default_embedded_asset_capacity_category_id', null);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/vehicle-categories', $this->payload(['name_en' => 'Other', 'name_ar' => 'أخرى', 'default_has_embedded_container' => true]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('default_embedded_container_type');
    }

    public function test_the_default_capacity_must_fit_the_default_type(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/vehicle-categories', $this->payload([
                'default_has_embedded_container' => true,
                'default_embedded_container_type' => 'container',
                'default_embedded_asset_capacity_category_id' => $this->tankCap->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('default_embedded_asset_capacity_category_id');

        // A capacity that applies to both fits either kind.
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/vehicle-categories', $this->payload([
                'default_has_embedded_container' => true,
                'default_embedded_container_type' => 'container',
                'default_embedded_asset_capacity_category_id' => $this->bothCap->id,
            ]))
            ->assertCreated();
    }

    public function test_a_capacity_from_another_tenant_is_rejected(): void
    {
        $other = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active']);
        app()->instance('tenant', $other);
        $foreign = AssetCapacityCategory::factory()->container()->create(['name' => 'Foreign']);
        app()->instance('tenant', $this->tenant);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/vehicle-categories', $this->payload([
                'default_has_embedded_container' => true,
                'default_embedded_container_type' => 'container',
                'default_embedded_asset_capacity_category_id' => $foreign->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('default_embedded_asset_capacity_category_id');
    }

    public function test_the_defaults_can_be_edited_and_cleared(): void
    {
        $category = VehicleCategory::where('slug', 'hook_lift')->firstOrFail();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/vehicle-categories/{$category->id}", [
                'name_en' => $category->name_en,
                'name_ar' => $category->name_ar,
                'default_has_embedded_container' => '1',
                'default_embedded_container_type' => 'container',
                'default_embedded_asset_capacity_category_id' => $this->containerCap->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.default_embedded_asset_capacity_category_id', $this->containerCap->id);

        // "No embedded container" wipes the kind and the capacity with it,
        // even if the client still sends the old pair.
        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/vehicle-categories/{$category->id}", [
                'name_en' => $category->name_en,
                'name_ar' => $category->name_ar,
                'default_has_embedded_container' => '0',
                'default_embedded_container_type' => 'container',
                'default_embedded_asset_capacity_category_id' => $this->containerCap->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.default_has_embedded_container', false)
            ->assertJsonPath('data.default_embedded_container_type', null)
            ->assertJsonPath('data.default_embedded_asset_capacity_category_id', null);

        $fresh = $category->fresh();
        $this->assertFalse($fresh->default_has_embedded_container);
        $this->assertNull($fresh->default_embedded_container_type);
        $this->assertNull($fresh->default_embedded_asset_capacity_category_id);
    }

    /** A rename-only call (older clients, Postman) must not wipe the stored defaults. */
    public function test_an_update_that_omits_the_defaults_leaves_them_alone(): void
    {
        $category = VehicleCategory::where('slug', 'hook_lift')->firstOrFail();
        $category->update([
            'default_has_embedded_container' => true,
            'default_embedded_container_type' => 'tank',
            'default_embedded_asset_capacity_category_id' => $this->tankCap->id,
        ]);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/vehicle-categories/{$category->id}", ['name_en' => 'Hook lift 2', 'name_ar' => 'خطاف ٢'])
            ->assertOk();

        $fresh = $category->fresh();
        $this->assertSame('Hook lift 2', $fresh->name_en);
        $this->assertTrue($fresh->default_has_embedded_container);
        $this->assertSame('tank', $fresh->default_embedded_container_type);
        $this->assertSame($this->tankCap->id, $fresh->default_embedded_asset_capacity_category_id);
    }

    /** The vehicle form's category dropdown is the plain list every role reads — it must carry the defaults. */
    public function test_the_plain_list_every_role_reads_carries_the_defaults(): void
    {
        $category = VehicleCategory::where('slug', 'water_tanker')->firstOrFail();
        $category->update([
            'default_has_embedded_container' => true,
            'default_embedded_container_type' => 'tank',
            'default_embedded_asset_capacity_category_id' => $this->tankCap->id,
        ]);

        $dataEntry = User::factory()->create(['email' => 'de@a.test']);
        $dataEntry->assignRole('data_entry');

        $row = collect($this->actingAs($dataEntry, 'web')->getJson('/api/v1/vehicle-categories')->assertOk()->json('data'))
            ->firstWhere('slug', 'water_tanker');

        $this->assertTrue($row['default_has_embedded_container']);
        $this->assertSame('tank', $row['default_embedded_container_type']);
        $this->assertSame($this->tankCap->id, $row['default_embedded_asset_capacity_category_id']);
    }
}
