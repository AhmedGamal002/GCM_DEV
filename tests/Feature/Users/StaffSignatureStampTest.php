<?php

namespace Tests\Feature\Users;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * GCM staff (system admin / data entry / auditor) carry an optional signature and
 * operational-stamp image, like a client account does. The admin sets them from
 * the generic user form; they sit on the private disk and are served only through
 * the Gate-checked download route.
 */
class StaffSignatureStampTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->seed(RoleSeeder::class);

        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Staff',
            'email' => 'staff@gcm.test',
            'phone' => '01000000001',
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
            'status' => 'active',
            'roles' => ['data_entry'],
        ], $overrides);
    }

    public function test_the_signature_and_stamp_are_optional_on_create(): void
    {
        $this->actingAs($this->actor('system_admin'), 'web')->post('/api/v1/users', $this->payload(), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.signature_url', null)
            ->assertJsonPath('data.stamp_url', null);
    }

    #[DataProvider('creators')]
    public function test_an_admin_creates_a_staff_account_with_signature_and_stamp(string $creator): void
    {
        $response = $this->actingAs($this->actor($creator), 'web')
            ->post('/api/v1/users', $this->payload([
                'signature' => UploadedFile::fake()->image('s.png'),
                'stamp' => UploadedFile::fake()->image('t.png'),
            ]), ['Accept' => 'application/json'])
            ->assertCreated();

        $user = User::findOrFail($response->json('data.id'));
        Storage::disk('local')->assertExists($user->signature_image);
        Storage::disk('local')->assertExists($user->stamp_image);
        $this->assertStringContainsString("user-signatures/{$user->id}", $user->signature_image);
        $this->assertStringContainsString("user-stamps/{$user->id}", $user->stamp_image);
        $this->assertStringContainsString("/api/v1/users/{$user->id}/images/signature", $response->json('data.signature_url'));
    }

    public static function creators(): array
    {
        return [['system_admin'], ['data_entry']];
    }

    public function test_updating_one_image_keeps_the_other_and_deletes_the_replaced_file(): void
    {
        $admin = $this->actor('system_admin');
        $staff = $this->actor('auditor');

        $this->actingAs($admin, 'web')
            ->post("/api/v1/users/{$staff->id}", $this->payload(['_method' => 'PATCH', 'email' => $staff->email, 'roles' => ['auditor'], 'signature' => UploadedFile::fake()->image('one.png'), 'stamp' => UploadedFile::fake()->image('t.png')]), ['Accept' => 'application/json'])
            ->assertOk();
        $firstSignature = $staff->fresh()->signature_image;
        $stamp = $staff->fresh()->stamp_image;

        $this->actingAs($admin, 'web')
            ->post("/api/v1/users/{$staff->id}", $this->payload(['_method' => 'PATCH', 'email' => $staff->email, 'roles' => ['auditor'], 'signature' => UploadedFile::fake()->image('two.png')]), ['Accept' => 'application/json'])
            ->assertOk();

        $staff->refresh();
        $this->assertNotSame($firstSignature, $staff->signature_image);
        Storage::disk('local')->assertMissing($firstSignature);
        $this->assertSame($stamp, $staff->stamp_image);
    }

    public function test_the_system_admin_can_set_their_own_signature_from_the_edit_form(): void
    {
        $admin = $this->actor('system_admin');

        // The edit form sends no `roles` for the system admin's own account (its role is fixed).
        $payload = $this->payload(['_method' => 'PATCH', 'email' => $admin->email, 'stamp' => UploadedFile::fake()->image('t.png')]);
        unset($payload['roles']);

        $this->actingAs($admin, 'web')
            ->post("/api/v1/users/{$admin->id}", $payload, ['Accept' => 'application/json'])
            ->assertOk();

        Storage::disk('local')->assertExists($admin->fresh()->stamp_image);
    }

    public function test_only_images_up_to_two_megabytes_are_accepted(): void
    {
        $this->actingAs($this->actor('system_admin'), 'web')
            ->post('/api/v1/users', $this->payload([
                'signature' => UploadedFile::fake()->create('s.pdf', 10, 'application/pdf'),
                'stamp' => UploadedFile::fake()->image('t.png')->size(3000),
            ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['signature', 'stamp']);
    }

    public function test_an_auditor_cannot_set_anyones_signature(): void
    {
        $staff = $this->actor('data_entry');

        $this->actingAs($this->actor('auditor'), 'web')
            ->post("/api/v1/users/{$staff->id}", $this->payload(['_method' => 'PATCH', 'signature' => UploadedFile::fake()->image('s.png')]), ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertNull($staff->fresh()->signature_image);
    }

    private function staffWithImages(string $role = 'data_entry'): User
    {
        $staff = $this->actor($role);
        $staff->forceFill([
            'signature_image' => UploadedFile::fake()->image('s.png')->store("user-signatures/{$staff->id}", 'local'),
            'stamp_image' => UploadedFile::fake()->image('t.png')->store("user-stamps/{$staff->id}", 'local'),
        ])->save();

        return $staff;
    }

    #[DataProvider('imageViewers')]
    public function test_who_can_fetch_a_staff_accounts_images(string $role, int $status): void
    {
        $staff = $this->staffWithImages();

        $this->actingAs($this->actor($role), 'web')
            ->get("/api/v1/users/{$staff->id}/images/signature")
            ->assertStatus($status);
    }

    public static function imageViewers(): array
    {
        return [['system_admin', 200], ['data_entry', 200], ['auditor', 200], ['driver', 403]];
    }

    public function test_the_owner_fetches_their_own_images(): void
    {
        $staff = $this->staffWithImages('auditor');

        $this->actingAs($staff, 'web')->get("/api/v1/users/{$staff->id}/images/stamp")->assertOk();
    }

    public function test_data_entry_cannot_fetch_the_system_admins_images(): void
    {
        $admin = $this->staffWithImages('system_admin');

        $this->actingAs($this->actor('data_entry'), 'web')->get("/api/v1/users/{$admin->id}/images/signature")->assertForbidden();
    }

    public function test_the_user_forms_have_signature_and_stamp_inputs(): void
    {
        $this->withoutVite();
        $admin = $this->actor('system_admin');

        $this->actingAs($admin, 'web')->get('/app/user/add')->assertOk()
            ->assertSee('id="signature"', false)
            ->assertSee('id="stamp"', false);

        $edit = $this->actingAs($admin, 'web')->get('/app/user/edit/'.$admin->id)->assertOk();
        foreach (['signature', 'stamp'] as $slot) {
            $edit->assertSee('id="'.$slot.'"', false)->assertSee('id="current-'.$slot.'"', false);
        }
    }
}
