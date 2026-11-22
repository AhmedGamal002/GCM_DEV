<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\Actions\CreateTripByDriverAction;
use App\Domain\Trips\Actions\UpdateTripAction;
use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\Contractor;
use App\Models\Driver;
use App\Models\IntermediateFacility;
use App\Models\MainService;
use App\Models\Project;
use App\Models\SubService;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Validation\ValidationException;

/**
 * Part 7.2 — the server re-checks every choice of a saved trip with the
 * rules of the form's lists (TripChoiceCheck, security checklist §1.3), and
 * only what the save changes: an old trip keeps resources that became
 * unavailable since (ج4 "old trips stay as they are").
 */
class TripChoiceCheckTest extends TripTestCase
{
    /** @return array<string, list<string>> */
    private function errorsOf(callable $attempt): array
    {
        try {
            $attempt();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('A validation error was expected.');
    }

    private function refusedOn(string $field, array $overrides): void
    {
        $this->assertArrayHasKey($field, $this->errorsOf(fn () => $this->createAwaiting($overrides)));
    }

    // ------------------------------------------------------------ driver / vehicle (ج4)

    public function test_a_driver_on_vacation_or_of_a_deactivated_contractor_is_refused(): void
    {
        $this->driver->user->forceFill(['status' => 'on_vacation'])->save();
        $this->refusedOn('driver_id', []);

        $contractorsDriver = Driver::factory()->forContractor(Contractor::factory()->deactivated()->create())->create();
        $this->makeCompatible($contractorsDriver, $this->vehicle);
        $this->refusedOn('driver_id', ['driver_id' => $contractorsDriver->id]);
    }

    public function test_a_vehicle_on_maintenance_is_refused(): void
    {
        $this->vehicle->forceFill(['operational_status' => 'on_maintenance'])->save();

        $this->refusedOn('vehicle_id', []);
    }

    public function test_a_vehicle_outside_the_drivers_categories_is_refused(): void
    {
        $other = Vehicle::factory()->create(['vehicle_category_id' => VehicleCategory::where('id', '!=', $this->vehicle->vehicle_category_id)->value('id')]);

        $this->refusedOn('vehicle_id', ['vehicle_id' => $other->id, 'going_asset_id' => null, 'returning_asset_id' => null]);
    }

    public function test_affiliations_need_not_match(): void
    {
        $contractorsDriver = Driver::factory()->forContractor(Contractor::factory()->create())->create();
        $this->makeCompatible($contractorsDriver, $this->vehicle);

        $this->assertSame($contractorsDriver->id, $this->createAwaiting(['driver_id' => $contractorsDriver->id])->driver_id);
    }

    // ------------------------------------------------------------ containers (ج3 / ج4)

    public function test_a_container_held_by_an_unfinished_trip_is_refused_until_that_trip_ends(): void
    {
        $first = $this->createAwaiting();
        $held = ['going_asset_id' => $this->goingAsset->id, 'returning_asset_id' => null];

        $this->refusedOn('going_asset_id', $held);

        $this->cancel($first);
        $this->assertSame($this->goingAsset->id, $this->createAwaiting($held)->going_asset_id);
    }

    public function test_a_going_container_comes_from_the_pool_and_fits_the_vehicle(): void
    {
        $this->refusedOn('going_asset_id', ['going_asset_id' => $this->returningAsset->id, 'returning_asset_id' => null]);

        $unfit = Asset::factory()->container()->create(['asset_capacity_category_id' => $this->capacity->id]);
        $this->refusedOn('going_asset_id', ['going_asset_id' => $unfit->id]);
    }

    public function test_a_returning_container_is_at_the_project_or_is_the_going_one(): void
    {
        $pool = Asset::factory()->container()->create(['asset_capacity_category_id' => $this->capacity->id]);
        $this->refusedOn('returning_asset_id', ['going_asset_id' => null, 'returning_asset_id' => $pool->id]);

        $same = $this->createAwaiting(['going_asset_id' => $this->goingAsset->id, 'returning_asset_id' => $this->goingAsset->id]);
        $this->assertSame($same->going_asset_id, $same->returning_asset_id);
    }

    public function test_a_vehicle_with_an_embedded_container_takes_no_external_one(): void
    {
        $tank = AssetCapacityCategory::factory()->create(['applies_to' => 'tank', 'capacity_cbm' => 20, 'capacity_ton' => 6.25]);
        $truck = Vehicle::factory()->create(['vehicle_category_id' => $this->vehicle->vehicle_category_id, 'has_embedded_container' => true, 'embedded_container_type' => 'tank', 'embedded_asset_capacity_category_id' => $tank->id]);

        $this->refusedOn('going_asset_id', ['vehicle_id' => $truck->id, 'sub_service_id' => null, 'intermediate_facility_id' => null]);
    }

    // ------------------------------------------------------------ services, facility, project

    public function test_a_main_service_not_supporting_the_vehicle_or_deactivated_is_refused(): void
    {
        $this->service->vehicleCategories()->detach();
        $this->refusedOn('main_service_id', []);
    }

    public function test_a_deactivated_main_service_is_refused(): void
    {
        $this->service->forceFill(['operational_status' => 'deactivated'])->save();

        $this->refusedOn('main_service_id', []);
    }

    public function test_a_sub_service_must_suit_the_container_and_be_active(): void
    {
        $tankSub = SubService::factory()->forService($this->service)->create(['asset_type' => 'tank']);
        $tankSub->capacities()->sync([$this->capacity->id]);
        $this->refusedOn('sub_service_id', ['sub_service_id' => $tankSub->id, 'intermediate_facility_id' => null]);

        $this->sub->forceFill(['operational_status' => 'deactivated'])->save();
        $this->refusedOn('sub_service_id', []);
    }

    public function test_a_deactivated_facility_is_refused(): void
    {
        $this->facility->forceFill(['operational_status' => 'deactivated'])->save();

        $this->refusedOn('intermediate_facility_id', []);
    }

    public function test_a_deactivated_project_is_refused(): void
    {
        $this->project->forceFill(['operational_status' => 'deactivated'])->save();

        $this->refusedOn('project_id', []);
    }

    // ------------------------------------------------------------ quantity ≤ capacity

    public function test_the_quantity_may_be_lowered_never_raised_above_the_capacity(): void
    {
        $this->refusedOn('quantity_ton', ['quantity_ton' => 4]);

        $this->assertSame('2.000', $this->createAwaiting(['quantity_ton' => 2])->quantity_ton);
        $this->assertSame('12.000', $this->createAwaiting(['quantity_cbm' => 12])->quantity_cbm, 'The capacity itself passes.');
    }

    public function test_the_capacitys_own_volume_passes_even_when_the_factor_gives_a_little_more_tonnage(): void
    {
        $this->capacity->forceFill(['capacity_cbm' => 10, 'capacity_ton' => 3])->save();

        $trip = $this->createAwaiting(['quantity_cbm' => 10]);

        $this->assertSame('3.125', $trip->quantity_ton, '10 m³ × 0.3125 — over the 3 t, but the volume is within: accepted (reading of ours).');
    }

    // ------------------------------------------------------------ the driver's own trip

    public function test_a_drivers_trip_on_a_service_no_po_covers_is_recorded_without_po_not_refused(): void
    {
        // ب6 (product owner, 2026-11-22): the driver's LIST stays the FRD's (PO-covered services), but the
        // trip happened — the server never refuses it on the PO side.
        $other = MainService::factory()->create();
        $this->makeCompatible(null, $this->vehicle, $other);

        $trip = app(CreateTripByDriverAction::class)
            ->execute($this->driver->user, $this->driverData(['main_service_id' => $other->id]), $this->image());

        $this->assertSame($other->id, $trip->main_service_id);
        $this->assertNull($trip->purchase_order_service_id);
        $this->assertFalse($trip->requires_purchase_order);
        $this->assertNull($trip->trip_cost, 'GCM enters the cost later.');
    }

    public function test_a_driver_on_a_project_without_po_may_pick_any_supported_service(): void
    {
        $bare = Project::factory()->for($this->company)->create();
        $back = Asset::factory()->container()->create(['asset_capacity_category_id' => $this->capacity->id]);
        $back->forceFill(['project_id' => $bare->id])->save();

        $trip = app(CreateTripByDriverAction::class)->execute($this->driver->user, $this->driverData([
            'project_id' => $bare->id, 'returning_asset_id' => $back->id,
        ]), $this->image());

        $this->assertNull($trip->purchase_order_service_id);
    }

    // ------------------------------------------------------------ old trips stay as they are

    public function test_an_edit_keeps_resources_that_became_unavailable_since(): void
    {
        $trip = $this->createAwaiting();
        $this->vehicle->forceFill(['operational_status' => 'on_maintenance'])->save();
        $this->driver->user->forceFill(['status' => 'on_vacation'])->save();
        $this->facility->forceFill(['operational_status' => 'deactivated'])->save();

        $trip = app(UpdateTripAction::class)->execute($trip, $this->admin, ['additional_data' => '<p>checked</p>']);

        $this->assertSame($this->vehicle->id, $trip->vehicle_id);
    }

    public function test_an_edit_that_changes_a_resource_checks_the_new_one(): void
    {
        $trip = $this->createAwaiting();
        $broken = Vehicle::factory()->onMaintenance()->create(['vehicle_category_id' => $this->vehicle->vehicle_category_id]);

        $errors = $this->errorsOf(fn () => app(UpdateTripAction::class)->execute($trip, $this->admin, ['vehicle_id' => $broken->id]));

        $this->assertArrayHasKey('vehicle_id', $errors);
    }

    public function test_a_new_driver_on_an_old_trip_must_be_qualified_for_its_vehicle(): void
    {
        $trip = $this->createAwaiting();
        $unqualified = Driver::factory()->create();

        $errors = $this->errorsOf(fn () => app(UpdateTripAction::class)->execute($trip, $this->admin, ['driver_id' => $unqualified->id]));

        $this->assertArrayHasKey('vehicle_id', $errors);
    }

    public function test_a_trip_edited_by_staff_keeps_its_own_containers(): void
    {
        $trip = $this->createAwaiting();

        $trip = app(UpdateTripAction::class)->execute($trip, $this->admin, ['going_asset_id' => $this->goingAsset->id, 'returning_asset_id' => $this->returningAsset->id, 'quantity_ton' => 3]);

        $this->assertSame('3.000', $trip->quantity_ton, 'Its own containers are not "held by another trip".');
    }
}
