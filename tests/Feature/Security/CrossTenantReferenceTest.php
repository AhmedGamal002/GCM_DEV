<?php

namespace Tests\Feature\Security;

use App\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehicleCategory;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vehicle categories belong to one company (tenant). The `exists:vehicle_categories,id` rules used to look at the whole
 * table, so another company's category id was accepted — and answered differently from a missing one, which also made
 * ids of other companies guessable.
 */
class CrossTenantReferenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $ownCategory;

    private int $foreignCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $mine = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        $other = Tenant::create(['name' => 'Rival', 'slug' => 'rival', 'status' => 'active']);
        app()->instance('tenant', $mine);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('system_admin');

        // Every new tenant is seeded with its own set of categories.
        $this->ownCategory = VehicleCategory::withoutGlobalScope(BelongsToTenant::class)->where('tenant_id', $mine->id)->value('id');
        $this->foreignCategory = VehicleCategory::withoutGlobalScope(BelongsToTenant::class)->where('tenant_id', $other->id)->value('id');

        $this->assertNotNull($this->ownCategory);
        $this->assertNotNull($this->foreignCategory);
    }

    public function test_a_vehicle_cannot_use_another_companys_category(): void
    {
        $this->actingAs($this->admin, 'web')->postJson('/api/v1/vehicles', ['vehicle_category_id' => $this->foreignCategory])
            ->assertJsonValidationErrors('vehicle_category_id');

        $this->actingAs($this->admin, 'web')->postJson('/api/v1/vehicles', ['vehicle_category_id' => $this->ownCategory])
            ->assertJsonMissingValidationErrors('vehicle_category_id');
    }

    public function test_a_driver_cannot_be_qualified_for_another_companys_category(): void
    {
        $this->actingAs($this->admin, 'web')->postJson('/api/v1/drivers', ['vehicle_category_ids' => [$this->foreignCategory]])
            ->assertJsonValidationErrors('vehicle_category_ids.0');

        $this->actingAs($this->admin, 'web')->postJson('/api/v1/drivers', ['vehicle_category_ids' => [$this->ownCategory]])
            ->assertJsonMissingValidationErrors('vehicle_category_ids.0');
    }

    public function test_an_asset_cannot_be_made_compatible_with_another_companys_category(): void
    {
        $this->actingAs($this->admin, 'web')->postJson('/api/v1/assets', ['compatible_vehicle_category_ids' => [$this->foreignCategory]])
            ->assertJsonValidationErrors('compatible_vehicle_category_ids.0');

        $this->actingAs($this->admin, 'web')->postJson('/api/v1/assets', ['compatible_vehicle_category_ids' => [$this->ownCategory]])
            ->assertJsonMissingValidationErrors('compatible_vehicle_category_ids.0');
    }

    public function test_editing_a_vehicle_or_driver_has_the_same_rule(): void
    {
        $vehicle = \App\Models\Vehicle::factory()->create();
        $driverUser = User::factory()->create();
        $driverUser->assignRole('driver');
        $driver = \App\Models\Driver::create([
            'user_id' => $driverUser->id,
            'residence_number' => '1', 'residence_valid_to' => now()->addYear(),
            'license_number' => '1', 'license_valid_to' => now()->addYear(),
            'operational_license_number' => '1', 'operational_license_valid_to' => now()->addYear(),
            'insurance_number' => '1', 'insurance_valid_to' => now()->addYear(),
        ]);

        $this->actingAs($this->admin, 'web')->patchJson("/api/v1/vehicles/{$vehicle->id}", ['vehicle_category_id' => $this->foreignCategory])
            ->assertJsonValidationErrors('vehicle_category_id');

        $this->actingAs($this->admin, 'web')->patchJson("/api/v1/drivers/{$driver->id}", ['vehicle_category_ids' => [$this->foreignCategory]])
            ->assertJsonValidationErrors('vehicle_category_ids.0');
    }
}
