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
 * FRD V01.14 §1.4 — a client account only ever sees the projects of its own
 * company: all of them ("جميع المشروعات", including ones created later) or
 * the ones it was assigned. Project::visibleTo() is the one rule the trips /
 * contracts / reports modules must filter through.
 */
class ProjectVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Project $tower;

    private Project $plaza;

    private Project $foreign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $this->company = Company::factory()->create(['prefix' => 'ALN']);
        $this->tower = Project::factory()->for($this->company)->create(['name' => 'Tower']);
        $this->plaza = Project::factory()->for($this->company)->create(['name' => 'Plaza']);
        $this->foreign = Project::factory()->create(['name' => 'Foreign']);
    }

    private function visibleNames(User $user): array
    {
        return Project::query()->visibleTo($user)->orderBy('name')->pluck('name')->all();
    }

    public function test_an_all_projects_account_sees_every_project_of_its_company_only(): void
    {
        $user = User::factory()->client($this->company)->create();

        $this->assertSame(['Plaza', 'Tower'], $this->visibleNames($user));
        $this->assertTrue($this->tower->isVisibleTo($user));
        $this->assertFalse($this->foreign->isVisibleTo($user));
    }

    public function test_an_all_projects_account_also_sees_projects_created_later(): void
    {
        $user = User::factory()->client($this->company)->create();

        Project::factory()->for($this->company)->create(['name' => 'Later']);

        $this->assertSame(['Later', 'Plaza', 'Tower'], $this->visibleNames($user));
    }

    public function test_a_specific_projects_account_sees_only_its_assigned_ones(): void
    {
        $user = User::factory()->client($this->company, 'client_project_auditor', false)->create();
        $user->projects()->attach($this->tower);

        $this->assertSame(['Tower'], $this->visibleNames($user));
        $this->assertFalse($this->plaza->isVisibleTo($user));

        // A new project of the same company is NOT picked up automatically.
        Project::factory()->for($this->company)->create(['name' => 'Later']);
        $this->assertSame(['Tower'], $this->visibleNames($user));
    }

    public function test_an_assignment_to_a_project_of_another_company_never_leaks_it(): void
    {
        $user = User::factory()->client($this->company, 'client_project_auditor', false)->create();
        // Cannot happen through the API (validated), but the rule must hold even if the data is wrong.
        $user->projects()->attach($this->foreign);

        $this->assertSame([], $this->visibleNames($user));
    }

    public function test_an_account_with_nothing_assigned_sees_nothing(): void
    {
        $user = User::factory()->client($this->company, 'client_project_manager', false)->create();

        $this->assertSame([], $this->visibleNames($user));
    }

    public function test_an_account_without_a_company_sees_nothing(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('system_admin');

        $this->assertSame([], $this->visibleNames($staff));
    }

    public function test_the_accessible_projects_helper_uses_the_same_rule(): void
    {
        $user = User::factory()->client($this->company, 'client_project_auditor', false)->create();
        $user->projects()->attach($this->plaza);

        $this->assertSame(['Plaza'], $user->accessibleProjects()->pluck('name')->all());
    }
}
