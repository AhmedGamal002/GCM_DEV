<?php

namespace Tests\Feature\Users;

use App\Models\Driver;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Driver accounts are created exclusively through POST /api/v1/drivers
 * (CreateDriverAction) — never through the general Users flow. Confirmed
 * directly against the FRD text: "إدارة وانشاء حسابات السائقين" is its
 * own section with a fully separate "انشاء مستخدم جديد (سائق)" page
 * (own Reference URL, own complete field spec — Default Vehicle,
 * residence, license, operational license, insurance, entry permits,
 * ALL marked "لازم"/required except attachments), not a category inside
 * the general Create User form.
 *
 * This went back and forth twice before landing here: first excluded
 * after a live bug (role=driver with no matching Driver row — even the
 * seeded demo driver had it, see DatabaseSeeder's fix); briefly restored
 * on a mistaken assumption that the FRD offered driver as a form
 * category with optional extra fields. The FRD text settled it for
 * good — see StoreUserRequest's docblock.
 */
class DriverRoleExclusionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $systemAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->systemAdmin = User::factory()->create(['email' => 'admin@gcm.test']);
        $this->systemAdmin->assignRole('system_admin');
    }

    public function test_creating_a_user_with_role_driver_via_the_general_endpoint_is_rejected(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/users', [
                'name' => 'Should Not Exist',
                'email' => 'not-a-real-driver@gcm.test',
                'phone' => '01000000000',
                'password' => 'a-secure-password',
                'password_confirmation' => 'a-secure-password',
                'status' => 'active',
                'roles' => ['driver'],
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('roles.0');
        $this->assertDatabaseMissing('users', ['email' => 'not-a-real-driver@gcm.test']);
    }

    public function test_promoting_an_existing_user_to_driver_via_the_general_endpoint_is_rejected(): void
    {
        $auditor = User::factory()->create(['email' => 'auditor@gcm.test']);
        $auditor->assignRole('auditor');

        $response = $this->actingAs($this->systemAdmin, 'web')
            ->patchJson("/api/v1/users/{$auditor->id}", [
                'name' => $auditor->name,
                'phone' => '01000000000',
                'roles' => ['driver'],
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('roles.0');
        $this->assertTrue($auditor->fresh()->hasRole('auditor'));
        $this->assertFalse($auditor->fresh()->hasRole('driver'));
    }

    public function test_editing_an_existing_driver_via_the_general_endpoint_is_forbidden(): void
    {
        $driverUser = User::factory()->create(['email' => 'real.driver@gcm.test']);
        $driverUser->assignRole('driver');
        Driver::create(['user_id' => $driverUser->id]);

        $response = $this->actingAs($this->systemAdmin, 'web')
            ->patchJson("/api/v1/users/{$driverUser->id}", [
                'name' => 'Attempted Rename',
                'phone' => '01000000000',
                'roles' => ['auditor'],
            ]);

        // Blocked by UserPolicy::update() regardless of payload — a
        // driver's record is only ever editable via /api/v1/drivers/{id}.
        $response->assertForbidden();
        $this->assertSame('real.driver@gcm.test', $driverUser->fresh()->email);
        $this->assertTrue($driverUser->fresh()->hasRole('driver'));
    }

    public function test_user_resource_exposes_driver_id_for_a_driver_and_null_otherwise(): void
    {
        $driverUser = User::factory()->create(['email' => 'real.driver@gcm.test']);
        $driverUser->assignRole('driver');
        $driver = Driver::create(['user_id' => $driverUser->id]);

        $auditor = User::factory()->create(['email' => 'auditor@gcm.test']);
        $auditor->assignRole('auditor');

        $driverResponse = $this->actingAs($this->systemAdmin, 'web')
            ->getJson("/api/v1/users/{$driverUser->id}");
        $driverResponse->assertOk()->assertJsonPath('data.driver_id', $driver->id);

        $auditorResponse = $this->actingAs($this->systemAdmin, 'web')
            ->getJson("/api/v1/users/{$auditor->id}");
        $auditorResponse->assertOk()->assertJsonPath('data.driver_id', null);
    }
}
