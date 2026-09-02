<?php

namespace Tests\Feature\Drivers;

use App\Domain\Drivers\Actions\CreateDriverAction;
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

class DriverDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $systemAdmin;

    private Driver $driver;

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

        $vehicleCategory = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();
        $vehicle = Vehicle::factory()->create(['vehicle_category_id' => $vehicleCategory->id]);

        $this->driver = app(CreateDriverAction::class)->execute(
            [
                'name' => 'Doc Driver',
                'email' => 'docdriver@tenant-a.test',
                'phone' => '01000000000',
                'password' => 'a-secure-password',
                'status' => 'active',
                'vehicle_category_ids' => [$vehicleCategory->id],
                'default_vehicle_id' => $vehicle->id,
                'residence_number' => 'RES-001', 'residence_valid_to' => '2030-01-01',
                'license_number' => 'LIC-001', 'license_valid_to' => '2030-01-01',
                'operational_license_number' => 'OP-001', 'operational_license_valid_to' => '2030-01-01',
                'insurance_number' => 'INS-001', 'insurance_valid_to' => '2030-01-01',
            ],
            null,
            ['license' => UploadedFile::fake()->create('license.pdf', 50, 'application/pdf')],
            []
        );
    }

    public function test_system_admin_can_download_a_driver_document(): void
    {
        $response = $this->actingAs($this->systemAdmin, 'web')
            ->get("/api/v1/drivers/{$this->driver->id}/documents/license");

        $response->assertOk();
    }

    public function test_a_non_system_admin_cannot_download_a_driver_document(): void
    {
        $auditor = User::factory()->create(['email' => 'auditor@tenant-a.test']);
        $auditor->assignRole('auditor');

        $this->actingAs($auditor, 'web')
            ->get("/api/v1/drivers/{$this->driver->id}/documents/license")
            ->assertForbidden();
    }

    public function test_an_unknown_document_type_404s(): void
    {
        $this->actingAs($this->systemAdmin, 'web')
            ->get("/api/v1/drivers/{$this->driver->id}/documents/not-a-real-type")
            ->assertNotFound();
    }

    public function test_a_document_field_with_no_uploaded_file_404s(): void
    {
        $this->actingAs($this->systemAdmin, 'web')
            ->get("/api/v1/drivers/{$this->driver->id}/documents/insurance")
            ->assertNotFound();
    }
}
