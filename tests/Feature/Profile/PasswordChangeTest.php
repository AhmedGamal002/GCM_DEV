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
 * FRD V01.09: GCM staff and drivers can't change their own password (an
 * admin sets it); client/contractor users can, from their profile page.
 * Those roles don't exist until Week 4-5 (RoleSeeder), so that "allowed"
 * half is covered with a contractor_user created here.
 *
 * FRD V01.14 (changed): auditor ("لا يملك المراقب تعديل أي بيانات من خلال
 * صفحة الملف الشخصي الا صورته او كلمة المرور") and driver (edit-account
 * section: "الصورة الشخصية" + "كلمة المرور") get self-service password
 * change too now — data_entry/system_admin stay exactly as before. The gate
 * itself is User::canChangeOwnPassword().
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

    #[DataProvider('adminRolesProvider')]
    public function test_data_entry_and_system_admin_cannot_change_their_own_password(string $role): void
    {
        $user = $this->userWithRole($role);

        $this->actingAs($user, 'web')
            ->patchJson('/api/v1/me/password', $this->payload())
            ->assertForbidden();

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    #[DataProvider('adminRolesProvider')]
    public function test_the_security_page_is_not_available_to_data_entry_and_system_admin(string $role): void
    {
        $this->actingAs($this->userWithRole($role), 'web')
            ->get('/pages/account-settings-security')
            ->assertForbidden();
    }

    public static function adminRolesProvider(): array
    {
        return [
            ['system_admin'],
            ['data_entry'],
        ];
    }

    public function test_the_profile_page_hides_the_security_tab_for_data_entry(): void
    {
        $this->actingAs($this->userWithRole('data_entry'), 'web')
            ->get('/pages/account-settings-account')
            ->assertOk()
            ->assertDontSee('account-settings-security', false);
    }

    /** FRD V01.14: auditor is the one GCM-staff exception — self-service password change, same as client/contractor roles. */
    public function test_an_auditor_changes_their_own_password(): void
    {
        $user = $this->userWithRole('auditor');

        $this->actingAs($user, 'web')
            ->patchJson('/api/v1/me/password', $this->payload())
            ->assertNoContent();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_the_security_page_is_available_to_an_auditor(): void
    {
        $this->actingAs($this->userWithRole('auditor'), 'web')
            ->get('/pages/account-settings-security')
            ->assertOk();
    }

    public function test_the_profile_page_shows_the_security_tab_for_an_auditor(): void
    {
        $this->actingAs($this->userWithRole('auditor'), 'web')
            ->get('/pages/account-settings-account')
            ->assertOk()
            ->assertSee('account-settings-security', false);
    }

    /** FRD V01.14: the driver's edit-account section lists the photo AND the password as self-service. */
    public function test_a_driver_changes_their_own_password(): void
    {
        $user = $this->userWithRole('driver');

        $this->actingAs($user, 'web')
            ->patchJson('/api/v1/me/password', $this->payload())
            ->assertNoContent();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_the_security_page_is_available_to_a_driver(): void
    {
        $this->actingAs($this->userWithRole('driver'), 'web')
            ->get('/pages/account-settings-security')
            ->assertOk();
    }

    public function test_a_contractor_user_changes_their_password_with_the_correct_current_password(): void
    {
        $user = $this->userWithRole('contractor_user');

        $this->actingAs($user, 'web')
            ->patchJson('/api/v1/me/password', $this->payload())
            ->assertNoContent();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_password_change_rejected_with_wrong_current_password(): void
    {
        $user = $this->userWithRole('contractor_user');

        $this->actingAs($user, 'web')
            ->patchJson('/api/v1/me/password', $this->payload(['current_password' => 'totally-wrong']))
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_password_change_rejected_when_confirmation_does_not_match(): void
    {
        $user = $this->userWithRole('contractor_user');

        $this->actingAs($user, 'web')
            ->patchJson('/api/v1/me/password', $this->payload(['new_password_confirmation' => 'does-not-match']))
            ->assertJsonValidationErrors('new_password');
    }
}
