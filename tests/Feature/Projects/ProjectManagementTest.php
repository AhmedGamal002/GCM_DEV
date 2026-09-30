<?php

namespace Tests\Feature\Projects;

use App\Models\Company;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FRD V01.14 §1.12 — client projects: create / view / edit / deactivate.
 */
class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $dataEntry;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->admin = User::factory()->create(['email' => 'admin@gcm.test']);
        $this->admin->assignRole('system_admin');

        $this->dataEntry = User::factory()->create(['email' => 'de@gcm.test']);
        $this->dataEntry->assignRole('data_entry');

        $this->company = Company::factory()->create(['name' => 'Al Noor', 'prefix' => 'ALN']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Al Noor Tower',
            'company_id' => $this->company->id,
            'operational_status' => 'active',
        ], $overrides);
    }

    public function test_system_admin_creates_a_project_with_only_the_required_fields(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/projects', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Al Noor Tower')
            ->assertJsonPath('data.company.id', $this->company->id)
            ->assertJsonPath('data.operational_status', 'active');

        $project = Project::firstOrFail();
        $this->assertSame($this->admin->id, $project->updated_by);
        $this->assertSame($this->company->id, $project->company_id);
        // FRD: numbers are built from the company's short name.
        $this->assertSame('ALN-P0001', $project->code);
        $this->assertSame('ALN-P0001', $response->json('data.code'));
    }

    public function test_the_project_id_is_the_company_prefix_plus_a_running_number_per_company(): void
    {
        $other = Company::factory()->create(['name' => 'Gulf', 'prefix' => 'GPC']);

        foreach ([$this->company->id, $this->company->id, $other->id, $this->company->id] as $i => $companyId) {
            $this->actingAs($this->admin, 'web')
                ->postJson('/api/v1/projects', $this->payload(['name' => "Project {$i}", 'company_id' => $companyId]))
                ->assertCreated();
        }

        $this->assertSame(
            ['ALN-P0001', 'ALN-P0002', 'GPC-P0001', 'ALN-P0003'],
            Project::orderBy('id')->pluck('code')->all()
        );
    }

    public function test_data_entry_can_create_a_project_even_a_deactivated_one(): void
    {
        $this->actingAs($this->dataEntry, 'web')
            ->postJson('/api/v1/projects', $this->payload(['operational_status' => 'deactivated']))
            ->assertCreated();

        $this->assertSame('deactivated', Project::firstOrFail()->operational_status);
    }

    public function test_name_company_and_status_are_required(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/projects', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'company_id', 'operational_status']);
    }

    public function test_all_the_optional_fields_are_saved(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/projects', $this->payload([
                'operational_region' => 'Riyadh',
                'phone' => '+966500000000',
                'email' => 'site@alnoor.test',
                'address' => 'King Fahd Road',
                'location_url' => 'https://maps.google.com/?q=1,2',
                'additional_data' => '<p>Gate 4</p>',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.operational_region', 'Riyadh')
            ->assertJsonPath('data.phone', '+966500000000')
            ->assertJsonPath('data.email', 'site@alnoor.test')
            ->assertJsonPath('data.address', 'King Fahd Road')
            ->assertJsonPath('data.location_url', 'https://maps.google.com/?q=1,2')
            ->assertJsonPath('data.additional_data', '<p>Gate 4</p>');
    }

    public function test_an_empty_rich_text_editor_is_stored_as_null(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/projects', $this->payload(['additional_data' => '<p><br></p>']))
            ->assertCreated()
            ->assertJsonPath('data.additional_data', null);
    }

    public function test_the_map_link_must_be_an_http_or_https_url(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/projects', $this->payload(['location_url' => 'javascript:alert(1)']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_url']);
    }

    public function test_a_deactivated_company_is_not_offered_for_new_projects(): void
    {
        $inactive = Company::factory()->deactivated()->create(['prefix' => 'OFF']);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/projects', $this->payload(['company_id' => $inactive->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company_id']);
    }

    public function test_another_tenants_company_cannot_own_a_project(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $foreign = Company::factory()->create(['prefix' => 'FOR']);
        app()->instance('tenant', $this->tenant);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/projects', $this->payload(['company_id' => $foreign->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company_id']);
    }

    public function test_show_returns_the_project_with_its_company(): void
    {
        $project = Project::factory()->for($this->company)->create(['name' => 'Tower']);

        $this->actingAs($this->admin, 'web')
            ->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Tower')
            ->assertJsonPath('data.company.name', 'Al Noor')
            ->assertJsonPath('data.stats.contracts', 0);
    }

    public function test_update_changes_the_fields_but_never_the_company_or_the_code(): void
    {
        $project = Project::factory()->for($this->company)->create(['name' => 'Tower']);
        $other = Company::factory()->create(['prefix' => 'GPC']);

        $this->actingAs($this->dataEntry, 'web')
            ->patchJson("/api/v1/projects/{$project->id}", [
                'name' => 'Tower Renamed',
                'operational_region' => 'Jeddah',
                'company_id' => $other->id,   // must be ignored
                'operational_status' => 'deactivated',   // has its own endpoint
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Tower Renamed')
            ->assertJsonPath('data.operational_region', 'Jeddah')
            ->assertJsonPath('data.company.id', $this->company->id)
            ->assertJsonPath('data.operational_status', 'active');

        $project->refresh();
        $this->assertSame($this->company->id, $project->company_id);
        $this->assertSame('ALN-P0001', $project->code);
        $this->assertSame($this->dataEntry->id, $project->updated_by);
    }

    public function test_update_still_requires_a_name(): void
    {
        $project = Project::factory()->for($this->company)->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/projects/{$project->id}", ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_status_can_be_changed_both_ways_by_system_admin_and_data_entry(): void
    {
        $project = Project::factory()->for($this->company)->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/projects/{$project->id}/status", ['status' => 'deactivated'])
            ->assertOk()
            ->assertJsonPath('data.operational_status', 'deactivated');

        $this->actingAs($this->dataEntry, 'web')
            ->patchJson("/api/v1/projects/{$project->id}/status", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.operational_status', 'active');
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $project = Project::factory()->for($this->company)->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/projects/{$project->id}/status", ['status' => 'on_maintenance'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_deactivating_a_company_leaves_its_projects_alone_and_vice_versa(): void
    {
        $project = Project::factory()->for($this->company)->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/companies/{$this->company->id}/status", ['status' => 'deactivated'])
            ->assertOk();
        $this->assertSame('active', $project->refresh()->operational_status);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/projects/{$project->id}/status", ['status' => 'deactivated'])
            ->assertOk();
        $this->assertSame('deactivated', $this->company->refresh()->operational_status);
    }

    public function test_list_filters_by_company_status_and_search(): void
    {
        $other = Company::factory()->create(['name' => 'Gulf Petro', 'prefix' => 'GPC']);
        Project::factory()->for($this->company)->create(['name' => 'Tower']);
        Project::factory()->for($this->company)->deactivated()->create(['name' => 'Warehouse']);
        Project::factory()->for($other)->create(['name' => 'Plant']);

        $names = fn (string $query) => collect(
            $this->actingAs($this->admin, 'web')->getJson("/api/v1/projects?{$query}")->assertOk()->json('data')
        )->pluck('name')->sort()->values()->all();

        $this->assertSame(['Plant', 'Tower', 'Warehouse'], $names(''));
        $this->assertSame(['Tower', 'Warehouse'], $names("company_id={$this->company->id}"));
        $this->assertSame(['Warehouse'], $names('operational_status=deactivated'));
        $this->assertSame(['Tower'], $names("company_id={$this->company->id}&operational_status=active"));
        // every column the table shows is searchable: name, ID, company name
        $this->assertSame(['Plant'], $names('search=Plant'));
        $this->assertSame(['Tower', 'Warehouse'], $names('search=ALN-P'));
        $this->assertSame(['Plant'], $names('search=Gulf'));
    }

    public function test_the_list_can_be_sorted_and_paginated(): void
    {
        foreach (['B', 'C', 'A'] as $name) {
            Project::factory()->for($this->company)->create(['name' => $name]);
        }

        $response = $this->actingAs($this->admin, 'web')
            ->getJson('/api/v1/projects?sort_by=name&sort_dir=desc&per_page=2');

        $response->assertOk()->assertJsonPath('meta.total', 3);
        $this->assertSame(['C', 'B'], collect($response->json('data'))->pluck('name')->all());
    }

    public function test_an_unknown_sort_column_falls_back_to_the_name(): void
    {
        Project::factory()->for($this->company)->create(['name' => 'B']);
        Project::factory()->for($this->company)->create(['name' => 'A']);

        $response = $this->actingAs($this->admin, 'web')->getJson('/api/v1/projects?sort_by=password');

        $response->assertOk();
        $this->assertSame(['A', 'B'], collect($response->json('data'))->pluck('name')->all());
    }

    public function test_the_company_reports_its_real_project_count(): void
    {
        Project::factory()->for($this->company)->count(2)->create();
        Project::factory()->create(); // another company's project

        $this->actingAs($this->admin, 'web')
            ->getJson("/api/v1/companies/{$this->company->id}")
            ->assertOk()
            ->assertJsonPath('data.projects_count', 2)
            ->assertJsonPath('data.stats.projects', 2);

        $list = $this->actingAs($this->admin, 'web')->getJson('/api/v1/companies?search=Al%20Noor');
        $this->assertSame(2, $list->json('data.0.projects_count'));
    }

    public function test_the_pages_render_for_a_manager(): void
    {
        $this->withoutVite();
        $project = Project::factory()->for($this->company)->create();

        $this->actingAs($this->admin, 'web')->get('/app/project/list')->assertOk();
        $this->actingAs($this->admin, 'web')->get('/app/project/add')->assertOk();
        $this->actingAs($this->admin, 'web')->get("/app/project/view/{$project->id}")->assertOk();
        $this->actingAs($this->admin, 'web')->get("/app/project/edit/{$project->id}")->assertOk();
        $this->actingAs($this->admin, 'web')->get('/app/asset/insert-into-project')->assertOk();
    }
}
