<?php

namespace Tests\Feature\Fleet;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VehiclePolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);

        $this->tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        app()->instance('tenant', $this->tenantA);
    }

    private function actor(string $role): User
    {
        app()->instance('tenant', $this->tenantA);
        $user = User::factory()->create(['email' => "{$role}@tenant-a.test"]);
        $user->assignRole($role);

        return $user;
    }

    #[DataProvider('managerRolesProvider')]
    public function test_system_admin_and_data_entry_can_list_vehicles(string $role): void
    {
        $this->actingAs($this->actor($role), 'web')
            ->getJson('/api/v1/vehicles')
            ->assertOk();
    }

    public static function managerRolesProvider(): array
    {
        return [['system_admin'], ['data_entry']];
    }

    public function test_auditor_can_list_but_not_create_vehicles(): void
    {
        $auditor = $this->actor('auditor');

        $this->actingAs($auditor, 'web')->getJson('/api/v1/vehicles')->assertOk();
        $this->actingAs($auditor, 'web')->postJson('/api/v1/vehicles', [])->assertForbidden();
    }

    public function test_driver_cannot_access_vehicles_at_all(): void
    {
        $driver = $this->actor('driver');

        $this->actingAs($driver, 'web')->getJson('/api/v1/vehicles')->assertForbidden();
        $this->actingAs($driver, 'web')->postJson('/api/v1/vehicles', [])->assertForbidden();
    }

    public function test_system_admin_from_one_tenant_cannot_reach_another_tenants_vehicle(): void
    {
        $admin = $this->actor('system_admin');

        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active']);
        app()->instance('tenant', $tenantB);
        $foreignVehicle = Vehicle::factory()->create();

        app()->instance('tenant', $this->tenantA);

        $this->actingAs($admin, 'web')
            ->getJson("/api/v1/vehicles/{$foreignVehicle->id}")
            ->assertNotFound();
    }
}
