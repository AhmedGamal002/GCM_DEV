<?php

namespace Tests\Feature\Users;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    #[DataProvider('nonManagerRolesProvider')]
    public function test_non_manager_roles_cannot_list_users(string $role): void
    {
        app()->instance('tenant', $this->tenantA);
        $actor = User::factory()->create(['email' => "{$role}@tenant-a.test"]);
        $actor->assignRole($role);

        $this->actingAs($actor, 'web')
            ->getJson('/api/v1/users')
            ->assertForbidden();
    }

    public static function nonManagerRolesProvider(): array
    {
        return [
            ['driver'],
        ];
    }

    /** FRD V01.14: auditor gets (عرض/تصدير) على كل الصفحات — was fully blocked under V01.09. */
    public function test_auditor_can_list_and_view_but_not_create_users(): void
    {
        app()->instance('tenant', $this->tenantA);
        $auditor = User::factory()->create(['email' => 'auditor@tenant-a.test']);
        $auditor->assignRole('auditor');
        $target = User::factory()->create(['email' => 'target@tenant-a.test']);
        $target->assignRole('data_entry');

        $this->actingAs($auditor, 'web')->getJson('/api/v1/users')->assertOk();
        $this->actingAs($auditor, 'web')->getJson("/api/v1/users/{$target->id}")->assertOk();

        $this->actingAs($auditor, 'web')
            ->postJson('/api/v1/users', [
                'name' => 'Blocked',
                'email' => 'blocked@tenant-a.test',
                'phone' => '01000000099',
                'password' => 'a-secure-password',
                'password_confirmation' => 'a-secure-password',
                'status' => 'active',
                'roles' => ['auditor'],
            ])
            ->assertForbidden();
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

    /** FRD: "(انشاء / تعديل) الحسابات مسؤولية (مدير النظام / مدخل البيانات)". */
    public function test_data_entry_can_list_and_create_users(): void
    {
        $dataEntry = User::factory()->create(['email' => 'dataentry@tenant-a.test']);
        $dataEntry->assignRole('data_entry');

        $this->actingAs($dataEntry, 'web')
            ->getJson('/api/v1/users')
            ->assertOk();

        $this->actingAs($dataEntry, 'web')
            ->postJson('/api/v1/users', [
                'name' => 'New Auditor',
                'email' => 'by-data-entry@tenant-a.test',
                'phone' => '01000000001',
                'password' => 'a-secure-password',
                'password_confirmation' => 'a-secure-password',
                'status' => 'active',
                'roles' => ['auditor'],
            ])
            ->assertCreated();
    }

    /** FRD: GCM users can't change their own password — system_admin / data_entry set it from the edit form. */
    #[DataProvider('passwordSettersProvider')]
    public function test_admins_can_set_another_users_password(string $role): void
    {
        $actor = $role === 'system_admin' ? $this->systemAdmin : User::factory()->create(['email' => "{$role}@tenant-a.test"]);
        $actor->assignRole($role);

        $target = User::factory()->create(['email' => 'target@tenant-a.test', 'password' => bcrypt('old-password')]);
        $target->assignRole('auditor');

        $this->actingAs($actor, 'web')
            ->patchJson("/api/v1/users/{$target->id}", [
                'name' => $target->name, 'phone' => '01000000009', 'roles' => ['auditor'],
                'password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('brand-new-password', $target->fresh()->password));
    }

    public static function passwordSettersProvider(): array
    {
        return [['system_admin'], ['data_entry']];
    }

    public function test_leaving_the_password_blank_keeps_the_current_one(): void
    {
        $target = User::factory()->create(['email' => 'target@tenant-a.test', 'password' => bcrypt('old-password')]);
        $target->assignRole('auditor');

        $this->actingAs($this->systemAdmin, 'web')
            ->patchJson("/api/v1/users/{$target->id}", ['name' => 'Renamed', 'phone' => '01000000009', 'roles' => ['auditor']])
            ->assertOk();

        $this->assertTrue(Hash::check('old-password', $target->fresh()->password));
    }

    /**
     * A data_entry who can edit users and set passwords must never be able
     * to reach the tenant's system_admin — that would be account takeover
     * (reset its password) or a demotion (roles field).
     */
    public function test_data_entry_cannot_edit_or_change_the_status_of_the_system_admin(): void
    {
        $dataEntry = User::factory()->create(['email' => 'dataentry@tenant-a.test']);
        $dataEntry->assignRole('data_entry');

        $this->actingAs($dataEntry, 'web')
            ->patchJson("/api/v1/users/{$this->systemAdmin->id}", [
                'name' => 'Hijacked', 'phone' => '01000000009', 'roles' => ['data_entry'],
                'password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password',
            ])
            ->assertForbidden();

        $this->actingAs($dataEntry, 'web')
            ->patchJson("/api/v1/users/{$this->systemAdmin->id}/status", ['status' => 'on_vacation'])
            ->assertForbidden();

        $this->assertTrue($this->systemAdmin->fresh()->hasRole('system_admin'));
        $this->assertSame('active', $this->systemAdmin->fresh()->status);
    }

    /** FRD V01.14: vacation AND deactivation are both (system_admin / data_entry) now — data_entry has admin parity here (except touching system_admin itself, see below). */
    public function test_data_entry_can_set_vacation_and_deactivate(): void
    {
        $dataEntry = User::factory()->create(['email' => 'dataentry@tenant-a.test']);
        $dataEntry->assignRole('data_entry');

        $target = User::factory()->create(['email' => 'target@tenant-a.test']);
        $target->assignRole('auditor');

        $this->actingAs($dataEntry, 'web')
            ->patchJson("/api/v1/users/{$target->id}/status", ['status' => 'on_vacation'])
            ->assertOk();
        $this->assertSame('on_vacation', $target->refresh()->status);

        $this->actingAs($dataEntry, 'web')
            ->patchJson("/api/v1/users/{$target->id}/status", ['status' => 'deactivated'])
            ->assertOk();
        $this->assertSame('deactivated', $target->refresh()->status);
    }

    /**
     * Same normalization as the equivalent Vehicles/Drivers/Assets tests
     * — an untouched Quill editor submits `<p><br></p>`, not an empty
     * string, so it always got saved and always showed an empty-looking
     * "Additional Data" section on the view page.
     */
    public function test_an_empty_quill_editor_is_stored_as_null_not_empty_markup(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/users', [
                'name' => 'New Auditor',
                'email' => 'auditor2@tenant-a.test',
                'phone' => '01000000000',
                'password' => 'a-secure-password',
                'password_confirmation' => 'a-secure-password',
                'status' => 'active',
                'roles' => ['auditor'],
                'additional_data' => '<p><br></p>',
            ]);

        $response->assertCreated();
        $this->assertNull(User::where('email', 'auditor2@tenant-a.test')->firstOrFail()->additional_data);
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

    /** FRD: user view/edit pages show "Last updated by X — <datetime>". */
    public function test_updated_by_is_recorded_on_create_and_update(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/users', [
                'name' => 'New Auditor',
                'email' => 'auditor3@tenant-a.test',
                'phone' => '01000000000',
                'password' => 'a-secure-password',
                'password_confirmation' => 'a-secure-password',
                'status' => 'active',
                'roles' => ['auditor'],
            ]);
        $response->assertCreated();
        $userId = $response->json('data.id');

        $created = User::findOrFail($userId);
        $this->assertSame($this->systemAdmin->id, $created->updated_by);

        $this->actingAs($this->systemAdmin, 'web')
            ->patchJson("/api/v1/users/{$userId}", [
                'name' => 'Updated Name',
                'phone' => '01000000000',
                'roles' => ['auditor'],
            ])
            ->assertOk();

        $response = $this->actingAs($this->systemAdmin, 'web')->getJson("/api/v1/users/{$userId}");
        $response->assertOk()->assertJsonPath('data.updated_by_name', $this->systemAdmin->name);
    }
}
