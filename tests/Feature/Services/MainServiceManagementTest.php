<?php

namespace Tests\Feature\Services;

use App\Domain\Services\Actions\UpdateMainServiceAction;
use App\Domain\Services\Actions\UpdateMainServiceStatusAction;
use App\Domain\Services\Exceptions\MainServiceInUseException;
use App\Models\MainService;
use App\Models\SubService;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\AssetCapacityCategory;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FRD V01.14 §1.9.1. Every test acts as ONE user — switching actingAs()
 * users inside a single test method leaks the first user (see CLAUDE.md).
 */
class MainServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        // Tenant::created gives the tenant its six default vehicle categories.
        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->admin = User::factory()->create(['email' => 'admin@gcm.test']);
        $this->admin->assignRole('system_admin');
    }

    private function categoryIds(string ...$slugs): array
    {
        return VehicleCategory::whereIn('slug', $slugs)->pluck('id')->all();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Solid Waste Transport',
            'service_type' => 'non_hazardous',
            'vehicle_category_ids' => $this->categoryIds('hook_lift', 'compactor'),
            'operational_status' => 'active',
        ], $overrides);
    }

    // -------------------------------------------------------------- create

    public function test_it_creates_a_main_service_with_its_supported_vehicle_categories(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/main-services', $this->payload(['additional_data' => '<p>Notes</p>']))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Solid Waste Transport')
            ->assertJsonPath('data.service_type', 'non_hazardous')
            ->assertJsonPath('data.operational_status', 'active')
            ->assertJsonPath('data.additional_data', '<p>Notes</p>')
            ->assertJsonPath('data.updated_by_name', $this->admin->name)
            ->assertJsonCount(2, 'data.vehicle_categories');

        $service = MainService::find($response->json('data.id'));
        $this->assertSame($this->tenant->id, $service->tenant_id);
        $this->assertEqualsCanonicalizing($this->categoryIds('hook_lift', 'compactor'), $service->vehicleCategories->pluck('id')->all());
    }

    public function test_each_type_of_service_is_accepted_and_nothing_else(): void
    {
        $this->actingAs($this->admin, 'web');

        foreach (MainService::TYPES as $i => $type) {
            $this->postJson('/api/v1/main-services', $this->payload(['name' => "S{$i}", 'service_type' => $type]))->assertCreated();
        }

        $this->postJson('/api/v1/main-services', $this->payload(['name' => 'Bad', 'service_type' => 'medical']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['service_type']);
    }

    public function test_it_can_be_created_deactivated(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/main-services', $this->payload(['operational_status' => 'deactivated']))
            ->assertCreated()
            ->assertJsonPath('data.operational_status', 'deactivated');
    }

    public function test_required_fields(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/main-services', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'service_type', 'vehicle_category_ids', 'operational_status']);
    }

    public function test_at_least_one_vehicle_category_is_required(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/main-services', $this->payload(['vehicle_category_ids' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vehicle_category_ids']);
    }

    public function test_the_name_is_unique_per_tenant(): void
    {
        MainService::factory()->create(['name' => 'Solid Waste Transport']);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/main-services', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * A real HTML form sends ids as strings (see CLAUDE.md) — must work
     * exactly like ints.
     */
    public function test_vehicle_category_ids_sent_as_strings_are_accepted(): void
    {
        $ids = array_map('strval', $this->categoryIds('dump_truck'));

        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/main-services', $this->payload(['vehicle_category_ids' => $ids]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonCount(1, 'data.vehicle_categories');
    }

    // -------------------------------------------------------------- read

    public function test_the_list_shows_sub_services_and_the_search_covers_them(): void
    {
        $main = MainService::factory()->create(['name' => 'Hazardous Transport']);
        SubService::factory()->forService($main)->create(['name' => 'Oily sludge']);
        MainService::factory()->create(['name' => 'Water Supply']);

        $this->actingAs($this->admin, 'web');

        $this->getJson('/api/v1/main-services')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Hazardous Transport')
            ->assertJsonPath('data.0.sub_services.0.name', 'Oily sludge');

        // The sub-services column is on screen, so searching it finds the main.
        $this->getJson('/api/v1/main-services?search=sludge')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Hazardous Transport');
    }

    public function test_the_list_filters_by_type_and_status(): void
    {
        MainService::factory()->ofType('hazardous')->create(['name' => 'H']);
        MainService::factory()->supplying()->deactivated()->create(['name' => 'S']);

        $this->actingAs($this->admin, 'web');

        $this->getJson('/api/v1/main-services?service_type=hazardous')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'H');
        $this->getJson('/api/v1/main-services?operational_status=deactivated')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'S');
    }

    public function test_the_details_carry_zero_stats_and_the_embedded_containers_of_supported_vehicles(): void
    {
        $compactor = VehicleCategory::where('slug', 'compactor')->first();
        $capacity = AssetCapacityCategory::factory()->container()->create(['name' => 'Compactor body', 'capacity_cbm' => 10, 'capacity_ton' => 4]);
        Vehicle::factory()->create([
            'vehicle_category_id' => $compactor->id,
            'has_embedded_container' => true,
            'embedded_container_type' => 'container',
            'embedded_asset_capacity_category_id' => $capacity->id,
        ]);

        $main = MainService::factory()->create();
        $main->vehicleCategories()->sync([$compactor->id]);

        $this->actingAs($this->admin, 'web')
            ->getJson("/api/v1/main-services/{$main->id}")
            ->assertOk()
            ->assertJsonPath('data.stats.trips', 0)
            ->assertJsonPath('data.stats.projects', 0)
            ->assertJsonPath('data.in_use', false)
            ->assertJsonPath('meta.embedded_capacities.0.capacity_name', 'Compactor body')
            ->assertJsonPath('meta.embedded_capacities.0.asset_type', 'container')
            ->assertJsonPath('meta.embedded_capacities.0.vehicles', 1);
    }

    // -------------------------------------------------------------- update

    public function test_the_name_additional_data_and_categories_are_editable(): void
    {
        $main = MainService::factory()->create();
        $main->vehicleCategories()->sync($this->categoryIds('hook_lift'));

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/main-services/{$main->id}", [
                'name' => 'Renamed',
                'additional_data' => '<p>x</p>',
                'vehicle_category_ids' => $this->categoryIds('water_tanker', 'dyna_box'),
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.additional_data', '<p>x</p>')
            ->assertJsonCount(2, 'data.vehicle_categories');
    }

    public function test_the_type_of_service_is_locked_after_creation(): void
    {
        $main = MainService::factory()->ofType('hazardous')->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/main-services/{$main->id}", ['name' => 'Renamed', 'service_type' => 'supplying'])
            ->assertOk()
            ->assertJsonPath('data.service_type', 'hazardous');
    }

    public function test_a_blank_quill_editor_clears_the_additional_data(): void
    {
        $main = MainService::factory()->create(['additional_data' => '<p>old</p>']);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/main-services/{$main->id}", ['name' => $main->name, 'additional_data' => '<p><br></p>'])
            ->assertOk()
            ->assertJsonPath('data.additional_data', null);
    }

    public function test_a_service_in_a_purchase_order_cannot_be_edited(): void
    {
        $main = $this->mainInPurchaseOrder();

        $this->expectException(MainServiceInUseException::class);
        app(UpdateMainServiceAction::class)->execute($main, ['name' => 'Renamed'], $this->admin);
    }

    // -------------------------------------------------------------- status

    public function test_deactivating_a_main_service_deactivates_all_its_sub_services(): void
    {
        $main = MainService::factory()->create();
        $a = SubService::factory()->forService($main)->create();
        $b = SubService::factory()->forService($main)->create();
        $other = SubService::factory()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/main-services/{$main->id}/status", ['status' => 'deactivated'])
            ->assertOk()
            ->assertJsonPath('data.operational_status', 'deactivated');

        $this->assertSame('deactivated', $a->fresh()->operational_status);
        $this->assertSame('deactivated', $b->fresh()->operational_status);
        $this->assertSame($this->admin->id, $a->fresh()->updated_by);
        $this->assertSame('active', $other->fresh()->operational_status);
    }

    public function test_reactivating_a_main_service_does_not_reactivate_its_sub_services(): void
    {
        $main = MainService::factory()->deactivated()->create();
        $sub = SubService::factory()->forService($main)->deactivated()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/main-services/{$main->id}/status", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.operational_status', 'active');

        $this->assertSame('deactivated', $sub->fresh()->operational_status);
    }

    public function test_a_service_in_a_purchase_order_cannot_be_deactivated(): void
    {
        $main = $this->mainInPurchaseOrder();

        $this->expectException(MainServiceInUseException::class);
        app(UpdateMainServiceStatusAction::class)->execute($main, 'deactivated', $this->admin);
    }

    public function test_a_service_whose_sub_service_has_an_ongoing_trip_cannot_be_deactivated(): void
    {
        $main = MainService::factory()->create();
        $sub = new class extends SubService
        {
            protected $table = 'sub_services';

            public function hasOngoingTrips(): bool
            {
                return true;
            }
        };
        $sub->forceFill(['main_service_id' => $main->id, 'name' => 'Busy', 'asset_type' => 'container', 'operational_status' => 'active'])->save();
        $main->setRelation('subServices', collect([$sub]));

        try {
            app(UpdateMainServiceStatusAction::class)->execute($main, 'deactivated', $this->admin);
            $this->fail('Expected MainServiceInUseException');
        } catch (MainServiceInUseException) {
            $this->assertSame('active', $main->fresh()->operational_status);
            $this->assertSame('active', SubService::find($sub->id)->operational_status);
        }
    }

    public function test_status_cannot_be_set_through_update(): void
    {
        $main = MainService::factory()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/main-services/{$main->id}", ['name' => 'X', 'operational_status' => 'deactivated'])
            ->assertOk()
            ->assertJsonPath('data.operational_status', 'active');
    }

    public function test_only_active_and_deactivated_are_valid_statuses(): void
    {
        $main = MainService::factory()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/main-services/{$main->id}/status", ['status' => 'on_maintenance'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    // -------------------------------------------------------------- vehicle categories

    public function test_a_vehicle_category_a_main_service_supports_cannot_be_deleted(): void
    {
        $custom = VehicleCategory::factory()->create();
        $main = MainService::factory()->create();
        $main->vehicleCategories()->sync([$custom->id]);

        $this->actingAs($this->admin, 'web')
            ->deleteJson("/api/v1/vehicle-categories/{$custom->id}")
            ->assertStatus(422);

        $this->assertNotNull($custom->fresh());
        $this->assertCount(1, $main->vehicleCategories()->get());
    }

    // -------------------------------------------------------------- helpers

    /**
     * POs don't exist yet — a subclass stands in for a main service that a
     * PO includes (same approach as the facility in-use test).
     */
    private function mainInPurchaseOrder(): MainService
    {
        $main = new class extends MainService
        {
            protected $table = 'main_services';

            public function hasPurchaseOrders(): bool
            {
                return true;
            }
        };
        $main->forceFill(['name' => 'In a PO', 'service_type' => 'hazardous', 'operational_status' => 'active'])->save();

        return $main;
    }
}
