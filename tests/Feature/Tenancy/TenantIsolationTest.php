<?php

namespace Tests\Feature\Tenancy;

use App\Concerns\BelongsToTenant;
use App\Exceptions\TenantContextMissingException;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active']);

        app()->instance('tenant', $this->tenantA);
        $this->userA = User::factory()->create(['email' => 'a@tenant-a.test']);
        $this->userA->assignRole('system_admin');

        app()->instance('tenant', $this->tenantB);
        $this->userB = User::factory()->create(['email' => 'b@tenant-b.test']);
        $this->userB->assignRole('system_admin');
    }

    public function test_user_query_is_scoped_to_current_tenant_only(): void
    {
        app()->instance('tenant', $this->tenantA);

        $users = User::all();

        $this->assertCount(1, $users);
        $this->assertTrue($users->first()->is($this->userA));
    }

    public function test_direct_id_lookup_of_another_tenants_user_returns_404_via_api_endpoint(): void
    {
        app()->instance('tenant', $this->tenantA);

        $response = $this->actingAs($this->userA, 'web')
            ->getJson("/api/v1/users/{$this->userB->id}");

        $response->assertNotFound();
    }

    public function test_own_tenants_user_is_reachable_via_api_endpoint(): void
    {
        app()->instance('tenant', $this->tenantA);

        $response = $this->actingAs($this->userA, 'web')
            ->getJson("/api/v1/users/{$this->userA->id}");

        $response->assertOk()->assertJsonPath('data.id', $this->userA->id);
    }

    public function test_creating_a_model_without_bound_tenant_context_throws(): void
    {
        app()->forgetInstance('tenant');

        $this->expectException(TenantContextMissingException::class);

        User::factory()->create();
    }

    public function test_querying_without_bound_tenant_context_throws(): void
    {
        app()->forgetInstance('tenant');

        $this->expectException(TenantContextMissingException::class);

        User::all();
    }

    public function test_without_global_scope_explicitly_bypasses_isolation(): void
    {
        app()->forgetInstance('tenant');

        $users = User::withoutGlobalScope(BelongsToTenant::class)->get();

        $this->assertCount(2, $users);
    }

    public function test_vehicle_query_is_scoped_to_current_tenant_only(): void
    {
        $this->seed(VehicleCategorySeeder::class);

        app()->instance('tenant', $this->tenantB);
        Vehicle::factory()->create();

        app()->instance('tenant', $this->tenantA);
        Vehicle::factory()->create();

        $this->assertCount(1, Vehicle::all());
    }

    public function test_role_relationship_traversal_stays_scoped_by_tenant(): void
    {
        app()->instance('tenant', $this->tenantA);

        $role = Role::where('name', 'system_admin')->first();

        $users = $role->users;

        $this->assertCount(1, $users);
        $this->assertTrue($users->first()->is($this->userA));
    }
}
