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

    /**
     * The Sub Services accordion is paginated over the main services: one page
     * of mains comes back with their sub-services (and everything a row shows),
     * the search/type/status filters apply across every page, and the total
     * stays the true count.
     */
    public function test_the_sub_services_page_gets_one_page_of_mains_with_full_sub_service_rows(): void
    {
        foreach (range(1, 25) as $i) {
            $main = MainService::factory()->create(['name' => sprintf('Main %02d', $i), 'service_type' => $i % 2 ? 'hazardous' : 'non_hazardous']);
            SubService::factory()->forService($main)->create([
                'name' => sprintf('Sub %02d', $i), 'asset_type' => 'tank', 'requires_recycle_receipt' => true, 'skip_color_code' => 'gray_plastic',
            ]);
        }

        $this->actingAs($this->admin, 'web');

        $page = $this->getJson('/api/v1/main-services?per_page=10&page=2&sort_by=name')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('data.0.name', 'Main 11')
            ->assertJsonPath('data.0.sub_services.0.name', 'Sub 11')
            ->assertJsonPath('data.0.sub_services.0.asset_type', 'tank')
            ->assertJsonPath('data.0.sub_services.0.requires_recycle_receipt', true)
            ->assertJsonPath('data.0.sub_services.0.skip_color_code', 'gray_plastic')
            ->assertJsonPath('data.0.sub_services.0.skip_color_hex', '#8d99ae');
        $this->assertNotNull($page->json('data.0.sub_services.0.skip_color_label'));

        // type filter + paging: 13 hazardous mains over two pages of 10
        $this->getJson('/api/v1/main-services?per_page=10&service_type=hazardous&page=2')
            ->assertJsonPath('meta.total', 13)
            ->assertJsonCount(3, 'data');

        // search reaches a sub-service on a page the user is not looking at
        $this->getJson('/api/v1/main-services?per_page=10&search=Sub 24')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Main 24');
    }

    /**
     * A main service can have hundreds of sub-services: the previews ask for
     * `subs_limit` and get the first N plus the true count; without it every
     * sub-service comes back as before; the details endpoint returns only the
     * count (its table pages through /sub-services).
     */
    public function test_a_main_service_with_many_sub_services_returns_a_preview_and_the_true_count(): void
    {
        $big = MainService::factory()->create(['name' => 'Big']);
        foreach (range(1, 30) as $i) {
            SubService::factory()->forService($big)->create(['name' => sprintf('Bulk %02d', $i)]);
        }
        $small = MainService::factory()->create(['name' => 'Small']);
        SubService::factory()->forService($small)->create(['name' => 'Only one']);

        $this->actingAs($this->admin, 'web');

        $rows = collect($this->getJson('/api/v1/main-services?subs_limit=10&sort_by=name')->assertOk()->json('data'))->keyBy('name');
        $this->assertCount(10, $rows['Big']['sub_services']);
        $this->assertSame('Bulk 01', $rows['Big']['sub_services'][0]['name']);
        $this->assertSame(30, $rows['Big']['sub_services_count']);
        $this->assertCount(1, $rows['Small']['sub_services']);
        $this->assertSame(1, $rows['Small']['sub_services_count']);

        // without a limit: every sub-service, as before
        $all = collect($this->getJson('/api/v1/main-services')->json('data'))->keyBy('name');
        $this->assertCount(30, $all['Big']['sub_services']);

        // a limit never exceeds 50
        $this->getJson('/api/v1/main-services?subs_limit=9999')->assertOk();

        // a search that only matches a sub-service narrows the preview — and the count — to the matches
        $narrow = collect($this->getJson('/api/v1/main-services?subs_limit=10&subs_match=1&search=Bulk 2')->json('data'))->keyBy('name');
        $this->assertSame(10, $narrow['Big']['sub_services_count']);      // Bulk 20..29
        $this->assertSame('Bulk 20', $narrow['Big']['sub_services'][0]['name']);
        $this->assertArrayNotHasKey('Small', $narrow->all());

        // a search that matches the MAIN service keeps all of its sub-services
        $byMain = collect($this->getJson('/api/v1/main-services?subs_limit=10&subs_match=1&search=Big')->json('data'))->keyBy('name');
        $this->assertSame(30, $byMain['Big']['sub_services_count']);

        // details: the count, not the list
        $this->getJson("/api/v1/main-services/{$big->id}")
            ->assertOk()
            ->assertJsonPath('data.sub_services_count', 30)
            ->assertJsonMissingPath('data.sub_services');

        // the sub-services endpoint pages through them and filters by type/status
        $this->getJson("/api/v1/sub-services?main_service_id={$big->id}&per_page=10&page=3&sort_by=name")
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('data.0.name', 'Bulk 21');
    }

    /**
     * The Sub Services page's "Search in" selector: `search_in` limits the
     * search to the main services' names, to their sub-services' names, or (the
     * default) both.
     */
    public function test_the_search_can_be_limited_to_main_services_or_to_sub_services(): void
    {
        // "Alpha" appears in a MAIN name and in a SUB name of another service.
        $alpha = MainService::factory()->create(['name' => 'Alpha Haulage']);
        SubService::factory()->forService($alpha)->create(['name' => 'General']);
        SubService::factory()->forService($alpha)->create(['name' => 'Special']);
        $beta = MainService::factory()->create(['name' => 'Beta Works']);
        SubService::factory()->forService($beta)->create(['name' => 'Alpha skip']);
        SubService::factory()->forService($beta)->create(['name' => 'Other']);

        $this->actingAs($this->admin, 'web');
        $names = fn (string $qs) => collect($this->getJson('/api/v1/main-services?sort_by=name&subs_limit=10&'.$qs)->assertOk()->json('data'))->pluck('name')->all();

        // default / all: the main OR any sub-service
        $this->assertSame(['Alpha Haulage', 'Beta Works'], $names('search=Alpha'));
        $this->assertSame(['Alpha Haulage', 'Beta Works'], $names('search=Alpha&search_in=all'));
        // main only: the main service's own name
        $this->assertSame(['Alpha Haulage'], $names('search=Alpha&search_in=main'));
        // sub only: the services that have a matching sub-service
        $this->assertSame(['Beta Works'], $names('search=Alpha&search_in=sub'));

        // the sub-services preview follows the scope
        $rows = collect($this->getJson('/api/v1/main-services?subs_limit=10&subs_match=1&search=Alpha&search_in=sub')->json('data'))->keyBy('name');
        $this->assertSame(1, $rows['Beta Works']['sub_services_count']);
        $this->assertSame('Alpha skip', $rows['Beta Works']['sub_services'][0]['name']);

        $byMain = collect($this->getJson('/api/v1/main-services?subs_limit=10&subs_match=1&search=Alpha&search_in=main')->json('data'))->keyBy('name');
        $this->assertSame(2, $byMain['Alpha Haulage']['sub_services_count']);   // a main-name search keeps every sub-service

        // "Load more" inside a tab uses the sub-services endpoint with the same scope
        $this->getJson("/api/v1/sub-services?main_service_id={$beta->id}&search=Alpha&search_in=sub")->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/sub-services?main_service_id={$beta->id}&search=Beta")->assertJsonCount(2, 'data');      // via the main's name
        $this->getJson("/api/v1/sub-services?main_service_id={$beta->id}&search=Beta&search_in=sub")->assertJsonCount(0, 'data');
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

    /**
     * FRD §1.9.1 edit page: "أسم الخدمة والبيانات الاختيارية" — the name and
     * the additional data only. The supported vehicle categories are fixed
     * at creation: a payload carrying them is ignored.
     */
    public function test_only_the_name_and_the_additional_data_are_editable(): void
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
            ->assertJsonCount(1, 'data.vehicle_categories');

        $this->assertSame(
            $this->categoryIds('hook_lift'),
            $main->vehicleCategories()->pluck('vehicle_categories.id')->all()
        );
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
