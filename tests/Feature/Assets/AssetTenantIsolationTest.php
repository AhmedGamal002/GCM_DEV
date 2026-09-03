<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);
    }

    public function test_asset_query_is_scoped_to_current_tenant_only(): void
    {
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b', 'status' => 'active']);

        app()->instance('tenant', $tenantB);
        AssetCapacityCategory::factory()->create();
        Asset::factory()->create();

        app()->instance('tenant', $tenantA);
        AssetCapacityCategory::factory()->create();
        Asset::factory()->create();

        $this->assertCount(1, Asset::all());
    }

    public function test_stats_do_not_count_another_tenants_assets(): void
    {
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b', 'status' => 'active']);

        app()->instance('tenant', $tenantB);
        AssetCapacityCategory::factory()->container()->create();
        Asset::factory()->count(5)->create(['asset_type' => 'container']);

        app()->instance('tenant', $tenantA);
        AssetCapacityCategory::factory()->container()->create();
        Asset::factory()->count(2)->create(['asset_type' => 'container']);
        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $this->actingAs($admin, 'web')->getJson('/api/v1/assets/stats')
            ->assertOk()
            ->assertJsonPath('data.container.available', 2);
    }

    public function test_capacity_category_read_endpoint_only_returns_current_tenant_rows(): void
    {
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b', 'status' => 'active']);

        app()->instance('tenant', $tenantB);
        AssetCapacityCategory::factory()->create(['name' => 'B band']);

        app()->instance('tenant', $tenantA);
        AssetCapacityCategory::factory()->create(['name' => 'A band']);
        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $response = $this->actingAs($admin, 'web')->getJson('/api/v1/asset-capacity-categories');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('A band', $response->json('data.0.name'));
    }
}
