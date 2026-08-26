<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->admin = PlatformAdmin::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@product.test',
            'password' => bcrypt('secret-password'),
        ]);
    }

    public function test_guest_is_redirected_to_platform_login(): void
    {
        $this->get('/platform/roles')->assertRedirect(route('platform.login'));
    }

    public function test_tenant_web_session_cannot_reach_platform_role_management(): void
    {
        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
        $tenantAdmin = User::factory()->create();
        $tenantAdmin->assignRole('system_admin');

        $this->actingAs($tenantAdmin, 'web')
            ->get('/platform/roles')
            ->assertRedirect(route('platform.login'));
    }

    public function test_super_admin_can_create_edit_and_delete_a_role(): void
    {
        $response = $this->actingAs($this->admin, 'platform')
            ->post('/platform/roles', [
                'name' => 'inspector',
                'permissions' => ['users.view'],
            ]);

        $response->assertRedirect(route('platform.roles.index'));
        $role = Role::where('name', 'inspector')->firstOrFail();
        $this->assertTrue($role->hasPermissionTo('users.view'));

        $updateResponse = $this->actingAs($this->admin, 'platform')
            ->put("/platform/roles/{$role->id}", [
                'name' => 'senior_inspector',
                'permissions' => ['users.view', 'users.update'],
            ]);

        $updateResponse->assertRedirect(route('platform.roles.index'));
        $role->refresh();
        $this->assertSame('senior_inspector', $role->name);
        $this->assertTrue($role->hasPermissionTo('users.update'));

        $deleteResponse = $this->actingAs($this->admin, 'platform')
            ->delete("/platform/roles/{$role->id}");

        $deleteResponse->assertRedirect(route('platform.roles.index'));
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_deleting_a_role_assigned_to_users_is_blocked(): void
    {
        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
        $user = User::factory()->create();
        $user->assignRole('driver');

        $role = Role::where('name', 'driver')->firstOrFail();

        $response = $this->actingAs($this->admin, 'platform')
            ->delete("/platform/roles/{$role->id}");

        $response->assertRedirect();
        $response->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }
}
