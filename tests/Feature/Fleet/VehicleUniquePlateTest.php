<?php

namespace Tests\Feature\Fleet;

use App\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VehicleCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleUniquePlateTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(VehicleCategorySeeder::class);

        $this->tenantA = Tenant::create(['name' => 'A', 'slug' => 'a', 'status' => 'active']);
        $this->tenantB = Tenant::create(['name' => 'B', 'slug' => 'b', 'status' => 'active']);
    }

    private function payload(): array
    {
        app()->instance('tenant', $this->tenantA);

        return [
            'plate_letters' => 'ABC',
            'plate_numbers' => '1234',
            'vehicle_category_id' => VehicleCategory::where('slug', 'hook_lift')->value('id'),
            'has_embedded_container' => '0',
            'operational_status' => 'active',
            'affiliation' => 'gcm',
            'documents' => [
                'registration_card' => ['number' => '11', 'valid_to' => '2027-01-01'],
                'fitness_document' => ['number' => '12', 'valid_to' => '2027-01-01'],
                'inspection_certificate' => ['number' => '13', 'valid_to' => '2027-01-01'],
                'insurance' => ['number' => '14', 'valid_to' => '2027-01-01'],
            ],
        ];
    }

    private function adminFor(Tenant $tenant): User
    {
        app()->instance('tenant', $tenant);
        $user = User::factory()->create(['email' => "admin-{$tenant->slug}@test.test"]);
        $user->assignRole('system_admin');

        return $user;
    }

    public function test_duplicate_plate_in_same_tenant_is_rejected(): void
    {
        $admin = $this->adminFor($this->tenantA);

        $this->actingAs($admin, 'web')->post('/api/v1/vehicles', $this->payload(), ['Accept' => 'application/json'])->assertCreated();
        $this->actingAs($admin, 'web')->post('/api/v1/vehicles', $this->payload(), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['plate_numbers']);
    }

    public function test_same_plate_in_a_different_tenant_is_allowed(): void
    {
        $adminA = $this->adminFor($this->tenantA);
        app()->instance('tenant', $this->tenantA);
        $this->actingAs($adminA, 'web')->post('/api/v1/vehicles', $this->payload(), ['Accept' => 'application/json'])->assertCreated();

        $adminB = $this->adminFor($this->tenantB);
        app()->instance('tenant', $this->tenantB);
        $payloadB = $this->payload();
        $payloadB['vehicle_category_id'] = VehicleCategory::where('slug', 'hook_lift')->value('id');

        $this->actingAs($adminB, 'web')->post('/api/v1/vehicles', $payloadB, ['Accept' => 'application/json'])->assertCreated();

        $withSamePlate = Vehicle::withoutGlobalScope(BelongsToTenant::class)
            ->where('plate_letters', 'ABC')->where('plate_numbers', '1234')->count();
        $this->assertSame(2, $withSamePlate);
    }
}
