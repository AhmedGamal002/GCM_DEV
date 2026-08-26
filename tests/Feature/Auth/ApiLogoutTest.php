<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ApiLogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
    }

    public function test_web_session_login_can_be_logged_out(): void
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');

        $this->withHeaders(['Referer' => 'http://localhost'])
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertTrue(Auth::guard('web')->check());

        $this->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_mobile_token_can_be_revoked(): void
    {
        $user = User::factory()->create();
        $user->assignRole('driver');

        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password']);
        $token = $login->json('token');

        $this->assertNotNull($token);
        $this->assertCount(1, $user->fresh()->tokens);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
