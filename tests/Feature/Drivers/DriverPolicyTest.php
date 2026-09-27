<?php

namespace Tests\Feature\Drivers;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DriverPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $systemAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->systemAdmin = User::factory()->create(['email' => 'admin@tenant-a.test']);
        $this->systemAdmin->assignRole('system_admin');
    }

    #[DataProvider('nonManagerRolesProvider')]
    public function test_non_manager_roles_cannot_list_drivers(string $role): void
    {
        $actor = User::factory()->create(['email' => "{$role}@tenant-a.test"]);
        $actor->assignRole($role);

        $this->actingAs($actor, 'web')
            ->getJson('/api/v1/drivers')
            ->assertForbidden();
    }

    public static function nonManagerRolesProvider(): array
    {
        return [
            ['driver'],
        ];
    }

    public function test_system_admin_can_list_drivers(): void
    {
        $this->actingAs($this->systemAdmin, 'web')
            ->getJson('/api/v1/drivers')
            ->assertOk();
    }

    /** FRD: driver management is (system_admin / data_entry) — same line as Users. */
    public function test_data_entry_can_list_drivers(): void
    {
        $dataEntry = User::factory()->create(['email' => 'dataentry@tenant-a.test']);
        $dataEntry->assignRole('data_entry');

        $this->actingAs($dataEntry, 'web')
            ->getJson('/api/v1/drivers')
            ->assertOk();
    }

    /** FRD V01.14: auditor gets (عرض/تصدير) على كل الصفحات — was fully blocked under V01.09. */
    public function test_auditor_can_list_and_view_but_not_update_drivers(): void
    {
        $auditor = User::factory()->create(['email' => 'auditor@tenant-a.test']);
        $auditor->assignRole('auditor');

        $this->actingAs($auditor, 'web')->getJson('/api/v1/drivers')->assertOk();

        $this->actingAs($auditor, 'web')
            ->postJson('/api/v1/drivers', ['name' => 'Blocked'])
            ->assertForbidden();
    }
}
