<?php

namespace Tests\Feature\Users;

use App\Models\Driver;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for a real bug the user found live: creating a
 * user with role 'driver' through the general Users flow (POST
 * /api/v1/users, matching the FRD — driver IS a selectable category
 * there, residence/license/insurance are NOT mandatory on this form)
 * produced a User row with role=driver but no matching `drivers` row at
 * all — invisible on the Drivers page. Even the seeded demo driver had
 * this exact bug (created via assignRole() directly, no Driver row) —
 * see DatabaseSeeder's fix.
 *
 * Fix: CreateUserAction now always creates a matching (initially empty —
 * every column but user_id is nullable) Driver row for a driver-role
 * user, filled in later via the dedicated Drivers edit page. What stays
 * exclusive to the Drivers module is EDITING an existing driver — see
 * UserPolicy::update()'s docblock for why that's still blocked here.
 */
class DriverRoleHandlingTest extends TestCase
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

    public function test_creating_a_user_with_role_driver_also_creates_a_matching_driver_row(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/users', [
                'name' => 'New Driver',
                'email' => 'new.driver@gcm.test',
                'phone' => '01000000000',
                'password' => 'a-secure-password',
                'password_confirmation' => 'a-secure-password',
                'status' => 'active',
                'roles' => ['driver'],
            ]);

        $response->assertCreated();

        $user = User::where('email', 'new.driver@gcm.test')->firstOrFail();
        $this->assertTrue($user->hasRole('driver'));
        $this->assertNotNull($user->driver, 'A driver-role user created via /api/v1/users has no matching Driver row.');

        // Now visible on the Drivers page — the actual bug being guarded.
        $this->actingAs($this->systemAdmin, 'web')
            ->getJson('/api/v1/drivers')
            ->assertOk()
            ->assertJsonFragment(['email' => 'new.driver@gcm.test']);
    }

    public function test_creating_a_data_entry_or_auditor_user_does_not_create_a_driver_row(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/users', [
                'name' => 'New Auditor',
                'email' => 'new.auditor@gcm.test',
                'phone' => '01000000000',
                'password' => 'a-secure-password',
                'password_confirmation' => 'a-secure-password',
                'status' => 'active',
                'roles' => ['auditor'],
            ]);

        $response->assertCreated();

        $user = User::where('email', 'new.auditor@gcm.test')->firstOrFail();
        $this->assertNull($user->driver);
    }

    public function test_promoting_an_existing_user_to_driver_via_the_general_edit_endpoint_is_rejected(): void
    {
        // Editing an existing driver stays exclusive to the Drivers
        // module (see next test) — this covers the OTHER direction:
        // turning a non-driver into one via the general edit form,
        // which (unlike creation) has no path to fill in a Driver row.
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
