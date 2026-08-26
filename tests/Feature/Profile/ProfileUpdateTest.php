<?php

namespace Tests\Feature\Profile;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    #[DataProvider('rolesProvider')]
    public function test_any_role_can_update_their_own_name(string $role): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        $user->assignRole($role);

        $response = $this->actingAs($user, 'web')
            ->patchJson('/api/v1/me', ['name' => 'New Name']);

        $response->assertOk();
        $this->assertSame('New Name', $user->fresh()->name);
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
        $user = User::factory()->create(['email' => 'original@gcm.test']);
        $user->assignRole('driver');

        $this->actingAs($user, 'web')
            ->patchJson('/api/v1/me', ['name' => 'New Name', 'email' => 'changed@gcm.test']);

        $this->assertSame('original@gcm.test', $user->fresh()->email);
    }
}
