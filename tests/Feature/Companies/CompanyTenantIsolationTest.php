<?php

namespace Tests\Feature\Companies;

use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTenantIsolationTest extends TestCase
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

    public function test_company_query_is_scoped_to_the_current_tenant_only(): void
    {
        app()->instance('tenant', $this->tenantB);
        Company::factory()->create();

        app()->instance('tenant', $this->tenantA);
        Company::factory()->create();

        $this->assertCount(1, Company::all());
    }

    public function test_the_api_never_lists_or_opens_another_tenants_company(): void
    {
        app()->instance('tenant', $this->tenantB);
        $foreign = Company::factory()->create(['name' => 'Foreign Co']);

        app()->instance('tenant', $this->tenantA);
        Company::factory()->create(['name' => 'Own Co']);
        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $list = $this->actingAs($admin, 'web')->getJson('/api/v1/companies');
        $list->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Own Co', $list->json('data.0.name'));

        $this->actingAs($admin, 'web')->getJson("/api/v1/companies/{$foreign->id}")->assertNotFound();
        $this->actingAs($admin, 'web')->patchJson("/api/v1/companies/{$foreign->id}", ['name' => 'Hijack', 'prefix' => 'HJK'])->assertNotFound();
        $this->actingAs($admin, 'web')->patchJson("/api/v1/companies/{$foreign->id}/status", ['status' => 'deactivated'])->assertNotFound();
        $this->actingAs($admin, 'web')->get("/api/v1/companies/{$foreign->id}/documents/contract")->assertNotFound();
    }

    public function test_the_export_only_contains_the_current_tenants_companies(): void
    {
        app()->instance('tenant', $this->tenantB);
        Company::factory()->count(3)->create();

        app()->instance('tenant', $this->tenantA);
        Company::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $this->actingAs($admin, 'web')->getJson('/api/v1/companies?per_page=100')
            ->assertJsonPath('meta.total', 1);
    }
}
