<?php

namespace Tests\Feature\Drivers;

use App\Models\Driver;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriverCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $systemAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->seed(RoleSeeder::class);

        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
        app()->instance('tenant', $tenant);

        $this->systemAdmin = User::factory()->create();
        $this->systemAdmin->assignRole('system_admin');
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
                // missing residence/license/operational_license/insurance
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'residence_number', 'license_number', 'operational_license_number', 'insurance_number',
        ]);
    }
}
