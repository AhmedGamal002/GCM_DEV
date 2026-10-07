<?php

namespace Tests\Feature\Services;

use App\Domain\Services\Actions\UpdateSubServiceAction;
use App\Domain\Services\Actions\UpdateSubServiceStatusAction;
use App\Domain\Services\Exceptions\SubServiceInUseException;
use App\Models\AssetCapacityCategory;
use App\Models\IntermediateFacility;
use App\Models\MainService;
use App\Models\SubService;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehicleCategory;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FRD V01.14 §1.9.2. Every test acts as ONE user — switching actingAs()
 * users inside a single test method leaks the first user (see CLAUDE.md).
 */
class SubServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private MainService $main;

    private AssetCapacityCategory $skip;

    private AssetCapacityCategory $tank;

    private AssetCapacityCategory $both;

    private IntermediateFacility $landfill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']);
        app()->instance('tenant', $this->tenant);

        $this->admin = User::factory()->create(['email' => 'admin@gcm.test']);
        $this->admin->assignRole('system_admin');

        $this->main = MainService::factory()->create(['name' => 'Solid Waste']);
        $this->main->vehicleCategories()->sync(VehicleCategory::whereIn('slug', ['hook_lift', 'compactor'])->pluck('id'));

        $this->skip = AssetCapacityCategory::factory()->container()->create(['name' => 'Skip 10']);
        $this->tank = AssetCapacityCategory::factory()->tank()->create(['name' => 'Tank 12']);
        $this->both = AssetCapacityCategory::factory()->create(['name' => 'Vacuum 16', 'applies_to' => 'both']);
        $this->landfill = IntermediateFacility::factory()->create(['name' => 'Landfill']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Construction debris',
            'main_service_id' => $this->main->id,
            'asset_type' => 'container',
            'capacity_ids' => [$this->skip->id, $this->both->id],
            'facility_ids' => [$this->landfill->id],
            'requires_recycle_receipt' => true,
            'skip_color_code' => 'light_concrete',
            'operational_status' => 'active',
        ], $overrides);
    }

    // -------------------------------------------------------------- create

    public function test_it_creates_a_sub_service_with_its_capacities_and_facilities(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/sub-services', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Construction debris')
            ->assertJsonPath('data.main_service.name', 'Solid Waste')
            ->assertJsonPath('data.asset_type', 'container')
            ->assertJsonPath('data.requires_recycle_receipt', true)
            ->assertJsonPath('data.skip_color_code', 'light_concrete')
            ->assertJsonPath('data.skip_color_label', 'Light — Concrete')
            ->assertJsonPath('data.operational_status', 'active')
            ->assertJsonCount(2, 'data.capacities')
            ->assertJsonPath('data.facilities.0.name', 'Landfill');

        $sub = SubService::find($response->json('data.id'));
        $this->assertSame($this->tenant->id, $sub->tenant_id);
        $this->assertSame($this->admin->id, $sub->updated_by);
    }

    public function test_the_recycle_receipt_is_off_and_the_colour_empty_by_default(): void
    {
        $payload = $this->payload();
        unset($payload['requires_recycle_receipt'], $payload['skip_color_code']);

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/sub-services', $payload)
            ->assertCreated()
            ->assertJsonPath('data.requires_recycle_receipt', false)
            ->assertJsonPath('data.skip_color_code', null);
    }

    public function test_required_fields(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/sub-services', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'main_service_id', 'asset_type', 'capacity_ids', 'operational_status']);
    }

    public function test_capacities_must_fit_the_asset_type(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/sub-services', $this->payload(['capacity_ids' => [$this->skip->id, $this->tank->id]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['capacity_ids']);
    }

    public function test_a_tank_sub_service_takes_tank_and_both_capacities(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/sub-services', $this->payload(['asset_type' => 'tank', 'capacity_ids' => [$this->tank->id, $this->both->id]]))
            ->assertCreated()
            ->assertJsonCount(2, 'data.capacities');
    }

    public function test_an_unknown_skip_colour_is_rejected(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/sub-services', $this->payload(['skip_color_code' => 'purple']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['skip_color_code']);
    }

    public function test_facilities_are_required_for_a_waste_service(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/sub-services', $this->payload(['facility_ids' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['facility_ids']);
    }

    public function test_facilities_are_optional_for_a_supplying_service(): void
    {
        $supply = MainService::factory()->supplying()->create();

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/sub-services', $this->payload(['main_service_id' => $supply->id, 'facility_ids' => []]))
            ->assertCreated()
            ->assertJsonCount(0, 'data.facilities');
    }

    public function test_a_deactivated_facility_cannot_be_chosen(): void
    {
        $closed = IntermediateFacility::factory()->deactivated()->create();

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/sub-services', $this->payload(['facility_ids' => [$closed->id]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['facility_ids']);
    }

    public function test_a_deactivated_main_service_cannot_take_new_sub_services(): void
    {
        $off = MainService::factory()->deactivated()->create();

        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/sub-services', $this->payload(['main_service_id' => $off->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['main_service_id']);
    }

    public function test_the_name_is_unique_within_a_main_service_only(): void
    {
        SubService::factory()->forService($this->main)->create(['name' => 'Construction debris']);
        $other = MainService::factory()->create();

        $this->actingAs($this->admin, 'web');

        $this->postJson('/api/v1/sub-services', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->postJson('/api/v1/sub-services', $this->payload(['main_service_id' => $other->id]))
            ->assertCreated();
    }

    /**
     * A real HTML form sends ids and booleans as strings (see CLAUDE.md).
     */
    public function test_ids_sent_as_strings_are_accepted(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post('/api/v1/sub-services', $this->payload([
                'main_service_id' => (string) $this->main->id,
                'capacity_ids' => [(string) $this->skip->id],
                'facility_ids' => [(string) $this->landfill->id],
                'requires_recycle_receipt' => '1',
            ]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.requires_recycle_receipt', true);
    }

    // -------------------------------------------------------------- read

    public function test_the_details_show_the_inherited_vehicle_categories_and_zero_stats(): void
    {
        $sub = SubService::factory()->forService($this->main)->create();

        $this->actingAs($this->admin, 'web')
            ->getJson("/api/v1/sub-services/{$sub->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.main_service.vehicle_categories')
            ->assertJsonPath('data.locked', false)
            ->assertJsonPath('data.stats.trips', 0);
    }

    public function test_the_list_filters_by_main_service_and_searches_both_names(): void
    {
        SubService::factory()->forService($this->main)->create(['name' => 'Debris']);
        $haz = MainService::factory()->create(['name' => 'Hazardous']);
        SubService::factory()->forService($haz)->create(['name' => 'Sludge']);

        $this->actingAs($this->admin, 'web');

        $this->getJson("/api/v1/sub-services?main_service_id={$haz->id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Sludge');
        $this->getJson('/api/v1/sub-services?search=Hazard')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Sludge');
        $this->getJson('/api/v1/sub-services?search=Debr')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Debris');
    }

    // -------------------------------------------------------------- update

    /**
     * FRD §1.9.2 edit page: "أسم الخدمة والبيانات الاختيارية" — the name, the
     * skip colour code and the additional data. The asset type, capacities,
     * facilities and the recycle-receipt flag are fixed at creation: a
     * payload carrying them is ignored.
     */
    public function test_only_the_name_and_the_optional_data_are_editable(): void
    {
        $sub = SubService::factory()->forService($this->main)->create(['asset_type' => 'container', 'requires_recycle_receipt' => false]);
        $sub->capacities()->sync([$this->skip->id]);
        $sub->facilities()->sync([$this->landfill->id]);
        $recycler = IntermediateFacility::factory()->recycling()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/sub-services/{$sub->id}", [
                'name' => 'Renamed',
                'skip_color_code' => 'gray_plastic',
                'additional_data' => '<p>x</p>',
                // locked at creation — all of these are ignored
                'asset_type' => 'tank',
                'capacity_ids' => [$this->tank->id],
                'facility_ids' => [$recycler->id],
                'requires_recycle_receipt' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.skip_color_code', 'gray_plastic')
            ->assertJsonPath('data.additional_data', '<p>x</p>')
            ->assertJsonPath('data.asset_type', 'container')
            ->assertJsonPath('data.capacities.0.id', $this->skip->id)
            ->assertJsonPath('data.facilities.0.id', $this->landfill->id)
            ->assertJsonPath('data.requires_recycle_receipt', false);

        $this->assertSame([$this->skip->id], $sub->capacities()->pluck('asset_capacity_categories.id')->all());
        $this->assertSame([$this->landfill->id], $sub->facilities()->pluck('intermediate_facilities.id')->all());
    }

    public function test_a_name_only_edit_keeps_everything_else(): void
    {
        $sub = SubService::factory()->forService($this->main)->create(['skip_color_code' => 'green_general', 'additional_data' => '<p>keep</p>']);
        $sub->capacities()->sync([$this->skip->id]);
        $sub->facilities()->sync([$this->landfill->id]);

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/sub-services/{$sub->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.capacities.0.id', $this->skip->id)
            ->assertJsonPath('data.facilities.0.id', $this->landfill->id);
    }

    public function test_the_main_service_is_locked_after_creation(): void
    {
        $sub = SubService::factory()->forService($this->main)->create();
        $other = MainService::factory()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/sub-services/{$sub->id}", ['name' => 'X', 'main_service_id' => $other->id])
            ->assertOk()
            ->assertJsonPath('data.main_service_id', $this->main->id);
    }

    public function test_a_locked_sub_service_cannot_be_edited(): void
    {
        $this->expectException(SubServiceInUseException::class);
        app(UpdateSubServiceAction::class)->execute($this->lockedSub(), ['name' => 'Renamed'], $this->admin);
    }

    // -------------------------------------------------------------- status

    public function test_a_sub_service_can_be_deactivated_and_reactivated(): void
    {
        $sub = SubService::factory()->forService($this->main)->create();

        $this->actingAs($this->admin, 'web');

        $this->patchJson("/api/v1/sub-services/{$sub->id}/status", ['status' => 'deactivated'])
            ->assertOk()
            ->assertJsonPath('data.operational_status', 'deactivated');

        $this->patchJson("/api/v1/sub-services/{$sub->id}/status", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.operational_status', 'active');
    }

    public function test_a_sub_service_cannot_be_reactivated_under_a_deactivated_main_service(): void
    {
        $off = MainService::factory()->deactivated()->create();
        $sub = SubService::factory()->forService($off)->deactivated()->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/sub-services/{$sub->id}/status", ['status' => 'active'])
            ->assertStatus(422);

        $this->assertSame('deactivated', $sub->fresh()->operational_status);
    }

    public function test_a_locked_sub_service_cannot_be_deactivated(): void
    {
        $this->expectException(SubServiceInUseException::class);
        app(UpdateSubServiceStatusAction::class)->execute($this->lockedSub(), 'deactivated', $this->admin);
    }

    public function test_status_cannot_be_set_through_update(): void
    {
        $sub = SubService::factory()->forService($this->main)->create();

        $this->actingAs($this->admin, 'web')
            ->patchJson("/api/v1/sub-services/{$sub->id}", ['name' => 'X', 'operational_status' => 'deactivated'])
            ->assertOk()
            ->assertJsonPath('data.operational_status', 'active');
    }

    // -------------------------------------------------------------- helpers

    /**
     * Trips don't exist yet — a subclass stands in for a sub-service tied
     * to an ongoing trip (same approach as the facility in-use test).
     */
    private function lockedSub(): SubService
    {
        $sub = new class extends SubService
        {
            protected $table = 'sub_services';

            public function hasOngoingTrips(): bool
            {
                return true;
            }
        };
        $sub->forceFill(['main_service_id' => $this->main->id, 'name' => 'Busy', 'asset_type' => 'container', 'operational_status' => 'active'])->save();

        return $sub;
    }
}
