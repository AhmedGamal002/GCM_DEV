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

    /** Every list export (Excel and PDF downloads are GETs under /api/v1/<module>/export). */
    private const EXPORTS = [
        'users', 'drivers', 'vehicles', 'vehicle-categories', 'assets', 'asset-capacity-categories',
        'facilities', 'main-services', 'contractors', 'companies', 'projects',
    ];

    private function userWithStatus(string $status, string $role = 'system_admin'): User
    {
        app()->instance('tenant', Tenant::first() ?? Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));
        $user = User::factory()->create(['status' => $status]);
        $user->assignRole($role);

        return $user;
    }

    public function test_an_on_vacation_account_cannot_export_anything_in_any_format(): void
    {
        $user = $this->userWithStatus('on_vacation');

        foreach (self::EXPORTS as $module) {
            foreach (['xlsx', 'pdf'] as $format) {
                $this->actingAs($user, 'web')->getJson("/api/v1/{$module}/export?format={$format}")
                    ->assertForbidden()
                    ->assertJsonPath('message', 'Your account is on vacation — you can view data but not export or make changes.');
            }
        }
    }

    public function test_an_on_vacation_data_entry_account_cannot_export_either(): void
    {
        $user = $this->userWithStatus('on_vacation', 'data_entry');

        $this->actingAs($user, 'web')->getJson('/api/v1/vehicles/export?format=xlsx')->assertForbidden();
    }

    public function test_an_on_vacation_account_cannot_export_with_a_token_either(): void
    {
        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
        $user = User::factory()->create(['password' => bcrypt('secret-password'), 'status' => 'on_vacation']);
        $user->assignRole('system_admin');
        app()->forgetInstance('tenant');

        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/drivers/export?format=xlsx')->assertForbidden();
        // Reading the list itself is still fine.
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/drivers')->assertOk();
    }

    public function test_an_active_account_can_still_export(): void
    {
        \Maatwebsite\Excel\Facades\Excel::fake();
        $user = $this->userWithStatus('active');

        foreach (self::EXPORTS as $module) {
            $this->actingAs($user, 'web')->get("/api/v1/{$module}/export?format=xlsx")->assertOk();
        }
    }

    public function test_the_export_button_is_locked_and_the_banner_says_so_for_an_on_vacation_account(): void
    {
        $this->withoutVite();
        $user = $this->userWithStatus('on_vacation');

        $this->actingAs($user, 'web')->get('/app/vehicle/list')->assertOk()
            ->assertSee('You can only view data. You cannot export, create, edit or change anything', false)
            ->assertDontSee('You can view and export data', false);
    }
}
