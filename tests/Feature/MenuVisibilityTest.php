<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for MenuComposer showing the Platform menu (Tenants,
 * Roles & Permissions) on tenant pages whenever the browser also happens
 * to be authenticated on the `platform` guard — Laravel keeps every
 * guard's login independently in one session, so this is a real,
 * reachable state (e.g. a Super Admin who also logs into a tenant
 * without logging out of Platform first), not just a test artifact.
 */
class MenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $tenantAdmin;

    private PlatformAdmin $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
        $this->tenantAdmin = User::factory()->create();
        $this->tenantAdmin->assignRole('system_admin');

        $this->platformAdmin = PlatformAdmin::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@product.test',
            'password' => bcrypt('secret-password'),
        ]);
    }

    public function test_tenant_page_shows_the_tenant_menu_even_when_also_platform_authenticated(): void
    {
        $response = $this->actingAs($this->tenantAdmin, 'web')
            ->actingAs($this->platformAdmin, 'platform')
            ->get('/app/user/list');

        $response->assertOk();
        $response->assertDontSee('Roles &amp; Permissions', false);
        $response->assertSee('Users', false);
    }

    public function test_platform_page_shows_the_platform_menu_even_when_also_tenant_authenticated(): void
    {
        $response = $this->actingAs($this->tenantAdmin, 'web')
            ->actingAs($this->platformAdmin, 'platform')
            ->get('/platform/dashboard');

        $response->assertOk();
        $response->assertSee('Tenants', false);
        $response->assertSee('Roles &amp; Permissions', false);
    }
}
