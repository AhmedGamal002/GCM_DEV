<?php

namespace Tests\Feature\Trips;

use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\Company;
use App\Models\Contractor;
use App\Models\Driver;
use App\Models\IntermediateFacility;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use App\Models\SubService;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Testing\TestResponse;

/**
 * Part 7.2 — the trip form's cascading lists, GET /api/v1/trips/options/*,
 * one test per step of the nine and their edge cases (no PO, ended PO, used
 * up line, embedded / external container, supplying service, deactivated
 * contractor, vehicle on maintenance, driver on vacation — ج4), plus who may
 * ask (staff form, the driver's own form, nobody else) and what the PO list
 * shows Data Entry (number, status, per-trip cost — no balances).
 *
 * One actor per test method (the actingAs cache trap, CLAUDE.md).
 */
class TripOptionsTest extends TripTestCase
{
    private function asAdmin(string $uri, array $query = []): TestResponse
    {
        return $this->actingAs($this->admin, 'web')->getJson($this->url($uri, $query));
    }

    private function url(string $uri, array $query): string
    {
        return '/api/v1/trips/options/'.$uri.($query === [] ? '' : '?'.http_build_query($query));
    }

    /** @return list<int> */
    private function ids(TestResponse $response): array
    {
        return collect($response->assertOk()->json('data'))->pluck('id')->all();
    }

    private function contractor(bool $active = true): Contractor
    {
        return $active ? Contractor::factory()->create() : Contractor::factory()->deactivated()->create();
    }

    // ------------------------------------------------------------ step 1: driver → vehicles

    public function test_the_driver_list_offers_active_drivers_of_active_contractors_only(): void
    {
        $vacation = Driver::factory()->create();
        $vacation->user->forceFill(['status' => 'on_vacation'])->save();
        $deactivated = Driver::factory()->create();
        $deactivated->user->forceFill(['status' => 'deactivated'])->save();
        $ofActive = Driver::factory()->forContractor($this->contractor())->create();
        $ofDeactivated = Driver::factory()->forContractor($this->contractor(false))->create();

        $ids = $this->ids($this->asAdmin('drivers'));

        $this->assertContains($this->driver->id, $ids);
        $this->assertContains($ofActive->id, $ids);
        $this->assertNotContains($vacation->id, $ids, 'ج4: a driver on vacation is not offered, even for a trip after the fact.');
        $this->assertNotContains($deactivated->id, $ids);
        $this->assertNotContains($ofDeactivated->id, $ids, 'ج4: the drivers of a deactivated contractor are not offered.');
    }

    public function test_the_driver_list_searches_and_names_the_affiliation(): void
    {
        $contractor = $this->contractor();
        $other = Driver::factory()->forContractor($contractor)->create();
        $other->user->forceFill(['name' => 'Zaid Contractor'])->save();

        $row = $this->asAdmin('drivers', ['search' => 'Zaid'])->assertOk()->json('data');

        $this->assertCount(1, $row);
        $this->assertSame('contractor', $row[0]['affiliation']);
        $this->assertSame($contractor->name, $row[0]['entity_name']);
        $this->assertArrayHasKey('default_vehicle_id', $row[0]);
    }

    public function test_vehicles_are_those_of_the_drivers_categories_whatever_their_affiliation(): void
    {
        $otherCategory = VehicleCategory::where('id', '!=', $this->vehicle->vehicle_category_id)->first();
        $unqualified = Vehicle::factory()->create(['vehicle_category_id' => $otherCategory->id]);
        $contractorVehicle = Vehicle::factory()->create(['vehicle_category_id' => $this->vehicle->vehicle_category_id, 'affiliation' => 'contractor', 'contractor_id' => $this->contractor()->id]);

        $ids = $this->ids($this->asAdmin('vehicles', ['driver_id' => $this->driver->id]));

        $this->assertContains($this->vehicle->id, $ids);
        $this->assertContains($contractorVehicle->id, $ids, 'ج4: a GCM driver may drive a contractor vehicle.');
        $this->assertNotContains($unqualified->id, $ids, 'Only a category the driver is qualified for.');
    }

