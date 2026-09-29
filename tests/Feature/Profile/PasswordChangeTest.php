<?php

namespace Tests\Feature\Profile;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Every role changes its own password from the profile page (User::
 * canChangeOwnPassword()). The FRD only names the auditor, the driver and
 * the client/contractor roles; the client asked for system_admin and
 * data_entry to have it as well — see the docblock on that method.
 */
class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create(['password' => bcrypt('old-password')]);
        $user->assignRole($role);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'current_password' => 'old-password',
            'new_password' => 'brand-new-password',
            'new_password_confirmation' => 'brand-new-password',
        ], $overrides);
    }

    public static function everyRoleProvider(): array
    {
        return [
            ['system_admin'],
            ['data_entry'],
            ['auditor'],
            ['driver'],
            ['contractor_user'],
        ];
    }

    #[DataProvider('everyRoleProvider')]
    public function test_every_role_changes_its_own_password(string $role): void
    {
        $user = $this->userWithRole($role);

        $this->actingAs($user, 'web')
            ->patchJson('/api/v1/me/password', $this->payload())
            ->assertNoContent();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    /**
     * The password card and the account page are ONE page now (used to be a
     * separate "Security" tab/route) — the old URL just redirects there.
     */
    #[DataProvider('everyRoleProvider')]
    public function test_the_old_security_url_redirects_to_the_profile_page(string $role): void
    {
        $this->actingAs($this->userWithRole($role), 'web')
            ->get('/pages/account-settings-security')
            ->assertRedirect('/pages/account-settings-account');
    }

    #[DataProvider('everyRoleProvider')]
    public function test_the_profile_page_shows_the_password_card_to_every_role(string $role): void
    {
        $this->actingAs($this->userWithRole($role), 'web')
            ->get('/pages/account-settings-account')
            ->assertOk()
            ->assertSee('id="formPasswordChange"', false);
    }

    public function test_changing_your_own_password_does_not_touch_anyone_elses(): void
    {
        $admin = $this->userWithRole('system_admin');
        $other = $this->userWithRole('data_entry');

        $this->actingAs($admin, 'web')
            ->patchJson('/api/v1/me/password', $this->payload())
            ->assertNoContent();

        $this->assertTrue(Hash::check('old-password', $other->fresh()->password));
    }

    public function test_password_change_rejected_with_wrong_current_password(): void
    {
        $user = $this->userWithRole('system_admin');

        $this->actingAs($user, 'web')
            ->patchJson('/api/v1/me/password', $this->payload(['current_password' => 'totally-wrong']))
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_password_change_rejected_when_confirmation_does_not_match(): void
    {
        $user = $this->userWithRole('data_entry');

        $this->actingAs($user, 'web')
            ->patchJson('/api/v1/me/password', $this->payload(['new_password_confirmation' => 'does-not-match']))
            ->assertJsonValidationErrors('new_password');
    }

    public function test_guests_cannot_change_a_password(): void
    {
        $this->patchJson('/api/v1/me/password', $this->payload())->assertUnauthorized();
    }
}
