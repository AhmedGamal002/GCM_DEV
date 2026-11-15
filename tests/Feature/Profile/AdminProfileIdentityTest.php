<?php

namespace Tests\Feature\Profile;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Client request: the System Admin edits their OWN name and email from the profile page. Every other role's stay
 * admin-managed (a name sent along with a photo is ignored, as before — see ProfileUpdateTest).
 */
class AdminProfileIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));
    }

    private function admin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('system_admin');

        return $user;
    }

    public function test_the_admin_changes_their_own_name_and_email_without_a_photo(): void
    {
        $admin = $this->admin(['name' => 'Old Name', 'email' => 'old@gcm.test']);

        $this->actingAs($admin, 'web')
            ->patchJson('/api/v1/me', ['name' => 'New Name', 'email' => 'new@gcm.test'])
            ->assertOk();

        $admin->refresh();
        $this->assertSame('New Name', $admin->name);
        $this->assertSame('new@gcm.test', $admin->email);
    }

    public function test_the_admin_can_change_only_the_name_or_only_the_email(): void
    {
        $admin = $this->admin(['name' => 'Old Name', 'email' => 'old@gcm.test']);

        $this->actingAs($admin, 'web')->patchJson('/api/v1/me', ['name' => 'Only Name'])->assertOk();
        $this->assertSame(['Only Name', 'old@gcm.test'], [$admin->fresh()->name, $admin->fresh()->email]);

        $this->actingAs($admin, 'web')->patchJson('/api/v1/me', ['email' => 'only@gcm.test'])->assertOk();
        $this->assertSame(['Only Name', 'only@gcm.test'], [$admin->fresh()->name, $admin->fresh()->email]);
    }

    public function test_the_admin_can_still_send_a_photo_with_them(): void
    {
        Storage::fake('public');
        $admin = $this->admin(['name' => 'Old Name']);

        $this->actingAs($admin, 'web')
            ->post('/api/v1/me', ['_method' => 'PATCH', 'name' => 'With Photo', 'photo' => UploadedFile::fake()->image('a.jpg')])
            ->assertOk();

        $this->assertSame('With Photo', $admin->fresh()->name);
        $this->assertNotNull($admin->fresh()->photo);
    }

    public function test_an_email_another_account_uses_is_refused_but_their_own_is_fine(): void
    {
        $admin = $this->admin(['email' => 'admin@gcm.test']);
        User::factory()->create(['email' => 'taken@gcm.test']);

        $this->actingAs($admin, 'web')->patchJson('/api/v1/me', ['email' => 'taken@gcm.test'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        // sending their own current email back (the form always sends both) is not a duplicate
        $this->actingAs($admin, 'web')->patchJson('/api/v1/me', ['name' => 'Same Email', 'email' => 'admin@gcm.test'])->assertOk();
    }

    public function test_the_email_must_be_unique_across_every_tenant_because_login_looks_it_up_globally(): void
    {
        $admin = $this->admin(['email' => 'admin@gcm.test']);

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        User::factory()->create(['email' => 'theirs@other.test']);
        app()->instance('tenant', Tenant::where('slug', 'gcm')->first());

        $this->actingAs($admin, 'web')->patchJson('/api/v1/me', ['email' => 'theirs@other.test'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_the_name_and_email_must_be_valid_and_not_empty(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->patchJson('/api/v1/me', ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->actingAs($admin, 'web')->patchJson('/api/v1/me', ['email' => 'not-an-email'])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_an_empty_request_is_still_refused(): void
    {
        $this->actingAs($this->admin(), 'web')->patchJson('/api/v1/me', [])->assertUnprocessable();
    }

    public function test_changing_the_email_keeps_the_admins_token_working(): void
    {
        $admin = $this->admin(['email' => 'old@gcm.test']);
        $token = $admin->createToken('postman')->plainTextToken;

        $this->withToken($token)->patchJson('/api/v1/me', ['email' => 'renewed@gcm.test'])->assertOk();

        // only a password change revokes access
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $this->assertSame('renewed@gcm.test', $admin->fresh()->email);
    }

    #[DataProvider('otherRoles')]
    public function test_every_other_role_still_cannot_change_their_name_or_email(string $role): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@gcm.test']);
        $user->assignRole($role);

        // a name or an email alone is still "nothing to update"
        $this->actingAs($user, 'web')->patchJson('/api/v1/me', ['name' => 'New Name'])->assertJsonValidationErrors('photo');
        $this->actingAs($user, 'web')->patchJson('/api/v1/me', ['email' => 'new@gcm.test'])->assertJsonValidationErrors('photo');

        // sent alongside a photo they are ignored
        $this->actingAs($user, 'web')
            ->post('/api/v1/me', ['_method' => 'PATCH', 'name' => 'Sneaky', 'email' => 'sneaky@gcm.test', 'photo' => UploadedFile::fake()->image('a.jpg')])
            ->assertOk();

        $this->assertSame(['Old Name', 'old@gcm.test'], [$user->fresh()->name, $user->fresh()->email]);
    }

    public static function otherRoles(): array
    {
        return [['data_entry'], ['auditor'], ['driver']];
    }

    public function test_the_admins_profile_page_has_editable_name_and_email_and_the_others_stay_read_only(): void
    {
        $this->withoutVite();

        $this->actingAs($this->admin(), 'web')->get('/pages/account-settings-account')->assertOk()
            ->assertDontSee('managed by your administrator', false)
            ->assertSee('Save changes');

        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');
        $this->actingAs($auditor, 'web')->get('/pages/account-settings-account')->assertOk()
            ->assertSee('managed by your administrator', false)
            ->assertSee('Save photo');
    }
}
