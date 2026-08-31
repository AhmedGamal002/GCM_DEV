<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for a real bug found while building the Postman
 * collection: every OTHER test in this app authenticates via
 * actingAs($user, 'web'), which never runs the actual auth:sanctum
 * middleware pipeline — so a request that genuinely carries a bearer
 * token (the mobile-app path LoginController is explicitly built for)
 * had zero coverage. It 500'd (TenantContextMissingException) on every
 * single tenant-scoped endpoint: Sanctum's own Guard::isValidAccessToken()
 * resolves the token's owner via PersonalAccessToken::tokenable(), a raw
 * Eloquent relation on User that (unlike the 'web' guard's own lookups,
 * see TenantUnawareEloquentUserProvider) was never exempted from
 * BelongsToTenant — and even once that's fixed, EnsureTenant itself only
 * ever looked at guard `web`, so a token-authenticated request would
 * still never get a tenant bound. Fixed by App\Models\PersonalAccessToken
 * + EnsureTenant resolving via guard `sanctum` (see both files' docblocks).
 *
 * Deliberately never pre-binds 'tenant' into the container the way other
 * tests' setUp() does — that would mask exactly this bug, since the
 * middleware's own (broken) resolution would be papered over by an
 * already-bound instance left by the test itself.
 */
class BearerTokenTenantAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function tokenFor(User $user, string $password = 'secret-password'): string
    {
        // No Referer/Origin header — not a SANCTUM_STATEFUL_DOMAINS origin,
        // so LoginController issues a bearer token instead of a session
        // cookie, exactly like the (future) driver mobile app will.
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertOk()->assertJsonStructure(['token']);

        return $response->json('token');
    }

    public function test_bearer_token_request_can_reach_a_tenant_scoped_endpoint(): void
    {
        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
        $user = User::factory()->create(['password' => bcrypt('secret-password')]);
        $user->assignRole('system_admin');
        app()->forgetInstance('tenant');

        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/users');

        $response->assertOk();
    }

    public function test_bearer_token_still_enforces_tenant_isolation(): void
    {
        app()->instance('tenant', Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']));
        $userA = User::factory()->create(['password' => bcrypt('secret-password')]);
        $userA->assignRole('system_admin');

        app()->instance('tenant', Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active']));
        $userB = User::factory()->create();
        $userB->assignRole('system_admin');

        app()->forgetInstance('tenant');

        $token = $this->tokenFor($userA);

        // Tenant A's token must never see Tenant B's user, even by direct id.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/users/{$userB->id}");

        $response->assertNotFound();
    }

    public function test_bearer_token_for_a_suspended_tenant_is_rejected_and_revoked(): void
    {
        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
        $user = User::factory()->create(['password' => bcrypt('secret-password')]);
        $user->assignRole('system_admin');
        app()->forgetInstance('tenant');

        $token = $this->tokenFor($user);

        $tenant->update(['status' => 'suspended']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/users');

        $response->assertForbidden();

        // The token itself must be gone — not just this one request refused.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
