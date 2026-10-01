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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FRD V01.14 §1.9: (System Admin / Data Entry) create, edit and deactivate
 * main and sub-services; the auditor views and exports only; a driver has
 * no access. One actor per test (see CLAUDE.md on actingAs()).
 */
class ServicePolicyTest extends TestCase
{
    use RefreshDatabase;

    private MainService $main;

    private SubService $sub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));

        $this->main = MainService::factory()->create();
        $this->sub = SubService::factory()->forService($this->main)->create();
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create(['email' => "{$role}@gcm.test"]);
        $user->assignRole($role);

        return $user;
    }

    public static function managerRolesProvider(): array
    {
        return [['system_admin'], ['data_entry']];
    }

    #[DataProvider('managerRolesProvider')]
    public function test_managers_can_do_everything(string $role): void
    {
        $this->actingAs($this->actor($role), 'web');
        $category = VehicleCategory::first();
        $capacity = AssetCapacityCategory::factory()->container()->create();
        $facility = IntermediateFacility::factory()->create();

        $this->getJson('/api/v1/main-services')->assertOk();
        $this->getJson("/api/v1/main-services/{$this->main->id}")->assertOk();
        $this->postJson('/api/v1/main-services', [
            'name' => 'New', 'service_type' => 'hazardous', 'vehicle_category_ids' => [$category->id], 'operational_status' => 'active',
        ])->assertCreated();
        $this->patchJson("/api/v1/main-services/{$this->main->id}", ['name' => 'Renamed'])->assertOk();
        $this->get('/api/v1/main-services/export?format=xlsx')->assertOk();

        $this->getJson('/api/v1/sub-services')->assertOk();
        $this->getJson("/api/v1/sub-services/{$this->sub->id}")->assertOk();
        $this->postJson('/api/v1/sub-services', [
            'name' => 'New sub', 'main_service_id' => $this->main->id, 'asset_type' => 'container',
            'capacity_ids' => [$capacity->id], 'facility_ids' => [$facility->id], 'operational_status' => 'active',
        ])->assertCreated();
        $this->patchJson("/api/v1/sub-services/{$this->sub->id}", ['name' => 'Renamed sub'])->assertOk();
        $this->patchJson("/api/v1/sub-services/{$this->sub->id}/status", ['status' => 'deactivated'])->assertOk();

        $this->patchJson("/api/v1/main-services/{$this->main->id}/status", ['status' => 'deactivated'])->assertOk();
    }

    public function test_the_auditor_can_view_and_export_but_not_change_anything(): void
    {
        $this->actingAs($this->actor('auditor'), 'web');

        $this->getJson('/api/v1/main-services')->assertOk();
        $this->getJson("/api/v1/main-services/{$this->main->id}")->assertOk();
        $this->get('/api/v1/main-services/export?format=xlsx')->assertOk();
        $this->getJson('/api/v1/sub-services')->assertOk();
        $this->getJson("/api/v1/sub-services/{$this->sub->id}")->assertOk();

        $this->postJson('/api/v1/main-services', [])->assertForbidden();
        $this->patchJson("/api/v1/main-services/{$this->main->id}", ['name' => 'X'])->assertForbidden();
        $this->patchJson("/api/v1/main-services/{$this->main->id}/status", ['status' => 'deactivated'])->assertForbidden();
        $this->postJson('/api/v1/sub-services', [])->assertForbidden();
        $this->patchJson("/api/v1/sub-services/{$this->sub->id}", ['name' => 'X'])->assertForbidden();
        $this->patchJson("/api/v1/sub-services/{$this->sub->id}/status", ['status' => 'deactivated'])->assertForbidden();
    }

    public function test_a_driver_has_no_access(): void
    {
        $this->actingAs($this->actor('driver'), 'web');

        $this->getJson('/api/v1/main-services')->assertForbidden();
        $this->getJson("/api/v1/main-services/{$this->main->id}")->assertForbidden();
        $this->get('/api/v1/main-services/export?format=xlsx')->assertForbidden();
        $this->postJson('/api/v1/main-services', [])->assertForbidden();
        $this->getJson('/api/v1/sub-services')->assertForbidden();
        $this->getJson("/api/v1/sub-services/{$this->sub->id}")->assertForbidden();
        $this->postJson('/api/v1/sub-services', [])->assertForbidden();
    }
}
