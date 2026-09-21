<?php

namespace Tests\Feature\Drivers;

use App\Models\Driver;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriverCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $systemAdmin;

    private VehicleCategory $vehicleCategory;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);

        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $this->systemAdmin = User::factory()->create();
        $this->systemAdmin->assignRole('system_admin');

        $this->vehicleCategory = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();
        $this->vehicle = Vehicle::factory()->create(['vehicle_category_id' => $this->vehicleCategory->id]);
    }

    private function basePayload(): array
    {
        return [
            'name' => 'New Driver',
            'email' => 'driver@tenant-a.test',
            'phone' => '01000000000',
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
            'status' => 'active',
            'vehicle_category_ids' => [$this->vehicleCategory->id],
            'default_vehicle_id' => $this->vehicle->id,
            'residence_number' => 'RES-001',
            'residence_valid_to' => '2030-01-01',
            'license_number' => 'LIC-001',
            'license_valid_to' => '2030-01-01',
            'operational_license_number' => 'OP-001',
            'operational_license_valid_to' => '2030-01-01',
            'insurance_number' => 'INS-001',
            'insurance_valid_to' => '2030-01-01',
        ];
    }

    public function test_system_admin_can_create_a_driver_with_full_profile_and_entry_permits(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->post('/api/v1/drivers', array_merge($this->basePayload(), [
                'entry_permits' => [
                    ['area_name' => 'Riyadh', 'permit_number' => 'P-1', 'valid_to' => '2029-01-01'],
                    ['area_name' => 'Jeddah', 'permit_number' => 'P-2', 'valid_to' => '2029-06-01'],
                ],
            ]));

        $response->assertCreated();

        $user = User::where('email', 'driver@tenant-a.test')->firstOrFail();
        $this->assertTrue($user->hasRole('driver'));
        $this->assertSame('gcm', $user->affiliation);

        $driver = Driver::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('RES-001', $driver->residence_number);
        $this->assertSame('LIC-001', $driver->license_number);
        $this->assertCount(2, $driver->entryPermits);
    }

    /** FRD: driver create/edit is (system_admin / data_entry), same line as Users. */
    public function test_data_entry_can_create_a_driver(): void
    {
        $dataEntry = User::factory()->create();
        $dataEntry->assignRole('data_entry');

        $this->actingAs($dataEntry, 'web')
            ->post('/api/v1/drivers', $this->basePayload())
            ->assertCreated();
    }

    public function test_driver_documents_are_stored_on_the_private_disk_not_public(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->post('/api/v1/drivers', array_merge($this->basePayload(), [
                'license_attachment' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
            ]));

        $response->assertCreated();

        $driver = Driver::firstOrFail();
        $this->assertNotNull($driver->license_attachment);
        Storage::disk('local')->assertExists($driver->license_attachment);
        Storage::disk('public')->assertMissing($driver->license_attachment);
    }

    public function test_required_driver_fields_are_validated(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/drivers', [
                'name' => 'Incomplete Driver',
                'email' => 'incomplete@tenant-a.test',
                'phone' => '01000000000',
                'password' => 'a-secure-password',
                'password_confirmation' => 'a-secure-password',
                'status' => 'active',
                // missing vehicle_category_ids/default_vehicle_id/residence/license/operational_license/insurance
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'vehicle_category_ids', 'default_vehicle_id',
            'residence_number', 'license_number', 'operational_license_number', 'insurance_number',
        ]);
    }

    /**
     * Regression test for a real bug found live in the browser: multipart
     * form submissions (what every actual browser form sends — unlike
     * postJson()'s JSON body, which keeps PHP array values as real ints)
     * stringify every field, including array values. The category-match
     * check originally used in_array(..., true) (strict) — "3" !== 3, so
     * it rejected every submission, even ones that genuinely matched.
     * postJson()-based tests never caught this because JSON preserves
     * int types; this test deliberately passes string ids, like a real
     * <form> submission would, to guard against it recurring.
     */
    public function test_creating_a_driver_succeeds_when_ids_are_submitted_as_strings_like_a_real_form_does(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->post('/api/v1/drivers', array_merge($this->basePayload(), [
                'vehicle_category_ids' => [(string) $this->vehicleCategory->id],
                'default_vehicle_id' => (string) $this->vehicle->id,
            ]), ['Accept' => 'application/json']);

        $response->assertCreated();
    }

    public function test_default_vehicle_must_belong_to_a_selected_qualified_category(): void
    {
        $otherCategory = VehicleCategory::where('slug', 'water_tanker')->firstOrFail();

        $response = $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/drivers', array_merge($this->basePayload(), [
                // vehicle belongs to 'dump_truck' (see setUp), but only
                // 'water_tanker' is selected as a qualified category.
                'vehicle_category_ids' => [$otherCategory->id],
            ]));

        $response->assertUnprocessable()->assertJsonValidationErrors('default_vehicle_id');
    }

    /**
     * A vehicle can be "the default" for at most one driver — otherwise
     * two drivers both showing the same truck as theirs is meaningless.
     * Real gap flagged by the user: the vehicle dropdown had no such
     * restriction at all, so a second driver could freely pick a vehicle
     * already claimed by someone else.
     */
    public function test_a_vehicle_already_set_as_another_drivers_default_is_rejected(): void
    {
        $existingDriverUser = User::factory()->create();
        $existingDriverUser->assignRole('driver');
        Driver::create([
            'user_id' => $existingDriverUser->id,
            'default_vehicle_id' => $this->vehicle->id,
            'residence_number' => 'RES-000', 'residence_valid_to' => '2030-01-01',
            'license_number' => 'LIC-000', 'license_valid_to' => '2030-01-01',
            'operational_license_number' => 'OPL-000', 'operational_license_valid_to' => '2030-01-01',
            'insurance_number' => 'INS-000', 'insurance_valid_to' => '2030-01-01',
        ]);

        $response = $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/drivers', $this->basePayload());

        $response->assertUnprocessable()->assertJsonValidationErrors('default_vehicle_id');
    }

    /**
     * Same normalization as VehicleManagementTest's equivalent test — see
     * its docblock. An untouched Quill editor submits `<p><br></p>`, not
     * an empty string.
     */
    public function test_an_empty_quill_editor_is_stored_as_null_not_empty_markup(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->postJson('/api/v1/drivers', array_merge($this->basePayload(), ['additional_data' => '<p><br></p>']));

        $response->assertCreated();
        $this->assertNull(Driver::firstOrFail()->user->additional_data);
    }
}
