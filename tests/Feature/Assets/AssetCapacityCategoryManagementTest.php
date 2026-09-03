<?php

namespace Tests\Feature\Assets;

use App\Models\AssetCapacityCategory;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetCapacityCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $dataEntry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->admin = User::factory()->create(['email' => 'admin@gcm.test']);
        $this->admin->assignRole('system_admin');

        $this->dataEntry = User::factory()->create(['email' => 'de@gcm.test']);
        $this->dataEntry->assignRole('data_entry');
    }

    public function test_create_a_capacity_category(): void
    {
        $response = $this->actingAs($this->admin, 'web')->postJson('/api/v1/asset-capacity-categories', [
            'name' => 'Roll-on/off',
            'applies_to' => 'container',
            'capacity_cbm' => 20,
            'capacity_ton' => 8,
            'additional_data' => 'big skip',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Roll-on/off');

        $category = AssetCapacityCategory::firstOrFail();
        $this->assertSame('container', $category->applies_to);
        $this->assertSame($this->admin->id, $category->updated_by);
    }

    public function test_both_capacity_units_are_required(): void
    {
        $this->actingAs($this->admin, 'web')->postJson('/api/v1/asset-capacity-categories', [
            'name' => 'Missing units',
            'applies_to' => 'both',
        ])->assertStatus(422)->assertJsonValidationErrors(['capacity_cbm', 'capacity_ton']);
    }

    public function test_edit_only_changes_the_name_capacity_and_type_are_locked(): void
    {
        $category = AssetCapacityCategory::factory()->create([
            'name' => 'Old name',
            'applies_to' => 'container',
            'capacity_cbm' => 10,
            'capacity_ton' => 3.5,
        ]);

        $this->actingAs($this->dataEntry, 'web')->patchJson("/api/v1/asset-capacity-categories/{$category->id}", [
            'name' => 'New name',
            'applies_to' => 'tank',
            'capacity_cbm' => 999,
            'capacity_ton' => 999,
        ])->assertOk();

        $category->refresh();
        $this->assertSame('New name', $category->name);
        $this->assertSame('container', $category->applies_to);
        $this->assertSame('10.00', $category->capacity_cbm);
        $this->assertSame('3.50', $category->capacity_ton);
        $this->assertSame($this->dataEntry->id, $category->updated_by);
    }

    public function test_index_filters_by_applies_to_and_for_type(): void
    {
        AssetCapacityCategory::factory()->create(['name' => 'C only', 'applies_to' => 'container']);
        AssetCapacityCategory::factory()->create(['name' => 'T only', 'applies_to' => 'tank']);
        AssetCapacityCategory::factory()->create(['name' => 'Both', 'applies_to' => 'both']);

        $exact = $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/asset-capacity-categories?applies_to=tank')
            ->assertOk()->json('data.*.name');
        $this->assertSame(['T only'], $exact);

        // for_type=container should include container + both
        $forType = $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/asset-capacity-categories?for_type=container')
            ->assertOk()->json('data.*.name');
        sort($forType);
        $this->assertSame(['Both', 'C only'], $forType);
    }

    public function test_there_is_no_delete_route(): void
    {
        $category = AssetCapacityCategory::factory()->create();

        $this->actingAs($this->admin, 'web')
            ->deleteJson("/api/v1/asset-capacity-categories/{$category->id}")
            ->assertStatus(405);
    }
}
