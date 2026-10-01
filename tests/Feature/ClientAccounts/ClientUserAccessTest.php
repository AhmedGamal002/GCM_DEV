<?php

namespace Tests\Feature\ClientAccounts;

use App\Models\Company;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Who may manage / see client accounts, who can fetch their private
 * signature and stamp, and that another tenant's accounts stay invisible.
 */
class ClientUserAccessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Company $company;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->company = Company::factory()->create(['prefix' => 'ALN']);
        $this->client = User::factory()->client($this->company)->create();
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function payload(): array
    {
        return [
            'name' => 'New Client', 'email' => 'new@alnoor.test', 'phone' => '+966500000042',
            'password' => 'Str0ng!Pass#1', 'password_confirmation' => 'Str0ng!Pass#1',
            'status' => 'active', 'role' => 'client_project_manager',
            'company_id' => $this->company->id, 'projects_scope' => 'all',
        ];
    }

    #[DataProvider('managers')]
    public function test_system_admin_and_data_entry_manage_client_accounts(string $role): void
    {
        $actor = $this->actor($role);

        $id = $this->actingAs($actor, 'web')
            ->postJson('/api/v1/client-users', $this->payload())
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($actor, 'web')
            ->patchJson("/api/v1/client-users/{$id}", array_merge($this->payload(), ['name' => 'Renamed']))
            ->assertOk();

        $this->actingAs($actor, 'web')
            ->patchJson("/api/v1/users/{$id}/status", ['status' => 'deactivated'])
            ->assertOk();
    }

    public static function managers(): array
    {
        return [['system_admin'], ['data_entry']];
    }

    public function test_the_auditor_can_see_client_accounts_but_not_change_them(): void
    {
        $auditor = $this->actor('auditor');

        $this->actingAs($auditor, 'web')->getJson('/api/v1/users?affiliation=client')->assertOk();
        $this->actingAs($auditor, 'web')->getJson("/api/v1/users/{$this->client->id}")->assertOk();
        $this->actingAs($auditor, 'web')->postJson('/api/v1/client-users', $this->payload())->assertForbidden();
    }

    #[DataProvider('outsiders')]
    public function test_drivers_and_client_accounts_cannot_use_the_client_endpoints(string $role): void
    {
        $actor = $role === 'driver' ? $this->actor('driver') : User::factory()->client($this->company, $role)->create();

        $this->actingAs($actor, 'web')->postJson('/api/v1/client-users', $this->payload())->assertForbidden();
        $this->actingAs($actor, 'web')->patchJson("/api/v1/client-users/{$this->client->id}", $this->payload())->assertForbidden();
        $this->actingAs($actor, 'web')->getJson('/api/v1/users')->assertForbidden();
    }

    public static function outsiders(): array
    {
        return [['driver'], ['client_project_manager'], ['client_project_auditor']];
    }

    public function test_a_client_account_cannot_reach_any_admin_module(): void
    {
        $client = User::factory()->client($this->company)->create();

        foreach (['/api/v1/companies', '/api/v1/projects', '/api/v1/vehicles', '/api/v1/assets', '/api/v1/drivers', '/api/v1/facilities'] as $url) {
            $this->actingAs($client, 'web')->getJson($url)->assertForbidden();
        }
    }

    public function test_a_client_account_cannot_change_its_own_company_or_role(): void
    {
        $client = User::factory()->client($this->company)->create();

        $this->actingAs($client, 'web')
            ->patchJson("/api/v1/client-users/{$client->id}", $this->payload())
            ->assertForbidden();
    }

    public function test_data_entry_cannot_edit_the_system_admin_through_the_client_endpoint(): void
    {
        $admin = $this->actor('system_admin');

        $this->actingAs($this->actor('data_entry'), 'web')
            ->patchJson("/api/v1/client-users/{$admin->id}", $this->payload())
            ->assertForbidden();
    }

    public function test_another_tenants_client_accounts_are_invisible(): void
    {
        $theirs = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $theirs);
        $foreign = User::factory()->client(Company::factory()->create(['prefix' => 'ZZZ']))->create();

        app()->instance('tenant', $this->tenant);
        $admin = $this->actor('system_admin');

        $this->actingAs($admin, 'web')->getJson("/api/v1/users/{$foreign->id}")->assertNotFound();
        $this->actingAs($admin, 'web')->patchJson("/api/v1/client-users/{$foreign->id}", $this->payload())->assertNotFound();
        $this->actingAs($admin, 'web')->getJson('/api/v1/users?affiliation=client')
            ->assertOk()
            ->assertJsonMissing(['id' => $foreign->id]);
    }

    public function test_a_company_from_another_tenant_cannot_be_chosen(): void
    {
        $theirs = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $theirs);
        $foreignCompany = Company::factory()->create(['prefix' => 'ZZZ']);

        app()->instance('tenant', $this->tenant);

        $this->actingAs($this->actor('system_admin'), 'web')
            ->postJson('/api/v1/client-users', array_merge($this->payload(), ['company_id' => $foreignCompany->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_id');
    }

    // ---------------------------------------------------------- signature / stamp

    private function clientWithImages(): User
    {
        $client = User::factory()->client($this->company)->create();
        $client->forceFill([
            'signature_image' => UploadedFile::fake()->image('s.png')->store("user-signatures/{$client->id}", 'local'),
            'stamp_image' => UploadedFile::fake()->image('t.png')->store("user-stamps/{$client->id}", 'local'),
        ])->save();

        return $client;
    }

    public function test_the_signature_and_stamp_are_served_only_through_the_gate_checked_route(): void
    {
        $client = $this->clientWithImages();

        $resource = $this->actingAs($this->actor('system_admin'), 'web')->getJson("/api/v1/users/{$client->id}")->json('data');
        $this->assertStringContainsString("/api/v1/users/{$client->id}/images/signature", $resource['signature_url']);
        $this->assertStringNotContainsString('storage', $resource['stamp_url']);
    }

    #[DataProvider('imageViewers')]
    public function test_staff_who_can_view_accounts_can_fetch_the_images(string $role, string $type): void
    {
        $client = $this->clientWithImages();

        $this->actingAs($this->actor($role), 'web')
            ->get("/api/v1/users/{$client->id}/images/{$type}")
            ->assertOk();
    }

    public static function imageViewers(): array
    {
        return [
            ['system_admin', 'signature'], ['data_entry', 'stamp'], ['auditor', 'signature'],
        ];
    }

    public function test_the_owner_can_fetch_their_own_images(): void
    {
        $client = $this->clientWithImages();

        $this->actingAs($client, 'web')->get("/api/v1/users/{$client->id}/images/signature")->assertOk();
    }

    public function test_another_client_account_and_drivers_cannot_fetch_the_images(): void
    {
        $client = $this->clientWithImages();
        $colleague = User::factory()->client($this->company)->create();

        $this->actingAs($colleague, 'web')->get("/api/v1/users/{$client->id}/images/signature")->assertForbidden();
    }

    public function test_a_driver_cannot_fetch_the_images(): void
    {
        $client = $this->clientWithImages();

        $this->actingAs($this->actor('driver'), 'web')->get("/api/v1/users/{$client->id}/images/stamp")->assertForbidden();
    }

    public function test_missing_image_or_unknown_type_is_404(): void
    {
        $admin = $this->actor('system_admin');

        $this->actingAs($admin, 'web')->get("/api/v1/users/{$this->client->id}/images/signature")->assertNotFound();
        $this->actingAs($admin, 'web')->get("/api/v1/users/{$this->client->id}/images/passport")->assertNotFound();
    }

    // ---------------------------------------------------------- menu

    public function test_client_accounts_are_offered_no_admin_menu_entries(): void
    {
        $this->withoutVite();

        $html = $this->actingAs(User::factory()->client($this->company)->create(), 'web')->get('/dashboard')->assertOk()->getContent();

        foreach (['app/user/list', 'app/company/list', 'app/project/list', 'app/vehicle/list', 'app/asset/list'] as $path) {
            $this->assertStringNotContainsString($path, $html);
        }
    }

    /** Guards the test above against passing vacuously (a menu that never renders any entry). */
    public function test_the_system_admin_does_see_those_menu_entries(): void
    {
        $this->withoutVite();

        $html = $this->actingAs($this->actor('system_admin'), 'web')->get('/dashboard')->assertOk()->getContent();

        foreach (['app/user/list', 'app/company/list', 'app/project/list'] as $path) {
            $this->assertStringContainsString($path, $html);
        }
    }

    public function test_the_user_pages_render_for_a_manager(): void
    {
        $this->withoutVite();
        $admin = $this->actor('system_admin');

        $this->actingAs($admin, 'web')->get('/app/client-user/add')->assertOk()->assertSee('clientUserForm', false);
        $this->actingAs($admin, 'web')->get("/app/client-user/edit/{$this->client->id}")->assertOk()->assertSee('clientUserForm', false);
        $this->actingAs($admin, 'web')->get('/app/user/list')->assertOk();
        $this->actingAs($admin, 'web')->get("/app/user/view/{$this->client->id}")->assertOk();
    }

    public function test_project_ids_are_not_required_when_all_projects(): void
    {
        $project = Project::factory()->for($this->company)->create();

        $this->actingAs($this->actor('system_admin'), 'web')
            ->postJson('/api/v1/client-users', array_merge($this->payload(), ['project_ids' => [$project->id]]))
            ->assertCreated();

        // "All projects" ignores any ticked list rather than storing it.
        $this->assertSame(0, User::where('email', 'new@alnoor.test')->firstOrFail()->projects()->count());
    }
}
