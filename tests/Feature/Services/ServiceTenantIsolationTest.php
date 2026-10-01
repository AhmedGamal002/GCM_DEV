<?php

namespace Tests\Feature\Services;

use App\Models\AssetCapacityCategory;
use App\Models\IntermediateFacility;
use App\Models\MainService;
use App\Models\SubService;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehicleCategory;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->tenantA = Tenant::create(['name' => 'A', 'slug' => 'a', 'status' => 'active']);
        $this->tenantB = Tenant::create(['name' => 'B', 'slug' => 'b', 'status' => 'active']);
    }

    private function adminOfA(): User
    {
        app()->instance('tenant', $this->tenantA);
        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        return $admin;
    }

    public function test_queries_are_scoped_to_the_current_tenant(): void
    {
        app()->instance('tenant', $this->tenantB);
        SubService::factory()->create();

        app()->instance('tenant', $this->tenantA);
        SubService::factory()->create();

        $this->assertCount(1, MainService::all());
        $this->assertCount(1, SubService::all());
    }

    public function test_another_tenants_services_are_a_404_everywhere(): void
    {
        app()->instance('tenant', $this->tenantB);
        $foreignSub = SubService::factory()->create();
        $foreignMain = $foreignSub->mainService;

        $this->actingAs($this->adminOfA(), 'web');

        $this->getJson("/api/v1/main-services/{$foreignMain->id}")->assertNotFound();
        $this->patchJson("/api/v1/main-services/{$foreignMain->id}", ['name' => 'X'])->assertNotFound();
        $this->patchJson("/api/v1/main-services/{$foreignMain->id}/status", ['status' => 'deactivated'])->assertNotFound();
        $this->getJson("/api/v1/sub-services/{$foreignSub->id}")->assertNotFound();
        $this->patchJson("/api/v1/sub-services/{$foreignSub->id}", ['name' => 'X'])->assertNotFound();
        $this->patchJson("/api/v1/sub-services/{$foreignSub->id}/status", ['status' => 'deactivated'])->assertNotFound();

        $this->getJson('/api/v1/main-services')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/sub-services')->assertJsonCount(0, 'data');
    }

    public function test_another_tenants_categories_capacities_facilities_and_main_services_cannot_be_linked(): void
    {
        app()->instance('tenant', $this->tenantB);
        $foreignCategory = VehicleCategory::first();
        $foreignCapacity = AssetCapacityCategory::factory()->container()->create();
        $foreignFacility = IntermediateFacility::factory()->create();
        $foreignMain = MainService::factory()->create();

        $admin = $this->adminOfA();
        $main = MainService::factory()->create();

        $this->actingAs($admin, 'web');

        $this->postJson('/api/v1/main-services', [
            'name' => 'X', 'service_type' => 'hazardous', 'vehicle_category_ids' => [$foreignCategory->id], 'operational_status' => 'active',
        ])->assertStatus(422)->assertJsonValidationErrors(['vehicle_category_ids.0']);

        $this->postJson('/api/v1/sub-services', [
            'name' => 'X', 'main_service_id' => $foreignMain->id, 'asset_type' => 'container',
            'capacity_ids' => [$foreignCapacity->id], 'facility_ids' => [$foreignFacility->id], 'operational_status' => 'active',
        ])->assertStatus(422)->assertJsonValidationErrors(['main_service_id', 'capacity_ids.0', 'facility_ids.0']);

        $this->postJson('/api/v1/sub-services', [
            'name' => 'X', 'main_service_id' => $main->id, 'asset_type' => 'container',
            'capacity_ids' => [$foreignCapacity->id], 'facility_ids' => [$foreignFacility->id], 'operational_status' => 'active',
        ])->assertStatus(422)->assertJsonValidationErrors(['capacity_ids.0', 'facility_ids.0']);
    }
}