    public function test_vehicles_on_maintenance_deactivated_or_of_a_deactivated_contractor_are_not_offered(): void
    {
        $category = ['vehicle_category_id' => $this->vehicle->vehicle_category_id];
        $maintenance = Vehicle::factory()->onMaintenance()->create($category);
        $deactivated = Vehicle::factory()->deactivated()->create($category);
        $ofDeactivated = Vehicle::factory()->create([...$category, 'affiliation' => 'contractor', 'contractor_id' => $this->contractor(false)->id]);

        $ids = $this->ids($this->asAdmin('vehicles', ['driver_id' => $this->driver->id]));

        $this->assertNotContains($maintenance->id, $ids);
        $this->assertNotContains($deactivated->id, $ids);
        $this->assertNotContains($ofDeactivated->id, $ids);
    }

    public function test_the_default_vehicle_is_flagged_and_not_prefilled_when_unavailable(): void
    {
        $this->driver->forceFill(['default_vehicle_id' => $this->vehicle->id])->save();

        $response = $this->asAdmin('vehicles', ['driver_id' => $this->driver->id])->assertOk();
        $this->assertSame($this->vehicle->id, $response->json('meta.default_vehicle_id'));
        $this->assertTrue(collect($response->json('data'))->firstWhere('id', $this->vehicle->id)['is_default']);

        $this->vehicle->forceFill(['operational_status' => 'on_maintenance'])->save();
        $this->assertNull($this->asAdmin('vehicles', ['driver_id' => $this->driver->id])->json('meta.default_vehicle_id'), 'A reading of ج4: an unavailable default is not prefilled.');
    }

    public function test_vehicles_search_by_the_plate_as_shown(): void
    {
        Vehicle::factory()->create(['vehicle_category_id' => $this->vehicle->vehicle_category_id, 'plate_letters' => 'XYZ', 'plate_numbers' => '9876']);

        $this->assertSame([$this->vehicle->id], $this->ids($this->asAdmin('vehicles', ['driver_id' => $this->driver->id, 'search' => 'ABC 1234'])));
        $this->assertSame([$this->vehicle->id], $this->ids($this->asAdmin('vehicles', ['driver_id' => $this->driver->id, 'search' => 'ABC1234'])));
    }

    public function test_vehicles_need_a_driver_of_this_tenant(): void
    {
        $this->asAdmin('vehicles')->assertStatus(422)->assertJsonValidationErrors('driver_id');
        $this->asAdmin('vehicles', ['driver_id' => 999999])->assertStatus(422)->assertJsonValidationErrors('driver_id');
    }

    public function test_a_driver_lists_his_own_vehicles_whatever_driver_he_sends(): void
    {
        $other = Driver::factory()->create();
        $otherCategory = VehicleCategory::where('id', '!=', $this->vehicle->vehicle_category_id)->first();
        $other->qualifiedVehicleCategories()->sync([$otherCategory->id]);
        Vehicle::factory()->create(['vehicle_category_id' => $otherCategory->id]);

        $response = $this->actingAs($this->driver->user, 'web')->getJson($this->url('vehicles', ['driver_id' => $other->id]))->assertOk();

        $this->assertSame($this->driver->id, $response->json('meta.driver_id'));
        $this->assertSame([$this->vehicle->id], collect($response->json('data'))->pluck('id')->all());
    }

    // ------------------------------------------------------------ step 2: company → projects

    public function test_companies_and_projects_are_the_active_ones(): void
    {
        $inactiveCompany = Company::factory()->deactivated()->create();
        $inactiveProject = Project::factory()->for($this->company)->deactivated()->create();
        $otherCompanyProject = Project::factory()->create();

        $companies = $this->ids($this->asAdmin('companies'));
        $this->assertContains($this->company->id, $companies);
        $this->assertNotContains($inactiveCompany->id, $companies);

        $projects = $this->ids($this->asAdmin('projects', ['company_id' => $this->company->id]));
        $this->assertSame([$this->project->id], $projects);
        $this->assertNotContains($inactiveProject->id, $projects);
        $this->assertNotContains($otherCompanyProject->id, $projects);
    }

    public function test_projects_need_the_company(): void
    {
        $this->asAdmin('projects')->assertStatus(422)->assertJsonValidationErrors('company_id');
    }

    // ------------------------------------------------------------ step 3: project → POs

