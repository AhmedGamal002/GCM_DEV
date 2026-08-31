<?php

namespace Tests\Feature\Fleet;

use App\Models\AssetCapacityCategory;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetCapacityCategoryReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_endpoint_returns_only_current_tenants_rows_for_any_role(): void
    {
        $this->seed(RoleSeeder::class);

        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b', 'status' => 'active']);

        app()->instance('tenant', $tenantA);
        AssetCapacityCategory::factory()->create(['name' => 'A skip']);
        $driver = User::factory()->create(['email' => 'driver@a.test']);
        $driver->assignRole('driver');

        app()->instance('tenant', $tenantB);
        AssetCapacityCategory::factory()->create(['name' => 'B skip']);

        app()->instance('tenant', $tenantA);

        $response = $this->actingAs($driver, 'web')->getJson('/api/v1/asset-capacity-categories');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('A skip', $response->json('data.0.name'));
    }
}
