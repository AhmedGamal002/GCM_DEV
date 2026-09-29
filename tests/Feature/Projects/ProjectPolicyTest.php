<?php

namespace Tests\Feature\Projects;

use App\Models\Company;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FRD V01.14 §1.12: (create / edit / deactivate) = System Admin / Data
 * Entry; Auditor = view + export only; Driver = nothing.
 */
class ProjectPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create(['email' => "{$role}@gcm.test"]);
        $user->assignRole($role);

        return $user;
    }

    #[DataProvider('managerRoles')]
    public function test_system_admin_and_data_entry_have_full_access(string $role): void
    {
        $project = Project::factory()->create();
        $actor = $this->actor($role);

        $this->actingAs($actor, 'web')->getJson('/api/v1/projects')->assertOk();
        $this->actingAs($actor, 'web')->getJson("/api/v1/projects/{$project->id}")->assertOk();
        $this->actingAs($actor, 'web')->get('/api/v1/projects/export')->assertOk();
        $this->actingAs($actor, 'web')->patchJson("/api/v1/projects/{$project->id}/status", ['status' => 'deactivated'])->assertOk();
    }

    public static function managerRoles(): array
    {
        return [['system_admin'], ['data_entry']];
    }

    public function test_auditor_can_view_and_export_but_not_change_anything(): void
    {
        $project = Project::factory()->create();
        $company = Company::factory()->create();
        $auditor = $this->actor('auditor');

        $this->actingAs($auditor, 'web')->getJson('/api/v1/projects')->assertOk();
        $this->actingAs($auditor, 'web')->getJson("/api/v1/projects/{$project->id}")->assertOk();
        $this->actingAs($auditor, 'web')->get('/api/v1/projects/export')->assertOk();

        $this->actingAs($auditor, 'web')->postJson('/api/v1/projects', ['name' => 'X', 'company_id' => $company->id, 'operational_status' => 'active'])->assertForbidden();
        $this->actingAs($auditor, 'web')->patchJson("/api/v1/projects/{$project->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($auditor, 'web')->patchJson("/api/v1/projects/{$project->id}/status", ['status' => 'deactivated'])->assertForbidden();
    }

    public function test_driver_has_no_access_at_all(): void
    {
        $project = Project::factory()->create();
        $driver = $this->actor('driver');

        $this->actingAs($driver, 'web')->getJson('/api/v1/projects')->assertForbidden();
        $this->actingAs($driver, 'web')->getJson("/api/v1/projects/{$project->id}")->assertForbidden();
        $this->actingAs($driver, 'web')->get('/api/v1/projects/export')->assertForbidden();
        $this->actingAs($driver, 'web')->postJson('/api/v1/projects', [])->assertForbidden();
    }

    public function test_guests_are_unauthenticated(): void
    {
        $this->getJson('/api/v1/projects')->assertUnauthorized();
    }

    public function test_the_manager_only_buttons_are_hidden_from_the_auditor(): void
    {
        $this->withoutVite();
        $project = Project::factory()->create();
        $auditor = $this->actor('auditor');

        // the Blade shell hands the JS a can_manage flag; the API stays the real guard
        $this->actingAs($auditor, 'web')->get('/app/project/list')->assertOk()->assertSee('can_manage: false', false);
        $this->actingAs($auditor, 'web')->get("/app/project/view/{$project->id}")->assertOk()
            ->assertSee('can_manage: false', false)
            ->assertSee('can_insert_asset: false', false);

        $manager = $this->actor('data_entry');
        $this->actingAs($manager, 'web')->get('/app/project/list')->assertOk()->assertSee('can_manage: true', false);
    }
}
