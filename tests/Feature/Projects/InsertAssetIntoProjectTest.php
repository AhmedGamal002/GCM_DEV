<?php

namespace Tests\Feature\Projects;

use App\Models\Asset;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FRD V01.14 §1.7.3 "Insert an asset into a project": the asset leaves the
 * pool of available assets and shows up as "in a project" (with the
 * project's name) on the assets list, the stat cards and the project page.
 */
class InsertAssetIntoProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));

        $this->admin = User::factory()->create();
        $this->admin->assignRole('system_admin');
    }

    private function insert(Project $project, Asset $asset, ?User $actor = null)
    {
        return $this->actingAs($actor ?? $this->admin, 'web')
            ->postJson("/api/v1/projects/{$project->id}/assets", ['asset_id' => $asset->id]);
    }

    public function test_an_available_asset_is_inserted_into_an_active_project(): void
    {
        $project = Project::factory()->create(['name' => 'Tower']);
        $asset = Asset::factory()->container()->create(['name' => 'Box 1']);

        $this->insert($project, $asset)
            ->assertCreated()
            ->assertJsonPath('data.availability', 'in_project')
            ->assertJsonPath('data.operational_status', 'active')
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonPath('data.project.name', 'Tower');

        $asset->refresh();
        $this->assertSame($project->id, $asset->project_id);
        $this->assertSame($this->admin->id, $asset->updated_by);
    }

    #[DataProvider('managerRoles')]
    public function test_system_admin_and_data_entry_may_insert(string $role): void
    {
        $actor = User::factory()->create();
        $actor->assignRole($role);

        $this->insert(Project::factory()->create(), Asset::factory()->create(), $actor)->assertCreated();
    }

    public static function managerRoles(): array
    {
        return [['system_admin'], ['data_entry']];
    }

    public function test_auditor_and_driver_may_not_insert(): void
    {
        $project = Project::factory()->create();
        $asset = Asset::factory()->create();

        foreach (['auditor', 'driver'] as $role) {
            $actor = User::factory()->create();
            $actor->assignRole($role);
            $this->insert($project, $asset, $actor)->assertForbidden();
        }

        $this->assertNull($asset->fresh()->project_id);
    }

    public function test_an_asset_can_only_be_in_one_project(): void
    {
        $first = Project::factory()->create();
        $second = Project::factory()->create();
        $asset = Asset::factory()->create();

        $this->insert($first, $asset)->assertCreated();

        $this->insert($second, $asset)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['asset_id']);
        // not even the same project twice
        $this->insert($first, $asset)->assertStatus(422)->assertJsonValidationErrors(['asset_id']);

        $this->assertSame($first->id, $asset->fresh()->project_id);
    }

    public function test_an_asset_in_maintenance_or_deactivated_cannot_be_inserted(): void
    {
        $project = Project::factory()->create();

        foreach ([Asset::factory()->onMaintenance()->create(), Asset::factory()->deactivated()->create()] as $asset) {
            $this->insert($project, $asset)->assertStatus(422)->assertJsonValidationErrors(['asset_id']);
            $this->assertNull($asset->fresh()->project_id);
        }
    }

    public function test_nothing_can_be_inserted_into_a_deactivated_project(): void
    {
        $project = Project::factory()->deactivated()->create();
        $asset = Asset::factory()->create();

        $this->insert($project, $asset)->assertStatus(422)->assertJsonValidationErrors(['project']);
        $this->assertNull($asset->fresh()->project_id);
    }

    public function test_the_asset_is_required_and_must_exist(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/projects/{$project->id}/assets", [])
            ->assertStatus(422)->assertJsonValidationErrors(['asset_id']);
        $this->actingAs($this->admin, 'web')->postJson("/api/v1/projects/{$project->id}/assets", ['asset_id' => 999999])
            ->assertStatus(422)->assertJsonValidationErrors(['asset_id']);
        $this->actingAs($this->admin, 'web')->postJson('/api/v1/projects/999999/assets', ['asset_id' => 1])
            ->assertNotFound();
    }

    public function test_the_active_filter_means_available_and_in_project_is_its_own_filter(): void
    {
        $project = Project::factory()->create();
        $free = Asset::factory()->create(['name' => 'Free']);
        $placed = Asset::factory()->create(['name' => 'Placed']);
        $this->insert($project, $placed)->assertCreated();

        $names = fn (string $query) => collect(
            $this->actingAs($this->admin, 'web')->getJson("/api/v1/assets?{$query}")->assertOk()->json('data')
        )->pluck('name')->sort()->values()->all();

        $this->assertSame(['Free', 'Placed'], $names(''));
        $this->assertSame(['Free'], $names('operational_status=active'));
        $this->assertSame(['Placed'], $names('operational_status=in_project'));
        $this->assertSame(['Placed'], $names("project_id={$project->id}"));
        $this->assertSame([], $names('project_id=999999'));
    }

    public function test_the_list_exposes_the_project_and_availability(): void
    {
        $project = Project::factory()->create(['name' => 'Tower']);
        $asset = Asset::factory()->create(['name' => 'Placed']);
        $this->insert($project, $asset)->assertCreated();

        $row = $this->actingAs($this->admin, 'web')->getJson('/api/v1/assets?search=Placed')->json('data.0');
        $this->assertSame('in_project', $row['availability']);
        $this->assertSame('Tower', $row['project']['name']);

        // an asset that is placed AND then sent to maintenance shows as maintenance
        $this->actingAs($this->admin, 'web')->patchJson("/api/v1/assets/{$asset->id}/status", ['status' => 'on_maintenance'])
            ->assertOk()
            ->assertJsonPath('data.availability', 'on_maintenance')
            ->assertJsonPath('data.project.name', 'Tower');
    }

    public function test_the_stat_cards_split_available_from_in_projects(): void
    {
        $project = Project::factory()->create();
        Asset::factory()->container()->count(2)->create();
        $placed = Asset::factory()->container()->create();
        Asset::factory()->tank()->create();
        Asset::factory()->container()->onMaintenance()->create();
        $this->insert($project, $placed)->assertCreated();

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/assets/stats')
            ->assertOk()
            ->assertJsonPath('data.container.available', 2)
            ->assertJsonPath('data.container.in_projects', 1)
            ->assertJsonPath('data.container.on_maintenance', 1)
            ->assertJsonPath('data.container.deactivated', 0)
            ->assertJsonPath('data.tank.available', 1)
            ->assertJsonPath('data.tank.in_projects', 0);
    }

    public function test_the_asset_export_can_be_limited_to_one_project(): void
    {
        $project = Project::factory()->create();
        $placed = Asset::factory()->create(['name' => 'Placed']);
        Asset::factory()->create(['name' => 'Free']);
        $this->insert($project, $placed)->assertCreated();

        \Maatwebsite\Excel\Facades\Excel::fake();
        $this->actingAs($this->admin, 'web')->get("/api/v1/assets/export?format=xlsx&project_id={$project->id}")->assertOk();

        $rows = collect();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('assets.xlsx', function ($export) use (&$rows) {
            $rows = $export->collection();

            return true;
        });
        $this->assertSame(['Placed'], $rows->pluck('name')->all());
    }
}