    public function test_every_po_of_the_project_but_the_deactivated_one_is_offered_with_its_status(): void
    {
        $ended = PurchaseOrder::factory()->forProject($this->project)->between('2025-01-01', '2025-06-30')->create(['number' => 'ALN-1100']);
        PurchaseOrderService::factory()->create(['purchase_order_id' => $ended->id, 'main_service_id' => $this->service->id, 'max_trips' => 10, 'trip_cost' => 80]);
        $off = PurchaseOrder::factory()->forProject($this->project)->between('2026-06-01', '2026-12-31')->create(['number' => 'ALN-1300']);
        PurchaseOrderService::factory()->create(['purchase_order_id' => $off->id, 'main_service_id' => $this->service->id]);
        $off->forceFill(['operational_status' => 'deactivated'])->save();

        $rows = collect($this->asAdmin('purchase-orders', ['project_id' => $this->project->id, 'main_service_id' => $this->service->id])->assertOk()->json('data'));

        $this->assertSame([$this->po->id, $ended->id], $rows->pluck('id')->all(), 'ب6: running or not, newest first, never the deactivated one.');
        $this->assertSame('ended', $rows->firstWhere('id', $ended->id)['timeline']);
        $this->assertSame('exhausted', $rows->firstWhere('id', $ended->id)['line']['status'], 'An ended PO has no balance left but is still offered.');
        $this->assertSame('valid', $rows->firstWhere('id', $this->po->id)['line']['status']);
        $this->assertTrue($rows->firstWhere('id', $this->po->id)['is_default']);
    }

    public function test_a_used_up_po_is_still_offered(): void
    {
        $this->line->forceFill(['max_trips' => 1])->save();
        $this->createAwaiting();

        $row = $this->asAdmin('purchase-orders', ['project_id' => $this->project->id, 'main_service_id' => $this->service->id])->json('data.0');

        $this->assertSame($this->po->id, $row['id']);
        $this->assertSame('exhausted', $row['line']['status']);
    }

    public function test_no_po_for_the_service_means_without_po_only(): void
    {
        $other = MainService::factory()->create();

        $response = $this->asAdmin('purchase-orders', ['project_id' => $this->project->id, 'main_service_id' => $other->id])->assertOk();

        $this->assertSame([], $response->json('data'));
        $this->assertTrue($response->json('meta.without_purchase_order_only'));
        $this->assertSame('manual', $response->json('meta.cost_source'));
    }

    public function test_data_entry_sees_the_number_status_and_per_trip_cost_but_no_balances(): void
    {
        $this->line->forceFill(['quota_method' => 'trips_and_ancillary', 'ancillary_cost_per_trip' => 15])->save();

        $row = $this->actingAs($this->dataEntry, 'web')
            ->getJson($this->url('purchase-orders', ['project_id' => $this->project->id, 'main_service_id' => $this->service->id]))
            ->assertOk()->json('data.0');

        $this->assertSame('ALN-1200', $row['number']);
        $this->assertEquals(100, $row['line']['trip_cost']);
        $this->assertEquals(15, $row['line']['ancillary_cost']);
        $this->assertSame(['quota_method', 'trip_cost', 'ancillary_cost', 'status', 'status_label'], array_keys($row['line']), 'No used / remaining / max — the balances stay behind /purchase-orders.');
    }

    public function test_the_driver_cannot_list_pos(): void
    {
        $this->actingAs($this->driver->user, 'web')->getJson($this->url('purchase-orders', ['project_id' => $this->project->id]))->assertForbidden();
    }

    // ------------------------------------------------------------ step 4: main services

    public function test_main_services_support_the_vehicle_and_are_covered_by_the_projects_pos(): void
    {
        $notCovered = MainService::factory()->create();
        $notForVehicle = MainService::factory()->create();
        $off = MainService::factory()->deactivated()->create();
        $this->makeCompatible(null, $this->vehicle, $notCovered);
        $this->makeCompatible(null, $this->vehicle, $off);

        $base = ['project_id' => $this->project->id, 'vehicle_id' => $this->vehicle->id];

        $this->assertSame([$this->service->id], $this->ids($this->asAdmin('main-services', $base)), 'Needs a PO: only what the project\'s POs cover.');

        $all = $this->ids($this->asAdmin('main-services', [...$base, 'requires_purchase_order' => 0]));
        $this->assertEqualsCanonicalizing([$this->service->id, $notCovered->id], $all, 'Without PO: every active service supporting the vehicle.');
        $this->assertNotContains($notForVehicle->id, $all);
        $this->assertNotContains($off->id, $all);
    }

