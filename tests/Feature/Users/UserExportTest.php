<?php

namespace Tests\Feature\Users;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The export button's columns must match the FRD's list-table spec
 * exactly — see ARCHITECTURE.md §5 / WEEKLY_PLAN.md's Week 2 addenda.
 */
class UserExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_is_forbidden_for_non_system_admin(): void
    {
        $this->seed(RoleSeeder::class);
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');

        $this->actingAs($auditor, 'web')
            ->get('/api/v1/users/export')
            ->assertForbidden();
    }

    public function test_export_succeeds_for_system_admin(): void
    {
        $this->seed(RoleSeeder::class);
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $admin = User::factory()->create();
        $admin->assignRole('system_admin');

        $other = User::factory()->create();
        $other->assignRole('auditor');

        $this->actingAs($admin, 'web')
            ->get('/api/v1/users/export?format=pdf')
            ->assertOk();
    }
}
