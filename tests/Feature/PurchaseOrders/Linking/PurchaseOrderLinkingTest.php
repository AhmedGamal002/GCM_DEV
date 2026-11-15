<?php

namespace Tests\Feature\PurchaseOrders\Linking;

use App\Domain\Projects\Exports\ProjectsExport;
use App\Models\Company;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Tenant;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Feature\PurchaseOrders\PurchaseOrderTestCase;

/**
 * Part 6.4 — purchase orders linked into the client company and project pages (FRD V01.14 §1.11.3 and §1.12.3):
 * the real contract counts the API reports (they were 0 placeholders), the "Contracts" section of both pages,
 * and what a role without access to POs — Data Entry, which the FRD keeps away from them even for reading —
 * is shown instead. One test = one actor.
 */
class PurchaseOrderLinkingTest extends PurchaseOrderTestCase
{
    private Project $secondProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->secondProject = Project::factory()->for($this->company)->create(['name' => 'Second Tower']);

        // The default project has two POs (one deactivated), the second one a single PO; another company has one too.
        $this->line(po: ['number' => 'ALN-1']);
        $this->line(project: $this->project, po: ['number' => 'ALN-2', 'operational_status' => 'deactivated'])
            ->update(['main_service_id' => MainService::factory()->create()->id]);
        $this->line(project: $this->secondProject, po: ['number' => 'ALN-3']);

