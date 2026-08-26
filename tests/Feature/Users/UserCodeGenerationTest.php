<?php

namespace Tests\Feature\Users;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_get_a_random_unique_code(): void
    {
        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $users = User::factory()->count(10)->create();

        foreach ($users as $user) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', $user->code);
        }

        $this->assertSame(10, $users->pluck('code')->unique()->count());
    }

    public function test_code_uniqueness_is_scoped_per_tenant(): void
    {
        $this->seed(RoleSeeder::class);

        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active']);

        app()->instance('tenant', $tenantA);
        $userA = User::factory()->create(['code' => '123456']);

        app()->instance('tenant', $tenantB);
        $userB = User::factory()->create(['code' => '123456']);

        $this->assertSame('123456', $userA->code);
        $this->assertSame('123456', $userB->code);
    }

    public function test_code_is_returned_by_the_users_api(): void
    {
        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $other = User::factory()->create();
        $other->assignRole('auditor');

        $response = $this->actingAs($admin, 'web')->getJson('/api/v1/users');

        $response->assertOk();
        $this->assertSame($other->code, collect($response->json('data'))->firstWhere('id', $other->id)['code']);
    }
}