    public function test_the_chosen_po_narrows_the_main_services(): void
    {
        $second = MainService::factory()->create();
        $this->makeCompatible(null, $this->vehicle, $second);
        $po2 = PurchaseOrder::factory()->forProject($this->project)->between('2026-01-01', '2026-05-30')->create();
        PurchaseOrderService::factory()->create(['purchase_order_id' => $po2->id, 'main_service_id' => $second->id]);
        $base = ['project_id' => $this->project->id, 'vehicle_id' => $this->vehicle->id];

        $this->assertEqualsCanonicalizing([$this->service->id, $second->id], $this->ids($this->asAdmin('main-services', $base)));
        $this->assertSame([$second->id], $this->ids($this->asAdmin('main-services', [...$base, 'purchase_order_id' => $po2->id])));

        $foreign = PurchaseOrder::factory()->forProject(Project::factory()->for($this->company)->create())->create();
        $this->asAdmin('main-services', [...$base, 'purchase_order_id' => $foreign->id])->assertStatus(422)->assertJsonValidationErrors('purchase_order_id');
    }

    public function test_a_project_without_any_po_offers_every_main_service(): void
    {
        $bare = Project::factory()->for($this->company)->create();
        $other = MainService::factory()->create();
        $this->makeCompatible(null, $this->vehicle, $other);

        $response = $this->asAdmin('main-services', ['project_id' => $bare->id, 'vehicle_id' => $this->vehicle->id])->assertOk();

        $this->assertEqualsCanonicalizing([$this->service->id, $other->id], collect($response->json('data'))->pluck('id')->all());
        $this->assertFalse($response->json('meta.project_has_purchase_orders'));
    }

