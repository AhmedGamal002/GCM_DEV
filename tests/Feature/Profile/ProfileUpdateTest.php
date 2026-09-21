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

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
    }

    /**
     * FRD: the only thing changed from one's own profile page is the photo
     * — name is admin-managed, so a name-only request is rejected and a
     * name sent alongside a photo is ignored.
     */
    #[DataProvider('rolesProvider')]
    public function test_a_users_own_name_cannot_be_changed_from_their_profile(string $role): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['name' => 'Old Name']);
        $user->assignRole($role);

        $this->actingAs($user, 'web')
            ->patchJson('/api/v1/me', ['name' => 'New Name'])
            ->assertJsonValidationErrors('photo');

        $this->actingAs($user, 'web')
            ->post('/api/v1/me', ['_method' => 'PATCH', 'name' => 'Sneaky Name', 'photo' => UploadedFile::fake()->image('a.jpg')])
            ->assertOk();

        $this->assertSame('Old Name', $user->fresh()->name);
    }

    public static function rolesProvider(): array
    {
        return [
            ['system_admin'],
            ['data_entry'],
            ['auditor'],
            ['driver'],
        ];
    }

    public function test_email_cannot_be_changed_via_profile_update(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['email' => 'original@gcm.test']);
        $user->assignRole('driver');

        $this->actingAs($user, 'web')
            ->post('/api/v1/me', ['_method' => 'PATCH', 'email' => 'changed@gcm.test', 'photo' => UploadedFile::fake()->image('a.jpg')]);

        $this->assertSame('original@gcm.test', $user->fresh()->email);
    }

    /** FRD: every role can change their own profile photo from the self-service Profile page. */
    #[DataProvider('rolesProvider')]
    public function test_any_role_can_upload_their_own_profile_photo(string $role): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->assignRole($role);

        $photo = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->actingAs($user, 'web')
            ->post('/api/v1/me', ['_method' => 'PATCH', 'photo' => $photo]);

        $response->assertOk();

        $user->refresh();
        Storage::disk('public')->assertExists($user->photo);
        $this->assertStringContainsString($user->photo, $response->json('data.photo_url'));
    }
}
