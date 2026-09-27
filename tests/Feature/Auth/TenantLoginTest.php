<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TenantLoginTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);
    }

    /**
     * Simulates the web panel's request: Sanctum only treats a request as
     * "stateful" (cookie-based) when its Referer/Origin matches
     * SANCTUM_STATEFUL_DOMAINS — see EnsureFrontendRequestsAreStateful.
     */
    private function postAsFrontend(string $uri, array $data): TestResponse
    {
        return $this->withHeaders(['Referer' => 'http://localhost'])->postJson($uri, $data);
    }

    #[DataProvider('gcmRolesProvider')]
    public function test_each_gcm_role_can_login(string $role): void
    {
        $user = User::factory()->create(['email' => "{$role}@gcm.test", 'password' => bcrypt('secret-password')]);
        $user->assignRole($role);

        $response = $this->postAsFrontend('/api/v1/auth/login', [
            'email' => "{$role}@gcm.test",
            'password' => 'secret-password',
        ]);

        $response->assertOk()->assertJsonMissing(['token']);
        $this->assertTrue(Auth::guard('web')->check());
        $this->assertTrue(Auth::guard('web')->user()->is($user));
    }

    public static function gcmRolesProvider(): array
    {
        return [
            ['system_admin'],
            ['data_entry'],
            ['auditor'],
            ['driver'],
        ];
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'admin@gcm.test', 'password' => bcrypt('secret-password')]);

        $response = $this->postAsFrontend('/api/v1/auth/login', [
            'email' => 'admin@gcm.test',
            'password' => 'wrong-password',
        ]);

        $response->assertJsonValidationErrors('email');
        $this->assertFalse(Auth::guard('web')->check());
    }

    /** FRD: a deactivated account gets its own distinct message, not the generic "credentials don't match" one. */
    public function test_deactivated_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'deactivated@gcm.test',
            'password' => bcrypt('secret-password'),
            'status' => 'deactivated',
        ]);

        $response = $this->postAsFrontend('/api/v1/auth/login', [
            'email' => 'deactivated@gcm.test',
            'password' => 'secret-password',
        ]);

        $response->assertJsonValidationErrors('email');
        $this->assertStringContainsString('deactivated', $response->json('errors.email.0'));
        $this->assertFalse(Auth::guard('web')->check());
    }

    /**
     * A wrong password for a deactivated account must still get the
     * generic failure message — revealing "this account is deactivated"
     * before the password is even confirmed correct would leak account
     * status to someone who doesn't actually know the password.
     */
    public function test_deactivated_user_with_wrong_password_gets_the_generic_message_not_the_deactivated_one(): void
    {
        User::factory()->create([
            'email' => 'deactivated2@gcm.test',
            'password' => bcrypt('secret-password'),
            'status' => 'deactivated',
        ]);

        $response = $this->postAsFrontend('/api/v1/auth/login', [
            'email' => 'deactivated2@gcm.test',
            'password' => 'wrong-password',
        ]);

        $response->assertJsonValidationErrors('email');
        $this->assertStringNotContainsString('deactivated', $response->json('errors.email.0'));
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_non_frontend_request_gets_a_token_instead_of_a_session(): void
    {
        $user = User::factory()->create(['email' => 'mobile@gcm.test', 'password' => bcrypt('secret-password')]);
        $user->assignRole('driver');

        // No Referer/Origin header — mimics the (future) mobile app, which
        // isn't a SANCTUM_STATEFUL_DOMAINS origin.
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'mobile@gcm.test',
            'password' => 'secret-password',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        User::factory()->create(['email' => 'throttle@gcm.test', 'password' => bcrypt('secret-password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postAsFrontend('/api/v1/auth/login', [
                'email' => 'throttle@gcm.test',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->postAsFrontend('/api/v1/auth/login', [
            'email' => 'throttle@gcm.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }
}
