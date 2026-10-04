<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FRD: an "on vacation" account "loses any ability to interact... can
 * browse only the sections allowed to them" but "can still log in and
 * just view". Enforced centrally in EnsureTenant (see its docblock) so it
 * covers both the API (auth:sanctum+tenant) and tenant web routes
 * (auth+tenant) without duplicating the check per-controller.
 *
 * Uses a real bearer token (not actingAs()) — actingAs() bypasses the
 * actual guard/middleware pipeline entirely, which would make this
 * middleware-level check pass trivially without proving anything (see
 * BearerTokenTenantAccessTest's docblock for the historical bug that
 * taught us this).
 */
class OnVacationReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function tokenFor(User $user, string $password = 'secret-password'): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertOk()->assertJsonStructure(['token']);

        return $response->json('token');
    }

    public function test_on_vacation_user_can_still_log_in_and_read(): void
    {
        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
        $user = User::factory()->create(['password' => bcrypt('secret-password'), 'status' => 'on_vacation']);
        $user->assignRole('system_admin');
        app()->forgetInstance('tenant');

        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/users');

        $response->assertOk();
    }

    public function test_on_vacation_user_cannot_perform_a_write_request(): void
    {
        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
        $user = User::factory()->create(['password' => bcrypt('secret-password'), 'status' => 'on_vacation']);
        $user->assignRole('system_admin');
        app()->forgetInstance('tenant');

        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/vehicle-categories', [
                'name_en' => 'Test Category',
                'name_ar' => 'تصنيف تجريبي',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('vehicle_categories', ['name_en' => 'Test Category']);
    }

    public function test_active_user_can_still_perform_a_write_request(): void
    {
        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
        $user = User::factory()->create(['password' => bcrypt('secret-password'), 'status' => 'active']);
        $user->assignRole('system_admin');
        app()->forgetInstance('tenant');

        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/vehicle-categories', [
                'name_en' => 'Test Category',
                'name_ar' => 'تصنيف تجريبي',
            ]);

        $response->assertCreated();
    }
}
