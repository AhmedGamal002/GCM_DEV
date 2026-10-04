<?php

namespace Tests\Feature\Users;

use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The system admin may change every field of every account — the email included, the admin's
 * own account included. The one thing that never moves is the admin's role (exactly one per tenant,
 * never deactivated). data_entry has the same reach, except over the admin account.
 */
class AdminEditsEveryAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));

        $this->admin = User::factory()->create(['name' => 'The Admin', 'email' => 'admin@gcm.test', 'phone' => '+966500000900']);
        $this->admin->assignRole('system_admin');
    }

    private function staff(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['phone' => '+9665000'.random_int(10000, 99999)]);
        $user->assignRole($role);

        return $user;
    }

    private function payload(User $user, array $overrides = []): array
    {
        return array_merge(['name' => $user->name, 'phone' => $user->phone, 'roles' => [$user->getRoleNames()->first()]], $overrides);
    }

    public function test_the_admin_changes_the_email_of_a_staff_account_and_it_logs_in_with_the_new_one(): void
    {
        $user = $this->staff('data_entry', ['email' => 'old@gcm.test']);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/users/{$user->id}", $this->payload($user, ['email' => 'new@gcm.test']))
            ->assertOk()
            ->assertJsonPath('data.email', 'new@gcm.test');

        $this->assertSame('new@gcm.test', $user->fresh()->email);
        $this->postJson('/api/v1/auth/login', ['email' => 'new@gcm.test', 'password' => 'password'])->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => 'old@gcm.test', 'password' => 'password'])->assertUnprocessable();
    }

    public function test_an_email_already_used_by_another_account_is_refused(): void
    {
        $user = $this->staff('auditor');

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/users/{$user->id}", $this->payload($user, ['email' => 'admin@gcm.test']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_keeping_the_same_email_is_not_a_duplicate(): void
    {
        $user = $this->staff('auditor', ['email' => 'same@gcm.test']);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/users/{$user->id}", $this->payload($user, ['email' => 'same@gcm.test', 'name' => 'Renamed']))
            ->assertOk();
    }

    public function test_an_update_without_an_email_leaves_it_unchanged(): void
    {
        $user = $this->staff('auditor', ['email' => 'keep@gcm.test']);

        $this->actingAs($this->admin, 'web')->patchJson("/api/v1/users/{$user->id}", $this->payload($user))->assertOk();

        $this->assertSame('keep@gcm.test', $user->fresh()->email);
    }

    public function test_the_admin_changes_the_email_of_a_client_account(): void
    {
        $client = User::factory()->client(Company::factory()->create(['prefix' => 'ALN']))->create(['email' => 'c@alnoor.test', 'phone' => '+966500000801']);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/client-users/{$client->id}", [
                'name' => $client->name, 'email' => 'c2@alnoor.test', 'phone' => $client->phone, 'role' => 'client_project_manager',
                'company_id' => $client->company_id, 'projects_scope' => 'all',
            ])
            ->assertOk()
            ->assertJsonPath('data.email', 'c2@alnoor.test');
    }

    public function test_the_admin_edits_their_own_account_but_the_role_never_changes(): void
    {
        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/users/{$this->admin->id}", [
                'name' => 'Admin Renamed', 'email' => 'boss@gcm.test', 'phone' => '+966500000901',
                // Even a forged role change is ignored.
                'roles' => ['data_entry'],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Admin Renamed')
            ->assertJsonPath('data.email', 'boss@gcm.test');

        $fresh = $this->admin->fresh();
        $this->assertTrue($fresh->hasRole('system_admin'));
        $this->assertFalse($fresh->hasRole('data_entry'));
    }

    public function test_the_admin_can_edit_without_sending_any_role(): void
    {
        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/users/{$this->admin->id}", ['name' => 'No Role Sent', 'phone' => '+966500000902'])
            ->assertOk();
    }

    public function test_the_admin_still_cannot_deactivate_themselves(): void
    {
        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/users/{$this->admin->id}/status", ['status' => 'deactivated'])
            ->assertForbidden();
    }

    public function test_the_admin_sees_their_own_row_in_the_users_list(): void
    {
        $names = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/users')->assertOk()->json('data'))->pluck('name');

        $this->assertContains('The Admin', $names);
    }

    public function test_data_entry_does_not_see_their_own_row_or_the_admin(): void
    {
        $dataEntry = $this->staff('data_entry', ['name' => 'Entry Clerk']);

        $names = collect($this->actingAs($dataEntry, 'web')->getJson('/api/v1/users')->assertOk()->json('data'))->pluck('name');

        $this->assertNotContains('Entry Clerk', $names);
        $this->assertNotContains('The Admin', $names);
    }

    public function test_data_entry_can_change_a_staff_email_but_never_the_admins(): void
    {
        $dataEntry = $this->staff('data_entry');
        $target = $this->staff('auditor');

        $this->actingAs($dataEntry, 'web')
            ->patchJson("/api/v1/users/{$target->id}", $this->payload($target, ['email' => 'by-entry@gcm.test']))
            ->assertOk();

        $this->actingAs($dataEntry, 'web')
            ->patchJson("/api/v1/users/{$this->admin->id}", ['name' => 'Hijack', 'email' => 'x@x.test', 'phone' => '+966500000999'])
            ->assertForbidden();
    }
}
