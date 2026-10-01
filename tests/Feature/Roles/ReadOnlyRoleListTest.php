<?php

namespace Tests\Feature\Roles;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReadOnlyRoleListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $tenant);
    }

    #[DataProvider('rolesProvider')]
    public function test_every_tenant_role_can_read_the_role_list(string $role): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user, 'web')->getJson('/api/v1/roles');

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            ['system_admin', 'data_entry', 'auditor', 'driver', 'client_project_manager', 'client_project_auditor'],
            $response->json()
        );
    }

    public static function rolesProvider(): array
    {
        return [
            ['system_admin'],
            ['data_entry'],
            ['auditor'],
            ['driver'],
            ['client_project_manager'],
            ['client_project_auditor'],
        ];
    }

    public function test_no_mutation_route_exists_for_tenant_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');

        // 405, not 404: the URI exists (GET is registered), POST simply
        // isn't — correct REST semantics for "no mutation route here".
        $this->actingAs($user, 'web')
            ->postJson('/api/v1/roles', ['name' => 'hacker'])
            ->assertStatus(405);
    }
}
