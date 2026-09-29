<?php

namespace Tests\Feature\Facilities;

use App\Models\IntermediateFacility;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FRD V01.14 §1.8: (System Admin / Data Entry) create, edit and deactivate;
 * the auditor views and exports only; a driver has no access. One actor per
 * test (see CLAUDE.md on actingAs()).
 */
class FacilityPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));
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
        $facility = IntermediateFacility::factory()->create();
        $this->actingAs($this->actor($role), 'web');

        $this->getJson('/api/v1/facilities')->assertOk();
        $this->getJson('/api/v1/facilities/stats')->assertOk();
        $this->getJson("/api/v1/facilities/{$facility->id}")->assertOk();
        $this->postJson('/api/v1/facilities', ['name' => 'N', 'prefix' => 'NNN', 'environmental_service' => 'disposal', 'operational_status' => 'active'])->assertCreated();
        $this->patchJson("/api/v1/facilities/{$facility->id}", ['name' => 'Renamed'])->assertOk();
        $this->patchJson("/api/v1/facilities/{$facility->id}/status", ['status' => 'deactivated'])->assertOk();
        $this->get('/api/v1/facilities/export?format=xlsx')->assertOk();
    }

    public function test_the_auditor_can_view_and_export_but_not_change_anything(): void
    {
        $facility = IntermediateFacility::factory()->create();
        $this->actingAs($this->actor('auditor'), 'web');

        $this->getJson('/api/v1/facilities')->assertOk();
        $this->getJson("/api/v1/facilities/{$facility->id}")->assertOk();
        $this->get('/api/v1/facilities/export?format=xlsx')->assertOk();

        $this->postJson('/api/v1/facilities', [])->assertForbidden();
        $this->patchJson("/api/v1/facilities/{$facility->id}", ['name' => 'X'])->assertForbidden();
        $this->patchJson("/api/v1/facilities/{$facility->id}/status", ['status' => 'deactivated'])->assertForbidden();
    }

    public function test_a_driver_has_no_access(): void
    {
        $facility = IntermediateFacility::factory()->create();
        $this->actingAs($this->actor('driver'), 'web');

        $this->getJson('/api/v1/facilities')->assertForbidden();
        $this->getJson('/api/v1/facilities/stats')->assertForbidden();
        $this->getJson("/api/v1/facilities/{$facility->id}")->assertForbidden();
        $this->get("/api/v1/facilities/{$facility->id}/contract")->assertForbidden();
        $this->postJson('/api/v1/facilities', [])->assertForbidden();
        $this->get('/api/v1/facilities/export?format=xlsx')->assertForbidden();
    }
}
