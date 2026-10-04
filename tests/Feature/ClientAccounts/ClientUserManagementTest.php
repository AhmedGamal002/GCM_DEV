<?php

namespace Tests\Feature\ClientAccounts;

use App\Models\Company;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FRD V01.14 §1.4 — client accounts (Project Manager / Project Auditor):
 * create / view / edit through /api/v1/client-users.
 */
class ClientUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Company $company;

    private Project $tower;

    private Project $plaza;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->admin = User::factory()->create(['email' => 'admin@gcm.test']);
        $this->admin->assignRole('system_admin');

        $this->company = Company::factory()->create(['name' => 'Al Noor', 'prefix' => 'ALN']);
        $this->tower = Project::factory()->for($this->company)->create(['name' => 'Tower']);
        $this->plaza = Project::factory()->for($this->company)->create(['name' => 'Plaza']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sara Client',
            'email' => 'sara@alnoor.test',
            'phone' => '+966500000001',
            'password' => 'Str0ng!Pass#1',
            'password_confirmation' => 'Str0ng!Pass#1',
            'status' => 'active',
            'role' => 'client_project_manager',
            'company_id' => $this->company->id,
            'projects_scope' => 'all',
        ], $overrides);
    }

    private function createClient(array $overrides = []): User
    {
        $id = $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/client-users', $this->payload($overrides))
            ->assertCreated()
            ->json('data.id');

        return User::findOrFail($id);
    }

    public function test_an_account_with_access_to_all_projects_is_created(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/client-users', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Sara Client')
            ->assertJsonPath('data.affiliation', 'client')
            ->assertJsonPath('data.roles.0', 'client_project_manager')
            ->assertJsonPath('data.company.id', $this->company->id)
            ->assertJsonPath('data.entity_name', 'Al Noor')
            ->assertJsonPath('data.projects_scope', 'all');

        $user = User::where('email', 'sara@alnoor.test')->firstOrFail();
        $this->assertSame('client', $user->affiliation);
        $this->assertSame($this->company->id, $user->company_id);
        $this->assertTrue($user->all_projects);
        $this->assertSame(0, $user->projects()->count());
        $this->assertSame($this->admin->id, $user->updated_by);
        $this->assertNotNull($user->code);
        $this->assertTrue($user->hasRole('client_project_manager'));
        $this->assertTrue(Hash::check('Str0ng!Pass#1', $user->password));
    }

    public function test_an_account_can_be_limited_to_specific_projects(): void
    {
        $user = $this->createClient([
            'role' => 'client_project_auditor',
            'projects_scope' => 'specific',
            'project_ids' => [$this->plaza->id],
        ]);

        $this->assertFalse($user->all_projects);
        $this->assertSame([$this->plaza->id], $user->projects()->pluck('projects.id')->all());
        $this->assertTrue($user->hasRole('client_project_auditor'));

        $this->actingAs($this->admin, 'web')
            ->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.projects_scope', 'specific')
            ->assertJsonPath('data.projects.0.name', 'Plaza')
            ->assertJsonCount(1, 'data.projects');
    }

    public function test_specific_projects_need_at_least_one_project(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/client-users', $this->payload(['projects_scope' => 'specific']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_ids');

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/client-users', $this->payload(['projects_scope' => 'specific', 'project_ids' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_ids');
    }

    public function test_projects_must_belong_to_the_chosen_company_and_be_active(): void
    {
        $foreign = Project::factory()->create(['name' => 'Foreign']);
        $inactive = Project::factory()->for($this->company)->deactivated()->create(['name' => 'Closed']);

        foreach ([$foreign, $inactive] as $bad) {
            $this->actingAs($this->admin, 'web')
                ->postJson('/api/v1/client-users', $this->payload(['projects_scope' => 'specific', 'project_ids' => [$this->tower->id, $bad->id]]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('project_ids');
        }

        $this->assertSame(0, User::where('affiliation', 'client')->count());
    }

    public function test_only_an_active_company_can_be_chosen_on_create(): void
    {
        $closed = Company::factory()->deactivated()->create(['prefix' => 'OLD']);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/client-users', $this->payload(['company_id' => $closed->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_id');
    }

    public function test_a_gcm_role_cannot_be_created_through_this_endpoint(): void
    {
        foreach (['data_entry', 'auditor', 'system_admin', 'driver'] as $role) {
            $this->actingAs($this->admin, 'web')
                ->postJson('/api/v1/client-users', $this->payload(['role' => $role]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('role');
        }
    }

    public function test_a_client_role_cannot_be_created_through_the_generic_users_endpoint(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/users', [
                'name' => 'X', 'email' => 'x@test', 'phone' => '+966500000009',
                'password' => 'Str0ng!Pass#1', 'password_confirmation' => 'Str0ng!Pass#1',
                'status' => 'active', 'roles' => ['client_project_manager'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('roles.0');
    }

    public function test_email_and_phone_must_be_unique(): void
    {
        $this->createClient();

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/client-users', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone']);
    }

    public function test_the_photo_is_public_but_the_signature_and_stamp_are_private(): void
    {
        $user = $this->actingAs($this->admin, 'web')
            ->post('/api/v1/client-users', $this->payload([
                'photo' => UploadedFile::fake()->image('me.jpg'),
                'signature' => UploadedFile::fake()->image('sig.png'),
                'stamp' => UploadedFile::fake()->image('stamp.png'),
            ]))
            ->assertCreated();

        $user = User::findOrFail($user->json('data.id'));

        Storage::disk('public')->assertExists($user->photo);
        Storage::disk('local')->assertExists($user->signature_image);
        Storage::disk('local')->assertExists($user->stamp_image);
        Storage::disk('public')->assertMissing($user->signature_image);
        $this->assertStringContainsString("user-signatures/{$user->id}", $user->signature_image);
    }

    public function test_the_images_must_be_images_within_the_size_limit(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/client-users', $this->payload([
                'signature' => UploadedFile::fake()->create('sig.pdf', 10, 'application/pdf'),
                'stamp' => UploadedFile::fake()->image('stamp.png')->size(3000),
            ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['signature', 'stamp']);
    }

    public function test_update_changes_every_field_except_the_email(): void
    {
        $user = $this->createClient();
        $other = Company::factory()->create(['name' => 'Gulf', 'prefix' => 'GPC']);
        $otherProject = Project::factory()->for($other)->create(['name' => 'Plant']);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/client-users/{$user->id}", $this->payload([
                'name' => 'Sara Renamed',
                'email' => 'hacker@evil.test',
                'phone' => '+966500000077',
                'password' => '',
                'password_confirmation' => '',
                'role' => 'client_project_auditor',
                'company_id' => $other->id,
                'projects_scope' => 'specific',
                'project_ids' => [$otherProject->id],
            ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Sara Renamed')
            ->assertJsonPath('data.company.id', $other->id)
            ->assertJsonPath('data.roles.0', 'client_project_auditor')
            ->assertJsonPath('data.projects_scope', 'specific');

        $user->refresh();
        $this->assertSame('sara@alnoor.test', $user->email);
        $this->assertSame('+966500000077', $user->phone);
        $this->assertTrue(Hash::check('Str0ng!Pass#1', $user->password), 'a blank password keeps the current one');
        $this->assertSame([$otherProject->id], $user->projects()->pluck('projects.id')->all());
        $this->assertFalse($user->hasRole('client_project_manager'));
    }

    public function test_switching_back_to_all_projects_clears_the_specific_list(): void
    {
        $user = $this->createClient(['projects_scope' => 'specific', 'project_ids' => [$this->tower->id, $this->plaza->id]]);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/client-users/{$user->id}", $this->payload(['projects_scope' => 'all']))
            ->assertOk();

        $this->assertTrue($user->fresh()->all_projects);
        $this->assertSame(0, $user->projects()->count());
    }

    public function test_an_already_assigned_project_stays_valid_after_it_is_deactivated(): void
    {
        $user = $this->createClient(['projects_scope' => 'specific', 'project_ids' => [$this->tower->id]]);
        $this->tower->forceFill(['operational_status' => 'deactivated'])->save();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/client-users/{$user->id}", $this->payload(['name' => 'Renamed', 'projects_scope' => 'specific', 'project_ids' => [$this->tower->id]]))
            ->assertOk();

        $this->assertSame([$this->tower->id], $user->projects()->pluck('projects.id')->all());
    }

    public function test_a_replaced_signature_is_deleted_from_disk(): void
    {
        $user = $this->createClient();

        $this->actingAs($this->admin, 'web')
            ->post("/api/v1/client-users/{$user->id}", array_merge($this->payload(), ['_method' => 'PATCH', 'signature' => UploadedFile::fake()->image('one.png')]))
            ->assertOk();
        $first = $user->fresh()->signature_image;

        $this->actingAs($this->admin, 'web')
            ->post("/api/v1/client-users/{$user->id}", array_merge($this->payload(), ['_method' => 'PATCH', 'signature' => UploadedFile::fake()->image('two.png')]))
            ->assertOk();

        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($user->fresh()->signature_image);
    }

    public function test_the_generic_edit_endpoint_refuses_a_client_account(): void
    {
        $user = $this->createClient();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/users/{$user->id}", ['name' => 'Sneaky', 'phone' => '+966500000055', 'roles' => ['data_entry']])
            ->assertForbidden();

        $this->assertTrue($user->fresh()->hasRole('client_project_manager'));
    }

    public function test_the_client_endpoint_refuses_a_non_client_account(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('data_entry');

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/client-users/{$staff->id}", $this->payload())
            ->assertForbidden();
    }

    public function test_status_goes_through_the_shared_status_endpoint(): void
    {
        $user = $this->createClient();

        foreach (['on_vacation', 'deactivated', 'active'] as $status) {
            $this->actingAs($this->admin, 'web')
                ->patchJson("/api/v1/users/{$user->id}/status", ['status' => $status])
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }
    }

    public function test_a_deactivated_client_cannot_log_in(): void
    {
        $user = $this->createClient(['status' => 'deactivated']);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Str0ng!Pass#1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_the_users_list_shows_the_company_and_filters_by_affiliation_company_and_project(): void
    {
        $other = Company::factory()->create(['name' => 'Gulf', 'prefix' => 'GPC']);
        $otherProject = Project::factory()->for($other)->create();

        $all = $this->createClient(['name' => 'All Projects', 'email' => 'all@a.test', 'phone' => '+966500000011']);
        $tower = $this->createClient(['name' => 'Tower Only', 'email' => 'tower@a.test', 'phone' => '+966500000012', 'projects_scope' => 'specific', 'project_ids' => [$this->tower->id]]);
        $gulf = $this->createClient(['name' => 'Gulf User', 'email' => 'gulf@a.test', 'phone' => '+966500000013', 'company_id' => $other->id, 'projects_scope' => 'specific', 'project_ids' => [$otherProject->id]]);

        $names = fn (array $q) => collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/users?'.http_build_query($q))->assertOk()->json('data'))->pluck('name')->sort()->values()->all();

        $this->assertSame(['All Projects', 'Gulf User', 'Tower Only'], $names(['affiliation' => 'client']));
        $this->assertSame(['All Projects', 'Tower Only'], $names(['company_id' => $this->company->id]));
        // The accounts that can see a project: "all projects" holders plus the assigned ones.
        $this->assertSame(['All Projects', 'Tower Only'], $names(['project_id' => $this->tower->id]));
        $this->assertSame(['All Projects'], $names(['project_id' => $this->plaza->id]));
        $this->assertSame(['Gulf User'], $names(['project_id' => $otherProject->id]));

        $row = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/users?affiliation=client&search=Gulf')->json('data'))->first();
        $this->assertSame('Gulf', $row['entity_name']);
        $this->assertSame('client', $row['affiliation']);
    }

    public function test_the_users_export_shows_the_company_as_the_entity(): void
    {
        $this->createClient();

        \Maatwebsite\Excel\Facades\Excel::fake();

        $this->actingAs($this->admin, 'web')->get('/api/v1/users/export?affiliation=client')->assertOk();

        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('users.xlsx', function (\App\Domain\Users\Exports\UsersExport $export) {
            $row = $export->collection()->map(fn ($u) => $export->map($u))->first();

            return $row[3] === 'Al Noor' && $row[4] === 'client_project_manager';
        });
    }
}
