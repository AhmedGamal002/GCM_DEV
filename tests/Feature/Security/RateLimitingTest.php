<?php

namespace Tests\Feature\Security;

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_super_admin_login_is_rate_limited(): void
    {
        PlatformAdmin::create(['name' => 'Root', 'email' => 'root@product.test', 'password' => 'right-password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/platform/login', ['email' => 'root@product.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        // Even the right password is refused once the limit is hit.
        $this->post('/platform/login', ['email' => 'root@product.test', 'password' => 'right-password'])->assertStatus(429);
        $this->assertGuest('platform');
    }

    public function test_one_ip_cannot_try_many_different_accounts(): void
    {
        // Each email has its own 5/minute counter, so only the per-IP ceiling stops credential stuffing.
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => "user{$i}@example.com", 'password' => 'guess'])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'user31@example.com', 'password' => 'guess'])->assertStatus(429);
    }

    public function test_the_api_has_a_ceiling_per_user(): void
    {
        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));
        $admin = User::factory()->create();
        $admin->assignRole('system_admin');
        $other = User::factory()->create();
        $other->assignRole('system_admin');

        config(['app.api_rate_limit' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($admin, 'web')->getJson('/api/v1/me')->assertOk();
        }
        $this->actingAs($admin, 'web')->getJson('/api/v1/me')->assertStatus(429);
    }

    public function test_exports_have_a_lower_ceiling(): void
    {
        $this->seed(RoleSeeder::class);
        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));
        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        config(['app.export_rate_limit' => 2]);

        $this->actingAs($admin, 'web')->get('/api/v1/users/export')->assertOk();
        $this->actingAs($admin, 'web')->get('/api/v1/users/export')->assertOk();
        $this->actingAs($admin, 'web')->get('/api/v1/users/export')->assertStatus(429);

        // Ordinary calls are not affected by the export counter.
        $this->actingAs($admin, 'web')->getJson('/api/v1/me')->assertOk();
    }
}
