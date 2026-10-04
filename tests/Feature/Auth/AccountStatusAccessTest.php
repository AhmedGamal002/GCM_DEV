<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An account's status takes effect on its very next request (deactivated),
 * says so on every page (on vacation), and the tenant's system_admin is not
 * shown to data_entry.
 */
class AccountStatusAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $this->admin = User::factory()->create(['name' => 'The Admin', 'email' => 'admin@gcm.test']);
        $this->admin->assignRole('system_admin');
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    // ------------------------------------------------ data entry never sees the admin

    public function test_data_entry_does_not_see_the_system_admin_in_the_list(): void
    {
        $this->user('auditor', ['name' => 'An Auditor']);

        $names = collect($this->actingAs($this->user('data_entry'), 'web')->getJson('/api/v1/users')->assertOk()->json('data'))->pluck('name');

        $this->assertNotContains('The Admin', $names);
        $this->assertContains('An Auditor', $names);
    }

    public function test_data_entry_cannot_open_the_system_admin_by_id_or_search_or_export_it(): void
    {
        $dataEntry = $this->user('data_entry');

        $this->actingAs($dataEntry, 'web')->getJson("/api/v1/users/{$this->admin->id}")->assertForbidden();

        $found = $this->actingAs($dataEntry, 'web')->getJson('/api/v1/users?search=admin@gcm.test&role=system_admin')->assertOk()->json('data');
        $this->assertSame([], $found);
    }

    public function test_the_export_for_data_entry_leaves_the_system_admin_out(): void
    {
        \Maatwebsite\Excel\Facades\Excel::fake();

        $this->actingAs($this->user('data_entry'), 'web')->get('/api/v1/users/export')->assertOk();

        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('users.xlsx', function (\App\Domain\Users\Exports\UsersExport $export) {
            return $export->collection()->pluck('name')->doesntContain('The Admin');
        });
    }

    public function test_the_auditor_and_the_admin_still_see_the_system_admin_where_they_may(): void
    {
        $other = $this->user('auditor', ['name' => 'Second Auditor']);

        $names = collect($this->actingAs($other, 'web')->getJson('/api/v1/users')->assertOk()->json('data'))->pluck('name');
        $this->assertContains('The Admin', $names);
    }

    // ------------------------------------------------ deactivated while signed in

    public function test_a_session_dies_on_the_next_request_after_deactivation(): void
    {
        $user = $this->user('data_entry');

        $this->actingAs($user, 'web')->getJson('/api/v1/users')->assertOk();

        $user->forceFill(['status' => 'deactivated'])->save();

        $this->actingAs($user->fresh(), 'web')->getJson('/api/v1/users')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'account_deactivated');
    }

    public function test_a_deactivated_account_is_sent_to_the_login_page_from_a_web_page(): void
    {
        $user = $this->user('data_entry', ['status' => 'deactivated']);

        $this->actingAs($user, 'web')->get('/app/user/list')->assertRedirect(route('login', ['deactivated' => 1]));
    }

    public function test_deactivating_an_account_deletes_its_mobile_tokens(): void
    {
        $driver = $this->user('driver');
        $token = $driver->createToken('mobile');
        $this->assertSame(1, $driver->tokens()->count());

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/users/{$driver->id}/status", ['status' => 'deactivated'])
            ->assertOk();

        $this->assertSame(0, $driver->fresh()->tokens()->count());
        $this->assertNotNull($token);
    }

    public function test_a_bearer_token_stops_working_the_moment_the_account_is_deactivated(): void
    {
        $user = $this->user('data_entry');
        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password']);
        $token = $login->json('token');
        $this->assertNotNull($token);

        $user->forceFill(['status' => 'deactivated'])->save();

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/users')->assertUnauthorized();
    }

    public function test_the_login_page_explains_a_deactivated_redirect(): void
    {
        $this->withoutVite();

        $this->get('/login?deactivated=1')->assertOk()->assertSee(__('auth.deactivated'));
    }

    // ------------------------------------------------ on vacation: visible everywhere

    public function test_an_account_on_vacation_sees_the_banner_on_every_page(): void
    {
        $this->withoutVite();
        $user = $this->user('data_entry', ['status' => 'on_vacation']);

        foreach (['/dashboard', '/app/user/list', '/app/company/add', '/pages/account-settings-account'] as $page) {
            $this->actingAs($user, 'web')->get($page)->assertOk()->assertSee('gcm-vacation-banner', false);
        }
    }

    public function test_an_active_account_does_not_see_the_banner(): void
    {
        $this->withoutVite();

        $this->actingAs($this->user('data_entry'), 'web')->get('/app/user/list')->assertOk()->assertDontSee('gcm-vacation-banner', false);
    }
}