    public function test_the_drivers_main_services_are_those_the_pos_cover_whatever_he_sends(): void
    {
        $other = MainService::factory()->create();
        $this->makeCompatible(null, $this->vehicle, $other);

        $ids = collect($this->actingAs($this->driver->user, 'web')
            ->getJson($this->url('main-services', ['project_id' => $this->project->id, 'vehicle_id' => $this->vehicle->id, 'requires_purchase_order' => 0]))
            ->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$this->service->id], $ids);
    }

    // ------------------------------------------------------------ step 5: sub-services

    public function test_sub_services_suit_the_containers_type_and_capacity(): void
    {
        $big = AssetCapacityCategory::factory()->create(['applies_to' => 'container', 'capacity_cbm' => 20, 'capacity_ton' => 6.25]);
        $bigOnly = SubService::factory()->forService($this->service)->create(['asset_type' => 'container']);
        $bigOnly->capacities()->sync([$big->id]);
        $tankSub = SubService::factory()->forService($this->service)->create(['asset_type' => 'tank']);
        $tankSub->capacities()->sync([$this->capacity->id]);
        $off = SubService::factory()->forService($this->service)->deactivated()->create();
        $off->capacities()->sync([$this->capacity->id]);

        $response = $this->asAdmin('sub-services', ['main_service_id' => $this->service->id, 'vehicle_id' => $this->vehicle->id, 'returning_asset_id' => $this->returningAsset->id])->assertOk();

        $this->assertEqualsCanonicalizing([$this->sub->id, $this->receiptSub->id], collect($response->json('data'))->pluck('id')->all());
        $this->assertSame('container', $response->json('meta.container.type'));
        $this->assertSame($this->capacity->id, $response->json('meta.container.capacity.id'));
    }

    public function test_with_no_container_yet_every_active_sub_service_is_offered(): void
    {
        $other = SubService::factory()->forService($this->service)->create(['asset_type' => 'tank']);

        $ids = $this->ids($this->asAdmin('sub-services', ['main_service_id' => $this->service->id]));

        $this->assertEqualsCanonicalizing([$this->sub->id, $this->receiptSub->id, $other->id], $ids);
    }

    public function test_an_embedded_container_decides_the_sub_services(): void
    {
        $tankCapacity = AssetCapacityCategory::factory()->create(['applies_to' => 'tank', 'capacity_cbm' => 20, 'capacity_ton' => 6.25]);
        $truck = Vehicle::factory()->create(['has_embedded_container' => true, 'embedded_container_type' => 'tank', 'embedded_asset_capacity_category_id' => $tankCapacity->id]);
        $tankSub = SubService::factory()->forService($this->service)->create(['asset_type' => 'tank']);
        $tankSub->capacities()->sync([$tankCapacity->id]);

        // The embedded container wins over any container sent along.
        $ids = $this->ids($this->asAdmin('sub-services', ['main_service_id' => $this->service->id, 'vehicle_id' => $truck->id, 'returning_asset_id' => $this->returningAsset->id]));

        $this->assertSame([$tankSub->id], $ids);
    }

    // ------------------------------------------------------------ step 6: capacity

    public function test_the_capacity_is_the_returning_containers_or_the_embedded_one_and_none_when_supplying(): void
    {
        $returning = $this->asAdmin('capacity', ['vehicle_id' => $this->vehicle->id, 'returning_asset_id' => $this->returningAsset->id, 'main_service_id' => $this->service->id])->assertOk();
        $this->assertSame('returning_container', $returning->json('data.source'));
        $this->assertEquals(['ton' => 3.75, 'cbm' => 12], $returning->json('data.default_quantity'));
        $this->assertEquals(0.3125, $returning->json('data.converter.tonnes_per_cubic_meter'));

        $tank = AssetCapacityCategory::factory()->create(['applies_to' => 'tank', 'capacity_cbm' => 20, 'capacity_ton' => 6.25]);
        $truck = Vehicle::factory()->create(['has_embedded_container' => true, 'embedded_container_type' => 'tank', 'embedded_asset_capacity_category_id' => $tank->id]);
        $embedded = $this->asAdmin('capacity', ['vehicle_id' => $truck->id, 'returning_asset_id' => $this->returningAsset->id]);
        $this->assertSame('embedded', $embedded->json('data.source'));
        $this->assertEquals(20, $embedded->json('data.capacity.capacity_cbm'));

        $supplying = MainService::factory()->supplying()->create();
        $none = $this->asAdmin('capacity', ['vehicle_id' => $this->vehicle->id, 'returning_asset_id' => $this->returningAsset->id, 'main_service_id' => $supplying->id]);
        $this->assertSame('supplying', $none->json('data.source'));
        $this->assertNull($none->json('data.capacity'), 'ج11: a supplying trip carries no waste.');
    }

    // ------------------------------------------------------------ step 7: containers

    public function test_going_containers_are_available_pool_assets_fitting_the_vehicle(): void
    {
        $atProject = $this->returningAsset;
        $unfit = Asset::factory()->container()->create(['asset_capacity_category_id' => $this->capacity->id]);
        $maintenance = Asset::factory()->container()->onMaintenance()->create(['asset_capacity_category_id' => $this->capacity->id]);
        $ofDeactivated = Asset::factory()->container()->create(['asset_capacity_category_id' => $this->capacity->id, 'affiliation' => 'contractor', 'contractor_id' => $this->contractor(false)->id]);
        $this->makeCompatible(null, $this->vehicle, null, [], [$maintenance, $ofDeactivated]);

        $ids = $this->ids($this->asAdmin('going-assets', ['vehicle_id' => $this->vehicle->id]));

        $this->assertSame([$this->goingAsset->id], $ids);
        $this->assertNotContains($atProject->id, $ids, 'ج3: a going container is not placed at a project.');
        $this->assertNotContains($unfit->id, $ids);
        $this->assertNotContains($maintenance->id, $ids);
        $this->assertNotContains($ofDeactivated->id, $ids, 'ج4: assets of a deactivated contractor are not offered.');
    }

    public function test_a_container_on_an_unfinished_trip_is_held_and_freed_for_that_trip_or_once_finished(): void
    {
        $trip = $this->createAwaiting();

        $this->assertNotContains($this->goingAsset->id, $this->ids($this->asAdmin('going-assets', ['vehicle_id' => $this->vehicle->id])));
        $this->assertContains($this->goingAsset->id, $this->ids($this->asAdmin('going-assets', ['vehicle_id' => $this->vehicle->id, 'trip_id' => $trip->id])), 'The trip being edited keeps its own.');

        $this->cancel($trip);
        $this->assertContains($this->goingAsset->id, $this->ids($this->asAdmin('going-assets', ['vehicle_id' => $this->vehicle->id])));
    }

    public function test_a_vehicle_with_an_embedded_container_takes_no_external_one(): void
    {
        $tank = AssetCapacityCategory::factory()->create(['applies_to' => 'tank', 'capacity_cbm' => 20, 'capacity_ton' => 6.25]);
        $truck = Vehicle::factory()->create(['vehicle_category_id' => $this->vehicle->vehicle_category_id, 'has_embedded_container' => true, 'embedded_container_type' => 'tank', 'embedded_asset_capacity_category_id' => $tank->id]);

        $going = $this->asAdmin('going-assets', ['vehicle_id' => $truck->id])->assertOk();
        $this->assertSame([], $going->json('data'));
        $this->assertFalse($going->json('meta.takes_external_containers'));

        $this->assertSame([], $this->ids($this->asAdmin('returning-assets', ['project_id' => $this->project->id, 'vehicle_id' => $truck->id])));
    }

    public function test_returning_containers_are_the_projects_plus_the_going_one(): void
    {
        $elsewhere = Asset::factory()->container()->create(['asset_capacity_category_id' => $this->capacity->id]);
        $elsewhere->forceFill(['project_id' => Project::factory()->for($this->company)->create()->id])->save();

        $base = ['project_id' => $this->project->id, 'vehicle_id' => $this->vehicle->id];
        $this->assertSame([$this->returningAsset->id], $this->ids($this->asAdmin('returning-assets', $base)));
        $this->assertEqualsCanonicalizing(
            [$this->returningAsset->id, $this->goingAsset->id],
            $this->ids($this->asAdmin('returning-assets', [...$base, 'going_asset_id' => $this->goingAsset->id])),
            'ج3: the going container may come back the same.',
        );
    }

    // ------------------------------------------------------------ step 8: facilities

    public function test_facilities_are_the_active_ones_serving_the_sub_service(): void
    {
        $off = IntermediateFacility::factory()->deactivated()->create();
        $this->sub->facilities()->attach($off);
        $unrelated = IntermediateFacility::factory()->create();

        $this->assertSame([$this->facility->id], $this->ids($this->asAdmin('facilities', ['sub_service_id' => $this->sub->id])));
        $this->assertNotContains($unrelated->id, $this->ids($this->asAdmin('facilities', ['sub_service_id' => $this->sub->id])));
    }

    // ------------------------------------------------------------ the default approvers

    public function test_client_approvers_are_the_accounts_that_can_approve_with_the_representative_first(): void
    {
        $response = $this->asAdmin('client-approvers', ['project_id' => $this->project->id])->assertOk();

        $this->assertEqualsCanonicalizing([$this->clientManager->id, $this->clientAuditor->id], collect($response->json('data'))->pluck('id')->all());
        $this->assertSame($this->clientManager->id, $response->json('meta.default_id'));
    }

    public function test_data_entry_does_not_see_the_system_admin_among_gcm_approvers(): void
    {
        $response = $this->actingAs($this->dataEntry, 'web')->getJson($this->url('gcm-approvers', []))->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->dataEntry->id, $ids);
        $this->assertNotContains($this->admin->id, $ids);
        $this->assertSame($this->dataEntry->id, $response->json('meta.default_id'));
    }

    // ------------------------------------------------------------ who may ask

    public function test_the_auditor_gets_no_list(): void
    {
        $this->actingAs($this->auditor, 'web')->getJson($this->url('companies', []))->assertForbidden();
    }

    public function test_a_client_account_gets_no_list(): void
    {
        $this->actingAs($this->clientManager, 'web')->getJson($this->url('companies', []))->assertForbidden();
    }

    public function test_a_contractor_user_gets_no_list(): void
    {
        $user = User::factory()->contractorUser($this->contractor())->create();

        $this->actingAs($user, 'web')->getJson($this->url('going-assets', ['vehicle_id' => $this->vehicle->id]))->assertForbidden();
    }

    public function test_the_driver_gets_his_form_lists_but_not_the_staff_ones(): void
    {
        $this->actingAs($this->driver->user, 'web');

        $this->getJson($this->url('companies', []))->assertOk();
        $this->getJson($this->url('drivers', []))->assertForbidden();
        $this->getJson($this->url('client-approvers', ['project_id' => $this->project->id]))->assertForbidden();
        $this->getJson($this->url('gcm-approvers', []))->assertForbidden();
    }

    public function test_a_guest_gets_401(): void
    {
        $this->getJson($this->url('companies', []))->assertUnauthorized();
    }

    public function test_it_works_with_a_real_bearer_token(): void
    {
        $token = $this->driver->user->createToken('mobile')->plainTextToken;

        $this->withToken($token)->getJson($this->url('vehicles', []))->assertOk()->assertJsonPath('meta.driver_id', $this->driver->id);
    }

    public function test_another_tenants_records_are_a_422(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $foreign = Project::factory()->create();
        app()->instance('tenant', $this->tenant);

        $this->asAdmin('returning-assets', ['project_id' => $foreign->id])->assertStatus(422)->assertJsonValidationErrors('project_id');
    }

    public function test_long_lists_are_paged(): void
    {
        $response = $this->asAdmin('companies', ['per_page' => 1])->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(1, $response->json('meta.per_page'));
        $this->assertArrayHasKey('last_page', $response->json('meta'));
    }
}
