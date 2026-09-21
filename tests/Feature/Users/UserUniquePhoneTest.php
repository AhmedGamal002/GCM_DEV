<?php

namespace Tests\Feature\Users;

use App\Models\Driver;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phone must not repeat within the same tenant — same rule as users.code
 * and vehicles' plate (see the new migration's docblock). All phones live
 * on the users table (a driver's account IS a User row), so this also
 * covers Users vs. Drivers cross-conflicts, not just Users vs. Users.
 */
class UserUniquePhoneTest extends TestCase
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

    private function userPayload(string $email, string $phone): array
    {
        return [
            'name' => 'New Auditor',
            'email' => $email,
            'phone' => $phone,
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
            'status' => 'active',
            'roles' => ['auditor'],
        ];
    }

    public function test_duplicate_phone_in_same_tenant_is_rejected_on_create(): void
    {
        $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/users', $this->userPayload('one@tenant-a.test', '01000000000'))
            ->assertCreated();

        $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/users', $this->userPayload('two@tenant-a.test', '01000000000'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_same_phone_in_a_different_tenant_is_allowed(): void
    {
        $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/users', $this->userPayload('one@tenant-a.test', '01000000000'))
            ->assertCreated();

        app()->instance('tenant', $this->tenantB);
        $adminB = User::factory()->create(['email' => 'admin@tenant-b.test']);
        $adminB->assignRole('system_admin');

        $this->actingAs($adminB, 'web')
            ->postJson('/api/v1/users', $this->userPayload('one@tenant-b.test', '01000000000'))
            ->assertCreated();
    }

    public function test_duplicate_phone_is_rejected_on_update(): void
    {
        $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/users', $this->userPayload('one@tenant-a.test', '01000000000'))
            ->assertCreated();

        $response = $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/users', $this->userPayload('two@tenant-a.test', '02000000000'));
        $response->assertCreated();
        $userTwoId = $response->json('data.id');

        $this->actingAs($this->systemAdmin, 'web')
            ->patchJson("/api/v1/users/{$userTwoId}", [
                'name' => 'Two', 'phone' => '01000000000', 'roles' => ['auditor'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_updating_a_user_without_changing_their_own_phone_does_not_reject_itself(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/users', $this->userPayload('one@tenant-a.test', '01000000000'));
        $userId = $response->json('data.id');

        $this->actingAs($this->systemAdmin, 'web')
            ->patchJson("/api/v1/users/{$userId}", [
                'name' => 'Renamed', 'phone' => '01000000000', 'roles' => ['auditor'],
            ])
            ->assertOk();
    }

    public function test_a_drivers_phone_conflicting_with_an_existing_users_phone_is_rejected(): void
    {
        $this->seed(VehicleCategorySeeder::class);
        $category = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();
        $vehicle = Vehicle::factory()->create(['vehicle_category_id' => $category->id]);

        $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/users', $this->userPayload('staff@tenant-a.test', '01000000000'))
            ->assertCreated();

        $response = $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/drivers', [
                'name' => 'New Driver',
                'email' => 'driver@tenant-a.test',
                'phone' => '01000000000',
                'password' => 'a-secure-password',
                'password_confirmation' => 'a-secure-password',
                'status' => 'active',
                'vehicle_category_ids' => [$category->id],
                'default_vehicle_id' => $vehicle->id,
                'residence_number' => 'RES-001', 'residence_valid_to' => '2030-01-01',
                'license_number' => 'LIC-001', 'license_valid_to' => '2030-01-01',
                'operational_license_number' => 'OP-001', 'operational_license_valid_to' => '2030-01-01',
                'insurance_number' => 'INS-001', 'insurance_valid_to' => '2030-01-01',
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->assertSame(0, Driver::count());
    }
}
