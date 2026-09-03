<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AssetCapacityCategorySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssetPolicyTest extends TestCase
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
        $this->seed(AssetCapacityCategorySeeder::class);
    }

    private function actor(string $role): User
    {
        app()->instance('tenant', $this->tenantA);
        $user = User::factory()->create(['email' => "{$role}@tenant-a.test"]);
        $user->assignRole($role);

        return $user;
    }

    #[DataProvider('managerRolesProvider')]
    public function test_system_admin_and_data_entry_can_list_assets(string $role): void
    {
        $this->actingAs($this->actor($role), 'web')
            ->getJson('/api/v1/assets')
            ->assertOk();
    }

    public static function managerRolesProvider(): array
    {
        return [['system_admin'], ['data_entry']];
    }

    public function test_auditor_can_list_but_not_create_assets(): void
    {
        $auditor = $this->actor('auditor');

        $this->actingAs($auditor, 'web')->getJson('/api/v1/assets')->assertOk();
        $this->actingAs($auditor, 'web')->postJson('/api/v1/assets', [])->assertForbidden();
    }

    public function test_driver_cannot_access_assets_at_all(): void
    {
        $driver = $this->actor('driver');

        $this->actingAs($driver, 'web')->getJson('/api/v1/assets')->assertForbidden();
        $this->actingAs($driver, 'web')->postJson('/api/v1/assets', [])->assertForbidden();
    }

    public function test_driver_can_still_read_capacity_categories_reference_list(): void
    {
        $driver = $this->actor('driver');

        $this->actingAs($driver, 'web')->getJson('/api/v1/asset-capacity-categories')->assertOk();
    }

    public function test_data_entry_cannot_create_a_capacity_category_is_false(): void
    {
        // data_entry *can* create categories (FRD) — sanity that the
        // policy isn't accidentally admin-only.
        $de = $this->actor('data_entry');

        $this->actingAs($de, 'web')->postJson('/api/v1/asset-capacity-categories', [
            'name' => 'DE band',
            'applies_to' => 'both',
            'capacity_cbm' => 8,
            'capacity_ton' => 4,
        ])->assertCreated();
    }

    public function test_system_admin_from_one_tenant_cannot_reach_another_tenants_asset(): void
    {
        $admin = $this->actor('system_admin');

        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active']);
        app()->instance('tenant', $tenantB);
        $this->seed(AssetCapacityCategorySeeder::class);
        $foreignAsset = Asset::factory()->create();

        app()->instance('tenant', $this->tenantA);

        $this->actingAs($admin, 'web')
            ->getJson("/api/v1/assets/{$foreignAsset->id}")
            ->assertNotFound();
    }
}