        $beta = Company::factory()->create(['prefix' => 'BET']);
        $this->line(project: Project::factory()->for($beta)->create(), po: ['number' => 'BET-1']);
    }

    private function as(string $role): static
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $this->actingAs($user, 'web');
    }

    // ── the counts ──────────────────────────────────────────────────

    public function test_a_project_reports_its_real_contract_count_with_every_status(): void
    {
        $this->actingAs($this->admin, 'web');

        $this->getJson("/api/v1/projects/{$this->project->id}")
            ->assertOk()
            ->assertJsonPath('data.contracts_count', 2)
            ->assertJsonPath('data.stats.contracts', 2);

        $this->getJson("/api/v1/projects/{$this->secondProject->id}")->assertJsonPath('data.contracts_count', 1);
    }

    public function test_the_project_list_carries_the_count_of_each_row(): void
    {
        $this->actingAs($this->admin, 'web');

        $rows = collect($this->getJson('/api/v1/projects?per_page=50')->assertOk()->json('data'))->pluck('contracts_count', 'name');

        $this->assertSame(2, $rows['Riyadh Hotel']);
        $this->assertSame(1, $rows['Second Tower']);
    }

    public function test_a_company_reports_the_contracts_of_all_its_projects_and_none_of_another_companys(): void
    {
        $this->actingAs($this->admin, 'web');

        $this->getJson("/api/v1/companies/{$this->company->id}")
            ->assertOk()
            ->assertJsonPath('data.contracts_count', 3)
            ->assertJsonPath('data.stats.contracts', 3)
            ->assertJsonPath('data.stats.projects', 2);
    }

    public function test_the_company_list_carries_the_count_too(): void
    {
        $this->actingAs($this->admin, 'web');

        $rows = collect($this->getJson('/api/v1/companies?per_page=50')->assertOk()->json('data'))->pluck('contracts_count', 'prefix');

        $this->assertSame(3, $rows['ALN']);
        $this->assertSame(1, $rows['BET']);
    }

    public function test_a_project_without_a_contract_reports_zero_not_null(): void
    {
        $this->actingAs($this->admin, 'web');
        $empty = Project::factory()->for($this->company)->create();

        $this->getJson("/api/v1/projects/{$empty->id}")->assertJsonPath('data.contracts_count', 0)->assertJsonPath('data.stats.contracts', 0);
    }

    public function test_the_auditor_sees_the_counts(): void
    {
        $this->as('auditor');

        $this->getJson("/api/v1/projects/{$this->project->id}")->assertJsonPath('data.contracts_count', 2);
        $this->getJson("/api/v1/companies/{$this->company->id}")->assertJsonPath('data.contracts_count', 3);
    }

    // ── Data Entry: no access to POs, so no figure ──────────────────

    public function test_data_entry_gets_no_contract_figure_anywhere(): void
    {
        $this->as('data_entry');

        $this->getJson("/api/v1/projects/{$this->project->id}")
            ->assertOk()->assertJsonPath('data.contracts_count', null)->assertJsonPath('data.stats.contracts', null);
        $this->getJson("/api/v1/companies/{$this->company->id}")
            ->assertOk()->assertJsonPath('data.contracts_count', null)->assertJsonPath('data.stats.contracts', null);

        $rows = $this->getJson('/api/v1/projects?per_page=50')->assertOk()->json('data');
        $this->assertNotEmpty($rows);
        $this->assertSame([null], collect($rows)->pluck('contracts_count')->unique()->values()->all());

        // and the PO list itself stays closed to it
        $this->getJson('/api/v1/purchase-orders')->assertForbidden();
    }

    // ── exports ─────────────────────────────────────────────────────

    public function test_the_project_export_carries_the_real_count_for_a_role_that_may_see_it(): void
    {
        $this->actingAs($this->admin, 'web');

        $projects = Project::withCount('purchaseOrders')->orderBy('name')->get();
        $rows = $projects->map(fn ($p) => [(new ProjectsExport($projects))->map($p)[2], (new ProjectsExport($projects))->map($p)[4]])->all();

        $this->assertContains(['Riyadh Hotel', 2], $rows);
        $this->assertContains(['Second Tower', 1], $rows);
    }

    public function test_the_project_export_leaves_the_column_blank_for_data_entry(): void
    {
        $this->as('data_entry');

        $projects = Project::withCount('purchaseOrders')->get();

        foreach ($projects as $project) {
            $this->assertNull((new ProjectsExport($projects))->map($project)[4]);
        }
    }

    public function test_the_project_export_endpoints_still_work(): void
    {
        Excel::fake();
        $this->actingAs($this->admin, 'web');

        $this->get('/api/v1/projects/export?format=xlsx')->assertOk();
        $this->get('/api/v1/projects/export?format=pdf')->assertOk();
    }

    // ── the "Contracts" section of the pages ────────────────────────

    public function test_the_company_page_has_the_contracts_table_for_the_admin(): void
    {
        $this->actingAs($this->admin, 'web');

        $this->get("/app/company/view/{$this->company->id}")->assertOk()
            ->assertSee('datatables-related-pos', false)
            ->assertSee('poRelatedTranslations', false)
            ->assertSee('can_manage: true', false)
            ->assertSee('can_export: true', false)
            // a company's list shows each PO's project
            ->assertSee('<th>Project</th>', false);
    }

    public function test_the_project_page_has_the_contracts_table_for_the_admin(): void
    {
        $this->actingAs($this->admin, 'web');

        $this->get("/app/project/view/{$this->project->id}")->assertOk()
            ->assertSee('datatables-related-pos', false)
            ->assertSee('poRelatedTranslations', false);
    }

    public function test_the_auditor_gets_the_table_without_add_or_nothing_to_write(): void
    {
        $this->as('auditor');

        $this->get("/app/project/view/{$this->project->id}")->assertOk()
            ->assertSee('datatables-related-pos', false)
            ->assertSee('can_manage: false', false)
            ->assertSee('can_export: true', false);
    }

    public function test_data_entry_gets_no_contracts_section_on_either_page(): void
    {
        $this->as('data_entry');

        foreach (["/app/company/view/{$this->company->id}", "/app/project/view/{$this->project->id}"] as $url) {
            $this->get($url)->assertOk()
                ->assertDontSee('datatables-related-pos', false)
                ->assertDontSee('poRelatedTranslations', false);
        }
    }

    // ── the table's data: the list narrowed to the company / project ──

    public function test_the_po_list_narrows_to_a_company_and_to_a_project_for_the_sections(): void
    {
        $this->actingAs($this->admin, 'web');

        $numbers = fn (string $query) => collect($this->getJson("/api/v1/purchase-orders?per_page=50&{$query}")->assertOk()->json('data'))
            ->pluck('number')->sort()->values()->all();

        $this->assertSame(['ALN-1', 'ALN-2', 'ALN-3'], $numbers("company_id={$this->company->id}"));
        $this->assertSame(['ALN-1', 'ALN-2'], $numbers("project_id={$this->project->id}"));
        $this->assertSame(['ALN-3'], $numbers("project_id={$this->secondProject->id}"));
    }

    // ── isolation ───────────────────────────────────────────────────

    public function test_another_tenants_counts_never_include_these_contracts(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $theirAdmin = User::factory()->create();
        $theirAdmin->assignRole('system_admin');
        $theirCompany = Company::factory()->create(['prefix' => 'ALN']);
        $theirProject = Project::factory()->for($theirCompany)->create();

        $this->actingAs($theirAdmin, 'web')
            ->getJson("/api/v1/projects/{$theirProject->id}")->assertJsonPath('data.contracts_count', 0);
        $this->getJson("/api/v1/companies/{$theirCompany->id}")->assertJsonPath('data.contracts_count', 0);
        $this->assertSame(0, PurchaseOrder::count());
    }
}
