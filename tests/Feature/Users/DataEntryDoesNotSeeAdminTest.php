<?php

namespace Tests\Feature\Users;

use App\Domain\Users\Exports\UsersExport;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * The tenant's system_admin account is outside data_entry's world: not in the list, the search, the
 * export, nor reachable by id. The auditor (view on every page) and the admin itself still see it.
 */
class DataEntryDoesNotSeeAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));

        $this->admin = User::factory()->create(['name' => 'The Admin', 'email' => 'admin@gcm.test']);
        $this->admin->assignRole('system_admin');
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    public function test_data_entry_does_not_see_the_system_admin_in_the_list(): void
    {
        $this->user('auditor', ['name' => 'An Auditor']);

        $names = collect($this->actingAs($this->user('data_entry'), 'web')->getJson('/api/v1/users')->assertOk()->json('data'))->pluck('name');

        $this->assertNotContains('The Admin', $names);
        $this->assertContains('An Auditor', $names);
    }

    public function test_data_entry_cannot_open_the_system_admin_by_id_or_find_it_by_search_or_role(): void
    {
        $dataEntry = $this->user('data_entry');

        $this->actingAs($dataEntry, 'web')->getJson("/api/v1/users/{$this->admin->id}")->assertForbidden();

        $this->assertSame([], $this->actingAs($dataEntry, 'web')->getJson('/api/v1/users?search=admin@gcm.test')->assertOk()->json('data'));
        $this->assertSame([], $this->actingAs($dataEntry, 'web')->getJson('/api/v1/users?role=system_admin')->assertOk()->json('data'));
    }

    public function test_the_export_for_data_entry_leaves_the_system_admin_out(): void
    {
        Excel::fake();

        $this->actingAs($this->user('data_entry'), 'web')->get('/api/v1/users/export')->assertOk();

        Excel::assertDownloaded('users.xlsx', fn (UsersExport $export) => $export->collection()->pluck('name')->doesntContain('The Admin'));
    }

    public function test_the_auditor_still_sees_the_system_admin(): void
    {
        $names = collect($this->actingAs($this->user('auditor'), 'web')->getJson('/api/v1/users')->assertOk()->json('data'))->pluck('name');

        $this->assertContains('The Admin', $names);
    }
}
