<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantManagementTest extends TestCase
{
    use RefreshDatabase;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->admin = PlatformAdmin::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@product.test',
            'password' => bcrypt('secret-password'),
        ]);
    }

    public function test_guest_is_redirected_to_platform_login(): void
    {
        $this->get('/platform/tenants')->assertRedirect(route('platform.login'));
    }

    public function test_super_admin_can_list_tenants_with_no_tenant_bound(): void
    {
        // No app()->instance('tenant', ...) anywhere in this test —
        // a Platform request never has one bound. Regression test for
        // Tenant::withCount('users') throwing TenantContextMissingException
        // (the users relation's subquery still carries BelongsToTenant's
        // fail-closed scope).
        Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);

        $response = $this->actingAs($this->admin, 'platform')->get('/platform/tenants');

        $response->assertOk();
        $response->assertSee('GCM');
    }

    public function test_tenant_web_session_cannot_reach_platform_tenant_management(): void
    {
        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
        $tenantAdmin = User::factory()->create();
        $tenantAdmin->assignRole('system_admin');

        $this->actingAs($tenantAdmin, 'web')
            ->get('/platform/tenants')
            ->assertRedirect(route('platform.login'));
    }

    public function test_super_admin_can_create_a_tenant_with_its_first_system_admin(): void
    {
        $response = $this->actingAs($this->admin, 'platform')
            ->post('/platform/tenants', [
                'name' => 'Acme Co',
                'slug' => 'acme-co',
                'domain' => null,
                'admin_name' => 'Acme Admin',
                'admin_email' => 'admin@acme.test',
                'admin_phone' => '01000000000',
                'admin_password' => 'a-secure-password',
                'admin_password_confirmation' => 'a-secure-password',
            ]);

        $response->assertRedirect(route('platform.tenants.index'));

        $tenant = Tenant::where('slug', 'acme-co')->firstOrFail();
        $this->assertSame('Acme Co', $tenant->name);
        $this->assertSame('active', $tenant->status);

        app()->instance('tenant', $tenant);
        $admin = User::where('email', 'admin@acme.test')->firstOrFail();
        $this->assertTrue($admin->hasRole('system_admin'));
        $this->assertSame($tenant->id, $admin->tenant_id);
    }

    public function test_tenant_creation_requires_a_unique_slug(): void
    {
        Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);

        $response = $this->actingAs($this->admin, 'platform')
            ->post('/platform/tenants', [
                'name' => 'Duplicate',
                'slug' => 'gcm',
                'admin_name' => 'Someone',
                'admin_email' => 'someone@duplicate.test',
                'admin_phone' => '01000000000',
                'admin_password' => 'a-secure-password',
                'admin_password_confirmation' => 'a-secure-password',
            ]);

        $response->assertSessionHasErrors('slug');
        $this->assertDatabaseCount('tenants', 1);
    }

    public function test_super_admin_can_edit_tenant_details(): void
    {
        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);

        $response = $this->actingAs($this->admin, 'platform')
            ->put("/platform/tenants/{$tenant->id}", [
                'name' => 'GCM Gulf',
                'slug' => 'gcm-gulf',
                'domain' => 'gcm-gulf.example.com',
            ]);

        $response->assertRedirect(route('platform.tenants.index'));
        $tenant->refresh();
        $this->assertSame('GCM Gulf', $tenant->name);
        $this->assertSame('gcm-gulf', $tenant->slug);
        $this->assertSame('gcm-gulf.example.com', $tenant->domain);
    }

    public function test_super_admin_can_suspend_and_reactivate_a_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);

        $suspend = $this->actingAs($this->admin, 'platform')
            ->patch("/platform/tenants/{$tenant->id}/status", ['status' => 'suspended']);

        $suspend->assertRedirect(route('platform.tenants.index'));
        $this->assertSame('suspended', $tenant->fresh()->status);

        $reactivate = $this->actingAs($this->admin, 'platform')
            ->patch("/platform/tenants/{$tenant->id}/status", ['status' => 'active']);

        $reactivate->assertRedirect(route('platform.tenants.index'));
        $this->assertSame('active', $tenant->fresh()->status);
    }

    public function test_suspending_a_tenant_logs_out_its_users(): void
    {
        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
        $tenantAdmin = User::factory()->create();
        $tenantAdmin->assignRole('system_admin');

        $this->actingAs($this->admin, 'platform')
            ->patch("/platform/tenants/{$tenant->id}/status", ['status' => 'suspended']);

        $this->actingAs($tenantAdmin, 'web')
            ->get('/app/user/list')
            ->assertForbidden();
    }
}
