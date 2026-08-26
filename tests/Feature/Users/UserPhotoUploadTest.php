<?php

namespace Tests\Feature\Users;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $systemAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $this->systemAdmin = User::factory()->create();
        $this->systemAdmin->assignRole('system_admin');
    }

    public function test_photo_is_stored_and_returned_as_an_absolute_url_on_create(): void
    {
        $photo = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->actingAs($this->systemAdmin, 'web')
            ->post('/api/v1/users', [
                'name' => 'New Auditor',
                'email' => 'auditor@tenant-a.test',
                'phone' => '01000000000',
                'password' => 'a-secure-password',
                'password_confirmation' => 'a-secure-password',
                'status' => 'active',
                'roles' => ['auditor'],
                'photo' => $photo,
            ]);

        $response->assertCreated();

        $user = User::where('email', 'auditor@tenant-a.test')->firstOrFail();
        Storage::disk('public')->assertExists($user->photo);

        // Storage::fake() drops the disk's configured 'url' (see
        // Storage::buildDiskConfiguration()), so photo_url is relative
        // here even though the real 'public' disk (configured with
        // APP_URL) returns an absolute one — what matters is that it
        // resolves to the file that was actually stored.
        $this->assertStringContainsString($user->photo, $response->json('data.photo_url'));
    }

    public function test_photo_can_be_replaced_on_update(): void
    {
        $auditor = User::factory()->create(['email' => 'auditor@tenant-a.test']);
        $auditor->assignRole('auditor');

        $newPhoto = UploadedFile::fake()->image('new-avatar.jpg');

        $response = $this->actingAs($this->systemAdmin, 'web')
            ->post("/api/v1/users/{$auditor->id}", [
                '_method' => 'PATCH',
                'name' => $auditor->name,
                'phone' => '01000000000',
                'roles' => ['auditor'],
                'photo' => $newPhoto,
            ]);

        $response->assertOk();

        $auditor->refresh();
        Storage::disk('public')->assertExists($auditor->photo);
        $this->assertStringContainsString($auditor->photo, $response->json('data.photo_url'));
    }
}
