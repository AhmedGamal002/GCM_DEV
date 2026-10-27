<?php

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PlatformAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CredentialHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_seeders_refuse_to_run_in_production(): void
    {
        // They create accounts whose password is "password" — in a live system that is an open door.
        $this->app['env'] = 'production';

        foreach ([DatabaseSeeder::class, PlatformAdminSeeder::class] as $seeder) {
            try {
                $this->app->make($seeder)->run();
                $this->fail("{$seeder} ran in production");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('production', $e->getMessage());
            }
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('platform_admins', 0);
    }

    public function test_the_demo_seeders_still_work_in_development(): void
    {
        $this->seed(PlatformAdminSeeder::class);

        $this->assertDatabaseHas('platform_admins', ['email' => 'superadmin@product.test']);
    }

    public function test_api_tokens_expire(): void
    {
        $this->assertGreaterThan(0, (int) config('sanctum.expiration'), 'a bearer token must not live forever');

        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));
        $user = User::factory()->create();

        // Created now, used for the first time after the expiry has passed (the sanctum guard keeps the user it resolved
        // first for the rest of a test, so a request made earlier would hide the expiry).
        $old = $user->createToken('phone')->plainTextToken;
        $this->travel(config('sanctum.expiration') + 1)->minutes();

        $this->withToken($old)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_the_session_cookie_is_secure_by_default_in_production(): void
    {
        $config = file_get_contents(config_path('session.php'));

        $this->assertStringContainsString("env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')", $config);
    }
}
