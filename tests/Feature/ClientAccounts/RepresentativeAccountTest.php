<?php

namespace Tests\Feature\ClientAccounts;

use App\Models\Company;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FRD V01.14 §1.11 / §1.12 — the optional "representative account" of a
 * client company (one of its project managers) and of a project (one of
 * the accounts that can see it), plus the user counts that come with
 * client accounts.
 */
class RepresentativeAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Company $company;

    private Project $tower;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('system_admin');

        $this->company = Company::factory()->create(['name' => 'Al Noor', 'prefix' => 'ALN']);
        $this->tower = Project::factory()->for($this->company)->create(['name' => 'Tower']);
    }

    private function companyPayload(array $overrides = []): array
    {
        return array_merge(['name' => 'Al Noor', 'operational_status' => 'active'], $overrides);
    }

    private function projectPayload(array $overrides = []): array
    {
        return array_merge(['name' => 'Tower'], $overrides);
    }

    // ------------------------------------------------------------ company

    public function test_a_project_manager_of_the_company_can_be_its_representative(): void
    {
        $manager = User::factory()->client($this->company)->create(['name' => 'Mona Manager']);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/companies/{$this->company->id}", $this->companyPayload(['representative_id' => $manager->id]))
            ->assertOk()
            ->assertJsonPath('data.representative.id', $manager->id)
            ->assertJsonPath('data.representative.name', 'Mona Manager');

        $this->assertSame($manager->id, $this->company->fresh()->representative_id);

        // ...and it shows on the list (FRD column "ممثل الشركة") and the details page.
        $this->actingAs($this->admin, 'web')->getJson('/api/v1/companies')->assertJsonPath('data.0.representative.name', 'Mona Manager');
        $this->actingAs($this->admin, 'web')->getJson("/api/v1/companies/{$this->company->id}")->assertJsonPath('data.representative.id', $manager->id);
    }

    public function test_the_company_representative_can_be_cleared(): void
    {
        $manager = User::factory()->client($this->company)->create();
        $this->company->forceFill(['representative_id' => $manager->id])->save();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/companies/{$this->company->id}", $this->companyPayload(['representative_id' => null]))
            ->assertOk()
            ->assertJsonPath('data.representative', null);
    }

    public function test_only_an_active_project_manager_of_the_same_company_qualifies(): void
    {
        $auditor = User::factory()->client($this->company, 'client_project_auditor')->create();
        $foreignManager = User::factory()->client(Company::factory()->create(['prefix' => 'GPC']))->create();
        $vacation = User::factory()->client($this->company)->create(['status' => 'on_vacation']);
        $staff = User::factory()->create();
        $staff->assignRole('data_entry');

        foreach ([$auditor, $foreignManager, $vacation, $staff] as $bad) {
            $this->actingAs($this->admin, 'web')
                ->patchJson("/api/v1/companies/{$this->company->id}", $this->companyPayload(['representative_id' => $bad->id]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('representative_id');
        }

        $this->assertNull($this->company->fresh()->representative_id);
    }

    public function test_saving_a_company_keeps_a_representative_who_has_since_gone_on_vacation(): void
    {
        $manager = User::factory()->client($this->company)->create();
        $this->company->forceFill(['representative_id' => $manager->id])->save();
        $manager->forceFill(['status' => 'on_vacation'])->save();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/companies/{$this->company->id}", $this->companyPayload(['name' => 'Al Noor Renamed', 'representative_id' => $manager->id]))
            ->assertOk()
            ->assertJsonPath('data.representative.id', $manager->id);
    }

    public function test_a_new_company_has_no_representative_field(): void
    {
        $manager = User::factory()->client($this->company)->create();

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/companies', ['name' => 'New Co', 'prefix' => 'NEW', 'operational_status' => 'active', 'representative_id' => $manager->id])
            ->assertCreated()
            ->assertJsonPath('data.representative', null);
    }

    public function test_the_company_user_count_counts_its_client_accounts(): void
    {
        User::factory()->client($this->company)->count(2)->create();
        User::factory()->client($this->company, 'client_project_auditor', false)->create(['status' => 'deactivated']);
        User::factory()->client(Company::factory()->create(['prefix' => 'GPC']))->create();

        $this->actingAs($this->admin, 'web')->getJson("/api/v1/companies/{$this->company->id}")->assertJsonPath('data.users_count', 3);

        $row = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/companies?search=ALN')->json('data'))->first();
        $this->assertSame(3, $row['users_count']);
    }

    // ------------------------------------------------------------ project

    public function test_an_account_that_can_see_the_project_can_be_its_representative(): void
    {
        $assigned = User::factory()->client($this->company, 'client_project_auditor', false)->create(['name' => 'Ali Assigned']);
        $assigned->projects()->attach($this->tower);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/projects/{$this->tower->id}", $this->projectPayload(['representative_id' => $assigned->id]))
            ->assertOk()
            ->assertJsonPath('data.representative.id', $assigned->id)
            ->assertJsonPath('data.representative.name', 'Ali Assigned');
    }

    public function test_an_all_projects_account_qualifies_for_every_project_of_its_company(): void
    {
        $all = User::factory()->client($this->company)->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/projects/{$this->tower->id}", $this->projectPayload(['representative_id' => $all->id]))
            ->assertOk();
    }

    public function test_an_account_that_cannot_see_the_project_is_refused(): void
    {
        $other = Project::factory()->for($this->company)->create();
        $notAssigned = User::factory()->client($this->company, 'client_project_manager', false)->create();
        $notAssigned->projects()->attach($other);
        $foreign = User::factory()->client(Company::factory()->create(['prefix' => 'GPC']))->create();
        $staff = User::factory()->create();
        $staff->assignRole('auditor');

        foreach ([$notAssigned, $foreign, $staff] as $bad) {
            $this->actingAs($this->admin, 'web')
                ->patchJson("/api/v1/projects/{$this->tower->id}", $this->projectPayload(['representative_id' => $bad->id]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('representative_id');
        }
    }

    public function test_a_new_project_may_pick_one_of_the_companys_all_projects_accounts(): void
    {
        $all = User::factory()->client($this->company)->create();
        $limited = User::factory()->client($this->company, 'client_project_manager', false)->create();

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/projects', ['name' => 'Plaza', 'company_id' => $this->company->id, 'operational_status' => 'active', 'representative_id' => $limited->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('representative_id');

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/projects', ['name' => 'Plaza', 'company_id' => $this->company->id, 'operational_status' => 'active', 'representative_id' => $all->id])
            ->assertCreated()
            ->assertJsonPath('data.representative.id', $all->id)
            // FRD: "عدد المستخدمين التابعون للمشروع" — the all-projects account already counts.
            ->assertJsonPath('data.users_count', 1);
    }

    public function test_the_project_user_count_is_all_projects_accounts_plus_assigned_ones(): void
    {
        User::factory()->client($this->company)->create();
        $assigned = User::factory()->client($this->company, 'client_project_auditor', false)->create();
        $assigned->projects()->attach($this->tower);
        $elsewhere = User::factory()->client($this->company, 'client_project_auditor', false)->create();
        $elsewhere->projects()->attach(Project::factory()->for($this->company)->create());
        User::factory()->client(Company::factory()->create(['prefix' => 'GPC']))->create();

        $this->actingAs($this->admin, 'web')->getJson("/api/v1/projects/{$this->tower->id}")->assertJsonPath('data.users_count', 2);

        $row = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/projects?search=Tower')->json('data'))->first();
        $this->assertSame(2, $row['users_count']);
    }

    // ------------------------------------------------------------ releasing a representative

    public function test_editing_an_account_out_of_the_role_clears_the_representations(): void
    {
        $manager = User::factory()->client($this->company)->create(['phone' => '+966500000031']);
        $this->company->forceFill(['representative_id' => $manager->id])->save();
        $this->tower->forceFill(['representative_id' => $manager->id])->save();

        // Demoted to auditor: still sees the tower, but is no longer a project manager.
        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/client-users/{$manager->id}", [
                'name' => 'Mona', 'phone' => '+966500000031', 'role' => 'client_project_auditor',
                'company_id' => $this->company->id, 'projects_scope' => 'all',
            ])
            ->assertOk();

        $this->assertNull($this->company->fresh()->representative_id, 'an auditor cannot represent the company');
        $this->assertSame($manager->id, $this->tower->fresh()->representative_id, 'still sees the project');
    }

    public function test_moving_an_account_to_another_company_clears_both_representations(): void
    {
        $manager = User::factory()->client($this->company)->create(['phone' => '+966500000032']);
        $this->company->forceFill(['representative_id' => $manager->id])->save();
        $this->tower->forceFill(['representative_id' => $manager->id])->save();
        $other = Company::factory()->create(['prefix' => 'GPC']);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/client-users/{$manager->id}", [
                'name' => 'Mona', 'phone' => '+966500000032', 'role' => 'client_project_manager',
                'company_id' => $other->id, 'projects_scope' => 'all',
            ])
            ->assertOk();

        $this->assertNull($this->company->fresh()->representative_id);
        $this->assertNull($this->tower->fresh()->representative_id);
    }

    public function test_removing_a_project_from_an_account_clears_that_projects_representative(): void
    {
        $account = User::factory()->client($this->company, 'client_project_manager', false)->create(['phone' => '+966500000033']);
        $account->projects()->attach($this->tower);
        $this->tower->forceFill(['representative_id' => $account->id])->save();
        $plaza = Project::factory()->for($this->company)->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/client-users/{$account->id}", [
                'name' => 'Mona', 'phone' => '+966500000033', 'role' => 'client_project_manager',
                'company_id' => $this->company->id, 'projects_scope' => 'specific', 'project_ids' => [$plaza->id],
            ])
            ->assertOk();

        $this->assertNull($this->tower->fresh()->representative_id);
    }
}
