<?php

namespace Tests\Feature\Facilities;

use App\Models\IntermediateFacility;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityTenantIsolationTest extends TestCase
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

    public function test_the_query_is_scoped_to_the_current_tenant(): void
    {
        app()->instance('tenant', $this->tenantB);
        IntermediateFacility::factory()->create();

        app()->instance('tenant', $this->tenantA);
        IntermediateFacility::factory()->create();

        $this->assertCount(1, IntermediateFacility::all());
    }

    public function test_another_tenants_facility_is_a_404_everywhere(): void
    {
        app()->instance('tenant', $this->tenantB);
        $foreign = IntermediateFacility::factory()->create();

        app()->instance('tenant', $this->tenantA);
        $admin = User::factory()->create();
        $admin->assignRole('system_admin');
        $this->actingAs($admin, 'web');

        $this->getJson("/api/v1/facilities/{$foreign->id}")->assertNotFound();
        $this->patchJson("/api/v1/facilities/{$foreign->id}", ['name' => 'X'])->assertNotFound();
        $this->patchJson("/api/v1/facilities/{$foreign->id}/status", ['status' => 'deactivated'])->assertNotFound();
        $this->get("/api/v1/facilities/{$foreign->id}/contract")->assertNotFound();
    }

    public function test_stats_and_list_do_not_include_another_tenants_facilities(): void
    {
        app()->instance('tenant', $this->tenantB);
        IntermediateFacility::factory()->count(5)->recycling()->create();

        app()->instance('tenant', $this->tenantA);
        IntermediateFacility::factory()->count(2)->recycling()->create();
        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $this->actingAs($admin, 'web')->getJson('/api/v1/facilities/stats')
            ->assertOk()
            ->assertJsonPath('data.recycle.total', 2);
    }
}
