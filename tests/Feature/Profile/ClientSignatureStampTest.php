<?php

namespace Tests\Feature\Profile;

use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FRD V01.14 §1.4: a client account changes its own photo, password,
 * signature image and operational-stamp image from the profile page — and
 * nobody else has a signature/stamp at all.
 */
class ClientSignatureStampTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
    }

    private function client(string $role = 'client_project_manager'): User
    {
        return User::factory()->client(Company::factory()->create(['prefix' => 'ALN']), $role)->create();
    }

    public static function clientRoles(): array
    {
        return [['client_project_manager'], ['client_project_auditor']];
    }

    #[DataProvider('clientRoles')]
    public function test_a_client_account_uploads_its_signature_and_stamp_privately(string $role): void
    {
        $client = $this->client($role);

        $this->actingAs($client, 'web')
            ->post('/api/v1/me', ['_method' => 'PATCH', 'signature' => UploadedFile::fake()->image('s.png'), 'stamp' => UploadedFile::fake()->image('t.png')])
            ->assertOk();

        $client->refresh();
        Storage::disk('local')->assertExists($client->signature_image);
        Storage::disk('local')->assertExists($client->stamp_image);
        $this->assertNull($client->photo, 'the photo was not part of this request');
    }

    public function test_either_image_alone_is_enough_and_the_other_is_kept(): void
    {
        $client = $this->client();

        $this->actingAs($client, 'web')
            ->post('/api/v1/me', ['_method' => 'PATCH', 'signature' => UploadedFile::fake()->image('s.png')])
            ->assertOk();
        $signature = $client->fresh()->signature_image;

        $this->actingAs($client, 'web')
            ->post('/api/v1/me', ['_method' => 'PATCH', 'stamp' => UploadedFile::fake()->image('t.png')])
            ->assertOk();

        $this->assertSame($signature, $client->fresh()->signature_image);
        $this->assertNotNull($client->fresh()->stamp_image);
    }

    public function test_a_new_signature_replaces_and_deletes_the_old_file(): void
    {
        $client = $this->client();

        $this->actingAs($client, 'web')->post('/api/v1/me', ['_method' => 'PATCH', 'signature' => UploadedFile::fake()->image('one.png')])->assertOk();
        $old = $client->fresh()->signature_image;

        $this->actingAs($client, 'web')->post('/api/v1/me', ['_method' => 'PATCH', 'signature' => UploadedFile::fake()->image('two.png')])->assertOk();

        Storage::disk('local')->assertMissing($old);
    }

    public function test_the_photo_still_works_for_a_client_account(): void
    {
        $client = $this->client();

        $this->actingAs($client, 'web')
            ->post('/api/v1/me', ['_method' => 'PATCH', 'photo' => UploadedFile::fake()->image('me.jpg')])
            ->assertOk();

        Storage::disk('public')->assertExists($client->fresh()->photo);
    }

    public function test_an_empty_request_is_rejected(): void
    {
        $this->actingAs($this->client(), 'web')->patchJson('/api/v1/me', [])->assertUnprocessable();
    }

    public function test_only_images_up_to_two_megabytes_are_accepted(): void
    {
        $this->actingAs($this->client(), 'web')
            ->post('/api/v1/me', ['_method' => 'PATCH', 'signature' => UploadedFile::fake()->create('s.pdf', 10, 'application/pdf'), 'stamp' => UploadedFile::fake()->image('t.png')->size(3000)], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['signature', 'stamp']);
    }

    #[DataProvider('staffRoles')]
    public function test_nobody_else_has_a_signature_or_stamp(string $role): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user, 'web')
            ->post('/api/v1/me', ['_method' => 'PATCH', 'photo' => UploadedFile::fake()->image('me.jpg'), 'signature' => UploadedFile::fake()->image('s.png')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('signature');

        $this->assertNull($user->fresh()->signature_image);
    }

    public static function staffRoles(): array
    {
        return [['system_admin'], ['data_entry'], ['auditor'], ['driver']];
    }

    public function test_the_profile_page_offers_the_signature_card_to_client_accounts_only(): void
    {
        $this->withoutVite();

        $this->actingAs($this->client(), 'web')->get('/pages/account-settings-account')->assertOk()->assertSee('formSignatureStamp', false);
    }

    public function test_the_profile_page_hides_the_signature_card_from_staff(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $this->actingAs($admin, 'web')->get('/pages/account-settings-account')->assertOk()->assertDontSee('formSignatureStamp', false);
    }

    #[DataProvider('clientRoles')]
    public function test_a_client_account_changes_its_own_password(string $role): void
    {
        $client = $this->client($role);

        $this->actingAs($client, 'web')
            ->patchJson('/api/v1/me/password', ['current_password' => 'password', 'new_password' => 'N3w!Passw0rd#', 'new_password_confirmation' => 'N3w!Passw0rd#'])
            ->assertNoContent();
    }
}
