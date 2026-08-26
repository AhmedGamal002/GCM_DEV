<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
    }

    public function test_forgot_password_sends_reset_link_for_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'admin@gcm.test']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'admin@gcm.test',
        ]);

        $response->assertOk()->assertJsonStructure(['message']);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_forgot_password_rejects_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nobody@gcm.test',
        ]);

        $response->assertJsonValidationErrors('email');
    }

    public function test_reset_password_with_valid_token_updates_password(): void
    {
        $user = User::factory()->create(['email' => 'admin@gcm.test', 'password' => bcrypt('old-password')]);
        $token = Password::broker('users')->createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'admin@gcm.test',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertOk()->assertJsonStructure(['message']);
        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_reset_password_with_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'admin@gcm.test', 'password' => bcrypt('old-password')]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'admin@gcm.test',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertJsonValidationErrors('email');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_password_reset_request_is_rate_limited_after_five_attempts(): void
    {
        User::factory()->create(['email' => 'admin@gcm.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => 'admin@gcm.test']);
        }

        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'admin@gcm.test']);

        $response->assertStatus(429);
    }
}
