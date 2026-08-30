<?php

namespace Tests\Feature\Users;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Locks in that listing users doesn't N+1 the roles relation — Spatie's
 * getRoleNames() (called from UserResource) lazy-loads 'roles' per row
 * via loadMissing() unless it's already eager-loaded on the query.
 */
class UserListQueryEfficiencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_users_does_not_n_plus_one_the_roles_relation(): void
    {
        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        User::factory()->count(10)->create()->each(fn (User $u) => $u->assignRole('auditor'));

        DB::enableQueryLog();

        $response = $this->actingAs($admin, 'web')->getJson('/api/v1/users');

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));

        // A handful of fixed queries (auth, tenant scoping, the paginated
        // select, its count, eager-loaded tenant + roles + role_has_permissions
        // pivot) regardless of row count — NOT ~1 extra query per user.
        $this->assertLessThan(15, $queryCount, "Expected a small, row-count-independent number of queries, got {$queryCount}.");
    }
}
