<?php

namespace Tests\Feature\Users;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemAdminUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private User $systemAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        app()->instance('tenant', $this->tenantA);

        $this->systemAdmin = User::factory()->create(['email' => 'admin@tenant-a.test']);
        $this->systemAdmin->assignRole('system_admin');
    }

    public function test_creating_a_second_system_admin_in_same_tenant_is_rejected(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/users', [
                'name' => 'Second Admin',
                'email' => 'admin2@tenant-a.test',
                'phone' => '01000000000',
                'password' => 'a-secure-password',
                'password_confirmation' => 'a-secure-password',
                'status' => 'active',
                'roles' => ['system_admin'],
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('roles');
        $this->assertDatabaseMissing('users', ['email' => 'admin2@tenant-a.test']);
    }

    public function test_creating_a_system_admin_in_a_different_tenant_succeeds(): void
    {
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active']);
        app()->instance('tenant', $tenantB);
        $adminB = User::factory()->create(['email' => 'admin@tenant-b.test']);
        $adminB->assignRole('system_admin');

        app()->instance('tenant', $tenantB);

        $response = $this->actingAs($adminB, 'web')
            ->postJson('/api/v1/users', [
                'name' => 'Someone Else',
                'email' => 'auditor@tenant-b.test',
                'phone' => '01000000000',
                'password' => 'a-secure-password',
                'password_confirmation' => 'a-secure-password',
                'status' => 'active',
                'roles' => ['auditor'],
            ]);

        $response->assertCreated();
    }

    public function test_assigning_system_admin_to_a_second_user_via_update_is_rejected(): void
    {
        $auditor = User::factory()->create(['email' => 'auditor@tenant-a.test']);
        $auditor->assignRole('auditor');

        $response = $this->actingAs($this->systemAdmin, 'web')
            ->patchJson("/api/v1/users/{$auditor->id}", [
                'name' => $auditor->name,
                'phone' => '01000000000',
                'roles' => ['system_admin'],
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('roles');
    }

    public function test_deactivating_the_system_admin_is_rejected_regardless_of_caller(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->patchJson("/api/v1/users/{$this->systemAdmin->id}/status", [
                'status' => 'deactivated',
            ]);

        $response->assertUnprocessable();
        $this->assertSame('active', $this->systemAdmin->fresh()->status);
    }
}
