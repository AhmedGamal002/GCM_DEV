<?php

namespace Tests\Feature\Projects;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTenantIsolationTest extends TestCase
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

    public function test_project_query_is_scoped_to_the_current_tenant_only(): void
    {
        app()->instance('tenant', $this->tenantB);
        Project::factory()->create();

        app()->instance('tenant', $this->tenantA);
        Project::factory()->create();

        $this->assertCount(1, Project::all());
    }

    public function test_the_same_code_can_exist_in_two_tenants(): void
    {
        app()->instance('tenant', $this->tenantB);
        $b = Project::factory()->for(Company::factory()->create(['prefix' => 'ALN']))->create();

        app()->instance('tenant', $this->tenantA);
        $a = Project::factory()->for(Company::factory()->create(['prefix' => 'ALN']))->create();

        $this->assertSame('ALN-P0001', $a->code);
        $this->assertSame('ALN-P0001', $b->code);
    }

    public function test_the_api_never_lists_or_opens_another_tenants_project(): void
    {
        app()->instance('tenant', $this->tenantB);
        $foreign = Project::factory()->create(['name' => 'Foreign Site']);
        $foreignAsset = Asset::factory()->create();

        app()->instance('tenant', $this->tenantA);
        Project::factory()->create(['name' => 'Own Site']);
        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $list = $this->actingAs($admin, 'web')->getJson('/api/v1/projects');
        $list->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Own Site', $list->json('data.0.name'));

        $this->actingAs($admin, 'web')->getJson("/api/v1/projects/{$foreign->id}")->assertNotFound();
        $this->actingAs($admin, 'web')->patchJson("/api/v1/projects/{$foreign->id}", ['name' => 'Hijack'])->assertNotFound();
        $this->actingAs($admin, 'web')->patchJson("/api/v1/projects/{$foreign->id}/status", ['status' => 'deactivated'])->assertNotFound();
        $this->actingAs($admin, 'web')->postJson("/api/v1/projects/{$foreign->id}/assets", ['asset_id' => $foreignAsset->id])->assertNotFound();
    }

    public function test_the_export_and_list_only_count_the_current_tenants_projects(): void
    {
        app()->instance('tenant', $this->tenantB);
        Project::factory()->count(3)->create();

        app()->instance('tenant', $this->tenantA);
        Project::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $this->actingAs($admin, 'web')->getJson('/api/v1/projects?per_page=100')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_an_asset_from_another_tenant_cannot_be_put_in_my_project(): void
    {
        app()->instance('tenant', $this->tenantB);
        $foreignAsset = Asset::factory()->create();

        app()->instance('tenant', $this->tenantA);
        $project = Project::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $this->actingAs($admin, 'web')
            ->postJson("/api/v1/projects/{$project->id}/assets", ['asset_id' => $foreignAsset->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['asset_id']);

        app()->instance('tenant', $this->tenantB);
        $this->assertNull($foreignAsset->fresh()->project_id);
    }
}
