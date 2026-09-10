<?php

namespace Tests\Feature\Vehicles;

use App\Models\Asset;
use App\Models\AssetCapacityCategory;
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

class VehicleManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $dataEntry;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->admin = User::factory()->create(['email' => 'admin@gcm.test']);
        $this->admin->assignRole('system_admin');

        $this->dataEntry = User::factory()->create(['email' => 'de@gcm.test']);
        $this->dataEntry->assignRole('data_entry');

        // The embedded-container capacity in validPayload() must be one the
        // fleet can field for a hook_lift vehicle + container kind — that's
        // derived from the asset pool (see EmbeddedCapacityFitsVehicleRule),
        // so a matching, compatible asset has to exist.
        $capacity = AssetCapacityCategory::factory()->container()->create(['name' => 'Standard skip']);
        $hookLift = VehicleCategory::where('slug', 'hook_lift')->firstOrFail();
        Asset::factory()->create([
            'asset_type' => 'container',
            'asset_capacity_category_id' => $capacity->id,
        ])->compatibleVehicleCategories()->attach($hookLift->id);
    }

    private function validPayload(array $overrides = []): array
    {
        $categoryId = VehicleCategory::where('slug', 'hook_lift')->value('id');
        $capacityId = AssetCapacityCategory::first()->id;

        return array_merge([
            'plate_letters' => 'ABC',
            'plate_numbers' => '1234',
            'vehicle_category_id' => $categoryId,
            'has_embedded_container' => '1',
            'embedded_container_type' => 'container',
            'embedded_asset_capacity_category_id' => $capacityId,
            'operational_status' => 'active',
            'affiliation' => 'gcm',
            'additional_data' => '<p>note</p>',
            'photo_front' => UploadedFile::fake()->image('front.jpg'),
            'photo_back' => UploadedFile::fake()->image('back.jpg'),
            'documents' => [
                'registration_card' => ['number' => '1001', 'valid_to' => '2027-01-01', 'attachment' => UploadedFile::fake()->create('rc.pdf', 40, 'application/pdf')],
                'fitness_document' => ['number' => '1002', 'valid_to' => '2027-01-01'],
                'inspection_certificate' => ['number' => '1003', 'valid_to' => '2027-01-01'],
                'insurance' => ['number' => '1004', 'valid_to' => '2027-01-01'],
            ],
            'entry_permits' => [
                ['area_name' => 'Riyadh', 'permit_number' => '2001', 'valid_to' => '2027-01-01'],
                ['area_name' => 'Jeddah', 'permit_number' => '2002', 'valid_to' => '2027-01-01'],
            ],
        ], $overrides);
    }

    public function test_system_admin_creates_a_full_vehicle_with_documents_and_photos(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->validPayload(), ['Accept' => 'application/json']);

        $response->assertCreated();

        $vehicle = Vehicle::firstOrFail();
        $this->assertSame('ABC', $vehicle->plate_letters);
        $this->assertTrue($vehicle->has_embedded_container);
        $this->assertSame('container', $vehicle->embedded_container_type);

        // 4 single documents + 2 entry permits
        $this->assertSame(6, $vehicle->documents()->count());
        $this->assertSame(2, $vehicle->entryPermits()->count());

        $this->assertNotNull($vehicle->photo_front);
        Storage::disk('public')->assertExists($vehicle->photo_front);

        $rc = $vehicle->documents()->where('type', 'registration_card')->firstOrFail();
        $this->assertNotNull($rc->attachment_path);
        Storage::disk('local')->assertExists($rc->attachment_path);
    }

    public function test_data_entry_can_create_a_vehicle(): void
    {
        $this->actingAs($this->dataEntry, 'web')
            ->post('/api/v1/vehicles', $this->validPayload(), ['Accept' => 'application/json'])
            ->assertCreated();
    }

    public function test_embedded_capacity_is_required_when_embedded_container_is_true(): void
    {
        $payload = $this->validPayload([
            'has_embedded_container' => '1',
            'embedded_container_type' => null,
            'embedded_asset_capacity_category_id' => null,
        ]);

        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $payload, ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['embedded_container_type', 'embedded_asset_capacity_category_id']);
    }

    public function test_plate_number_and_document_numbers_must_be_digits_only(): void
    {
        $payload = $this->validPayload([
            'plate_numbers' => '12AB',
            'documents' => [
                'registration_card' => ['number' => 'RC-1', 'valid_to' => '2027-01-01'],
                'fitness_document' => ['number' => '1002', 'valid_to' => '2027-01-01'],
                'inspection_certificate' => ['number' => '1003', 'valid_to' => '2027-01-01'],
                'insurance' => ['number' => '1004', 'valid_to' => '2027-01-01'],
            ],
            'entry_permits' => [
                ['area_name' => 'Riyadh', 'permit_number' => 'ABC', 'valid_to' => '2027-01-01'],
            ],
        ]);

        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $payload, ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'plate_numbers',
                'documents.registration_card.number',
                'entry_permits.0.permit_number',
            ]);
    }

    public function test_update_changes_editable_fields_and_replaces_entry_permits(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->validPayload(), ['Accept' => 'application/json'])->assertCreated();
        $vehicle = Vehicle::firstOrFail();

        $payload = $this->validPayload([
            'plate_letters' => 'XYZ',
            'plate_numbers' => '9999',
            'photo_front' => null,
            'photo_back' => null,
            'documents' => [
                'registration_card' => ['number' => '1009', 'valid_to' => '2028-01-01'],
                'fitness_document' => ['number' => '1002', 'valid_to' => '2027-01-01'],
                'inspection_certificate' => ['number' => '1003', 'valid_to' => '2027-01-01'],
                'insurance' => ['number' => '1004', 'valid_to' => '2027-01-01'],
            ],
            'entry_permits' => [
                ['area_name' => 'Dammam', 'permit_number' => '2009', 'valid_to' => '2029-01-01'],
            ],
        ]);
        $payload['_method'] = 'PATCH';

        $this->actingAs($this->admin, 'web')
            ->post("/api/v1/vehicles/{$vehicle->id}", $payload, ['Accept' => 'application/json'])
            ->assertOk();

        $vehicle->refresh();
        $this->assertSame('XYZ', $vehicle->plate_letters);
        $this->assertSame('1009', $vehicle->documents()->where('type', 'registration_card')->value('document_number'));
        $this->assertSame(1, $vehicle->entryPermits()->count());
        $this->assertSame('Dammam', $vehicle->entryPermits()->first()->area_name);
    }

    public function test_editing_a_vehicle_keeps_existing_entry_permit_attachments(): void
    {
        $payload = $this->validPayload([
            'entry_permits' => [
                ['area_name' => 'Riyadh', 'permit_number' => '2001', 'valid_to' => '2027-01-01', 'attachment' => UploadedFile::fake()->create('permit.pdf', 20, 'application/pdf')],
            ],
        ]);
        $this->actingAs($this->admin, 'web')->post('/api/v1/vehicles', $payload, ['Accept' => 'application/json'])->assertCreated();

        $vehicle = Vehicle::firstOrFail();
        $permit = $vehicle->entryPermits()->firstOrFail();
        $this->assertNotNull($permit->attachment_path);
        $originalPath = $permit->attachment_path;

        // Re-save: same permit by id, no new file + one brand-new permit.
        $update = $this->validPayload([
            'photo_front' => null,
            'photo_back' => null,
            'entry_permits' => [
                ['id' => $permit->id, 'area_name' => 'Riyadh (edited)', 'permit_number' => '2001', 'valid_to' => '2027-01-01'],
                ['area_name' => 'Jeddah', 'permit_number' => '2002', 'valid_to' => '2027-01-01'],
            ],
        ]);
        $update['_method'] = 'PATCH';

        $this->actingAs($this->admin, 'web')
            ->post("/api/v1/vehicles/{$vehicle->id}", $update, ['Accept' => 'application/json'])
            ->assertOk();

        $permit->refresh();
        $this->assertSame($originalPath, $permit->attachment_path, 'attachment kept across edit');
        Storage::disk('local')->assertExists($originalPath);
        $this->assertSame('Riyadh (edited)', $permit->area_name);
        $this->assertSame(2, $vehicle->entryPermits()->count());
    }

    public function test_editing_a_vehicle_deletes_removed_entry_permits_and_their_files(): void
    {
        $payload = $this->validPayload([
            'entry_permits' => [
                ['area_name' => 'Riyadh', 'permit_number' => '2001', 'valid_to' => '2027-01-01', 'attachment' => UploadedFile::fake()->create('p.pdf', 20, 'application/pdf')],
            ],
        ]);
        $this->actingAs($this->admin, 'web')->post('/api/v1/vehicles', $payload, ['Accept' => 'application/json'])->assertCreated();

        $vehicle = Vehicle::firstOrFail();
        $permit = $vehicle->entryPermits()->firstOrFail();
        $path = $permit->attachment_path;

        $update = $this->validPayload(['photo_front' => null, 'photo_back' => null, 'entry_permits' => []]);
        $update['_method'] = 'PATCH';

        $this->actingAs($this->admin, 'web')
            ->post("/api/v1/vehicles/{$vehicle->id}", $update, ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertSame(0, $vehicle->entryPermits()->count());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_plate_letters_are_stored_and_returned_upper_case(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->validPayload(['plate_letters' => 'abc']), ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('data.plate_letters', 'ABC');

        $this->assertSame('ABC', Vehicle::firstOrFail()->plate_letters);
        $this->assertSame('ABC 1234', Vehicle::firstOrFail()->plate());
    }

    public function test_system_admin_can_create_a_deactivated_vehicle(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->validPayload(['operational_status' => 'deactivated']), ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame('deactivated', Vehicle::firstOrFail()->operational_status);
    }

    public function test_data_entry_cannot_create_a_deactivated_vehicle(): void
    {
        $this->actingAs($this->dataEntry, 'web')
            ->post('/api/v1/vehicles', $this->validPayload(['operational_status' => 'deactivated']), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['operational_status']);
    }

    public function test_data_entry_can_set_maintenance_but_not_deactivate(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($this->dataEntry, 'web')
            ->patchJson("/api/v1/vehicles/{$vehicle->id}/status", ['status' => 'on_maintenance'])
            ->assertOk();
        $this->assertSame('on_maintenance', $vehicle->refresh()->operational_status);

        $this->actingAs($this->dataEntry, 'web')
            ->patchJson("/api/v1/vehicles/{$vehicle->id}/status", ['status' => 'deactivated'])
            ->assertStatus(422);
        $this->assertSame('on_maintenance', $vehicle->refresh()->operational_status);
    }

    public function test_system_admin_can_deactivate(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/vehicles/{$vehicle->id}/status", ['status' => 'deactivated'])
            ->assertOk();

        $this->assertSame('deactivated', $vehicle->refresh()->operational_status);
    }

    public function test_stats_endpoint_returns_category_and_availability_counts(): void
    {
        $hookLift = VehicleCategory::where('slug', 'hook_lift')->value('id');
        $dumpTruck = VehicleCategory::where('slug', 'dump_truck')->value('id');
        Vehicle::factory()->count(2)->create(['vehicle_category_id' => $hookLift]);
        Vehicle::factory()->onMaintenance()->create(['vehicle_category_id' => $dumpTruck]);

        $response = $this->actingAs($this->admin, 'web')->getJson('/api/v1/vehicles/stats');

        $response->assertOk()
            ->assertJsonPath('data.by_category.hook_lift', 2)
            ->assertJsonPath('data.availability.on_maintenance', 1)
            ->assertJsonPath('data.availability.on_trip', 0);
    }

    public function test_export_returns_xlsx_and_pdf(): void
    {
        Vehicle::factory()->count(2)->create();

        $this->actingAs($this->admin, 'web')
            ->get('/api/v1/vehicles/export?format=xlsx')
            ->assertOk()
            ->assertDownload('vehicles.xlsx');

        $this->actingAs($this->admin, 'web')
            ->get('/api/v1/vehicles/export?format=pdf')
            ->assertOk();
    }

    public function test_document_download_streams_the_private_file(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->validPayload(), ['Accept' => 'application/json'])->assertCreated();

        $vehicle = Vehicle::firstOrFail();
        $doc = $vehicle->documents()->where('type', 'registration_card')->firstOrFail();

        $this->actingAs($this->admin, 'web')
            ->get("/api/v1/vehicles/{$vehicle->id}/documents/{$doc->id}/download")
            ->assertOk();
    }

    /**
     * An untouched/cleared Quill editor submits `<p><br></p>`, not an
     * empty string — `nullable|string`-valid, so it always got saved and
     * always showed the "Additional Data" section with a heading over
     * nothing on the view page. Real bug flagged by the user across every
     * module using this same rich-text field (Users/Drivers/Vehicles/
     * Assets) — this is the create-time normalization catching it.
     */
    public function test_an_empty_quill_editor_is_stored_as_null_not_empty_markup(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/vehicles', $this->validPayload(['additional_data' => '<p><br></p>']), ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertNull(Vehicle::firstOrFail()->additional_data);
    }
}
