<?php

namespace Tests\Feature\Auth;

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PlatformLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_login_via_platform_guard(): void
    {
        $admin = PlatformAdmin::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@product.test',
            'password' => bcrypt('secret-password'),
        ]);

        $response = $this->post('/platform/login', [
            'email' => 'superadmin@product.test',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('platform.dashboard'));
        $this->assertTrue(Auth::guard('platform')->check());
        $this->assertTrue(Auth::guard('platform')->user()->is($admin));
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_tenant_user_cannot_authenticate_against_platform_guard(): void
    {
        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        User::factory()->create([
            'email' => 'admin@gcm.test',
            'password' => bcrypt('secret-password'),
        ]);

        $response = $this->post('/platform/login', [
            'email' => 'admin@gcm.test',
            'password' => 'secret-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertFalse(Auth::guard('platform')->check());
    }

    public function test_platform_admin_cannot_authenticate_against_tenant_login(): void
    {
        PlatformAdmin::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@product.test',
            'password' => bcrypt('secret-password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'superadmin@product.test',
            'password' => 'secret-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertFalse(Auth::guard('web')->check());
    }
}
