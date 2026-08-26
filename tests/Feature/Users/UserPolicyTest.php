<?php

namespace Tests\Feature\Users;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $systemAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active']);

        app()->instance('tenant', $this->tenantA);
        $this->systemAdmin = User::factory()->create(['email' => 'admin@tenant-a.test']);
        $this->systemAdmin->assignRole('system_admin');
    }

    #[DataProvider('nonAdminRolesProvider')]
    public function test_non_system_admin_roles_cannot_list_users(string $role): void
    {
        app()->instance('tenant', $this->tenantA);
        $actor = User::factory()->create(['email' => "{$role}@tenant-a.test"]);
        $actor->assignRole($role);

        $this->actingAs($actor, 'web')
            ->getJson('/api/v1/users')
            ->assertForbidden();
    }

    public static function nonAdminRolesProvider(): array
    {
        return [
            ['data_entry'],
            ['auditor'],
            ['driver'],
        ];
    }

    public function test_system_admin_can_list_and_create_users(): void
    {
        $this->actingAs($this->systemAdmin, 'web')
            ->getJson('/api/v1/users')
            ->assertOk();

        $response = $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/users', [
                'name' => 'New Auditor',
                'email' => 'auditor@tenant-a.test',
                'phone' => '01000000000',
                'password' => 'a-secure-password',
                'password_confirmation' => 'a-secure-password',
                'status' => 'active',
                'roles' => ['auditor'],
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'auditor@tenant-a.test']);
    }

    public function test_users_list_never_includes_the_caller_themselves(): void
    {
        $auditor = User::factory()->create(['email' => 'auditor@tenant-a.test']);
        $auditor->assignRole('auditor');

        $response = $this->actingAs($this->systemAdmin, 'web')->getJson('/api/v1/users');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($auditor->id));
        $this->assertFalse($ids->contains($this->systemAdmin->id));
    }

    public function test_system_admin_from_tenant_a_cannot_reach_tenant_b_user(): void
    {
        app()->instance('tenant', $this->tenantB);
        $tenantBUser = User::factory()->create(['email' => 'someone@tenant-b.test']);
        $tenantBUser->assignRole('driver');

        app()->instance('tenant', $this->tenantA);

        $this->actingAs($this->systemAdmin, 'web')
            ->getJson("/api/v1/users/{$tenantBUser->id}")
            ->assertNotFound();
    }
}
