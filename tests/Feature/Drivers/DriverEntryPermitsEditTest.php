<?php

namespace Tests\Feature\Drivers;

use App\Models\Driver;
use App\Models\DriverEntryPermit;
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

/**
 * The driver edit form manages the driver's entry permits ("تصاريح الدخول"), like the vehicle form: the
 * submitted list replaces the stored one — existing rows are matched by id (a new file replaces the old one,
 * no file keeps it), new rows are added and rows left out are removed together with their file. A request that
 * says nothing about permits leaves them alone.
 */
class DriverEntryPermitsEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Driver $driver;

    private VehicleCategory $category;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);

        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));

        $this->admin = User::factory()->create();
        $this->admin->assignRole('system_admin');

        $this->category = VehicleCategory::where('slug', 'dump_truck')->firstOrFail();
        $this->vehicle = Vehicle::factory()->create(['vehicle_category_id' => $this->category->id]);

        $user = User::factory()->create();
        $user->assignRole('driver');
        $this->driver = Driver::create([
            'user_id' => $user->id,
            'default_vehicle_id' => $this->vehicle->id,
            'residence_number' => 'RES-000', 'residence_valid_to' => '2030-01-01',
            'license_number' => 'LIC-000', 'license_valid_to' => '2030-01-01',
            'operational_license_number' => 'OPL-000', 'operational_license_valid_to' => '2030-01-01',
            'insurance_number' => 'INS-000', 'insurance_valid_to' => '2030-01-01',
        ]);
        $this->driver->qualifiedVehicleCategories()->sync([$this->category->id]);
    }

    private function permit(string $area, ?string $file = 'driver-documents/old.pdf'): DriverEntryPermit
    {
        if ($file) {
            Storage::disk('local')->put($file, 'old');
        }

        return $this->driver->entryPermits()->create([
            'area_name' => $area, 'permit_number' => '123', 'valid_to' => '2030-06-01', 'attachment' => $file,
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Driver', 'phone' => '01000000000',
            'vehicle_category_ids' => [$this->category->id],
            'default_vehicle_id' => $this->vehicle->id,
            'residence_number' => 'RES-001', 'residence_valid_to' => '2030-01-01',
            'license_number' => 'LIC-001', 'license_valid_to' => '2030-01-01',
            'operational_license_number' => 'OPL-001', 'operational_license_valid_to' => '2030-01-01',
            'insurance_number' => 'INS-001', 'insurance_valid_to' => '2030-01-01',
        ], $extra);
    }

    private function patchDriver(array $payload)
    {
        return $this->actingAs($this->admin, 'web')->patchJson("/api/v1/drivers/{$this->driver->id}", $payload);
    }

    public function test_an_update_that_says_nothing_about_permits_leaves_them_alone(): void
    {
        $this->permit('Port');

        $this->patchDriver($this->payload())->assertOk();

        $this->assertSame(['Port'], $this->driver->entryPermits()->pluck('area_name')->all());
        Storage::disk('local')->assertExists('driver-documents/old.pdf');
    }

    public function test_the_sync_flag_with_no_rows_removes_every_permit_and_its_file(): void
    {
        $this->permit('Port');

        $this->patchDriver($this->payload(['sync_entry_permits' => true]))->assertOk()->assertJsonPath('data.entry_permits', []);

        $this->assertSame(0, $this->driver->entryPermits()->count());
        Storage::disk('local')->assertMissing('driver-documents/old.pdf');
    }

    public function test_existing_permits_are_updated_in_place_new_ones_added_and_missing_ones_removed(): void
    {
        $keep = $this->permit('Port');
        $drop = $this->permit('Airport', 'driver-documents/drop.pdf');

        $this->patchDriver($this->payload([
            'sync_entry_permits' => true,
            'entry_permits' => [
                ['id' => $keep->id, 'area_name' => 'Port (renewed)', 'permit_number' => '999', 'valid_to' => '2031-01-01'],
                ['area_name' => 'Industrial City', 'permit_number' => '555', 'valid_to' => '2031-02-02'],
            ],
        ]))->assertOk();

        $permits = $this->driver->entryPermits()->orderBy('id')->get();
        $this->assertCount(2, $permits);
        $this->assertSame($keep->id, $permits[0]->id);
        $this->assertSame('Port (renewed)', $permits[0]->area_name);
        $this->assertSame('999', $permits[0]->permit_number);
        $this->assertSame('Industrial City', $permits[1]->area_name);
        $this->assertNull(DriverEntryPermit::find($drop->id));

        // No new file was sent for the kept one — its file stays; the removed one's file is gone.
        Storage::disk('local')->assertExists('driver-documents/old.pdf');
        Storage::disk('local')->assertMissing('driver-documents/drop.pdf');
    }

    public function test_a_new_file_replaces_the_old_one(): void
    {
        $permit = $this->permit('Port');

        $this->actingAs($this->admin, 'web')
            ->post("/api/v1/drivers/{$this->driver->id}", $this->payload([
                '_method' => 'PATCH',
                'sync_entry_permits' => '1',
                'entry_permits' => [[
                    'id' => (string) $permit->id, 'area_name' => 'Port', 'permit_number' => '123', 'valid_to' => '2030-06-01',
                    'attachment' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
                ]],
            ]), ['Accept' => 'application/json'])
            ->assertOk();

        $fresh = $permit->fresh();
        $this->assertNotSame('driver-documents/old.pdf', $fresh->attachment);
        Storage::disk('local')->assertExists($fresh->attachment);
        Storage::disk('local')->assertMissing('driver-documents/old.pdf');
    }

    public function test_an_id_of_another_drivers_permit_is_never_touched(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('driver');
        $other = Driver::create([
            'user_id' => $otherUser->id,
            'residence_number' => 'R', 'residence_valid_to' => '2030-01-01',
            'license_number' => 'L', 'license_valid_to' => '2030-01-01',
            'operational_license_number' => 'O', 'operational_license_valid_to' => '2030-01-01',
            'insurance_number' => 'I', 'insurance_valid_to' => '2030-01-01',
        ]);
        $foreign = $other->entryPermits()->create(['area_name' => 'Theirs', 'permit_number' => '1', 'valid_to' => '2030-01-01']);

        $this->patchDriver($this->payload([
            'sync_entry_permits' => true,
            'entry_permits' => [['id' => $foreign->id, 'area_name' => 'Hijack', 'permit_number' => '2', 'valid_to' => '2031-01-01']],
        ]))->assertOk();

        $this->assertSame('Theirs', $foreign->fresh()->area_name);
        $this->assertSame(['Hijack'], $this->driver->entryPermits()->pluck('area_name')->all());
    }

    public function test_the_permit_rows_are_validated(): void
    {
        $this->patchDriver($this->payload(['entry_permits' => [['area_name' => '', 'permit_number' => '', 'valid_to' => 'nope']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['entry_permits.0.area_name', 'entry_permits.0.permit_number', 'entry_permits.0.valid_to']);
    }

    public function test_the_auditor_cannot_change_permits(): void
    {
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');

        $this->actingAs($auditor, 'web')
            ->patchJson("/api/v1/drivers/{$this->driver->id}", $this->payload(['sync_entry_permits' => true]))
            ->assertForbidden();
    }

    public function test_the_edit_page_has_the_permits_card(): void
    {
        $this->withoutVite();

        $this->actingAs($this->admin, 'web')->get("/app/driver/edit/{$this->driver->id}")
            ->assertOk()
            ->assertSee('id="entry-permits-list"', false)
            ->assertSee('id="add-entry-permit"', false)
            ->assertSee('id="entry-permit-row-template"', false);
    }
}
