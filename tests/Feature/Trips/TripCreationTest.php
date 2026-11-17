<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\Actions\CreateTripByDriverAction;
use App\Domain\Trips\Actions\UpdateTripAction;
use App\Domain\Trips\Support\ClientApproverResolver;
use App\Models\Driver;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating trips (driver / staff), the quantity (ج10 / ج11), the default
 * approvers (ج6) and tenant isolation of every id a trip points at.
 */
class TripCreationTest extends TripTestCase
{
    // ------------------------------------------------------------ the driver

    public function test_the_driver_creates_with_the_minimum_and_the_rest_is_set_for_them(): void
    {
        $trip = $this->createByDriver(['additional_data' => '<p>Gate 3</p>']);

        $this->assertSame($this->driver->id, $trip->driver_id);
        $this->assertSame('2026-03-01 10:00', $trip->trip_at->format('Y-m-d H:i'));
        $this->assertSame($this->driver->user->id, $trip->created_by);
        $this->assertTrue($trip->requires_client_approval);
        $this->assertNotNull($trip->live_photo_path);
        $this->assertSame('https://www.google.com/maps?q=24.7136,46.6753', $trip->location_url);
        $this->assertNull($trip->gcm_approver_id);
        $this->assertSame($this->clientManager->id, $trip->client_approver_id, 'Prefilled with the project\'s representative.');
    }

    public function test_the_driver_cannot_set_the_date_the_driver_the_facility_or_the_cost(): void
    {
        $other = Driver::factory()->create();

        $trip = $this->createByDriver([
            'trip_at' => '2025-01-01 00:00', 'driver_id' => $other->id, 'intermediate_facility_id' => $this->facility->id,
            'trip_cost' => 1, 'purchase_order_id' => null, 'requires_purchase_order' => false, 'quantity_ton' => 99,
        ]);

        $this->assertSame($this->driver->id, $trip->driver_id);
        $this->assertSame('2026-03-01', $trip->trip_at->toDateString());
        $this->assertNull($trip->intermediate_facility_id);
        $this->assertSame('100.00', $trip->trip_cost);
        $this->assertSame('3.750', $trip->quantity_ton, 'The capacity, not the 99 sent.');
    }

    public function test_the_live_location_and_photo_are_required_for_the_driver(): void
    {
        try {
            app(CreateTripByDriverAction::class)->execute($this->driver->user, $this->driverData(['location_url' => null]), null);
            $this->fail('Location and photo are required (ج12).');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing(['location_url', 'live_photo'], array_keys($e->errors()));
        }
    }

    public function test_the_location_must_be_a_web_link(): void
    {
        $this->expectException(ValidationException::class);
        $this->createByDriver(['location_url' => 'javascript:alert(1)']);
    }

    public function test_only_a_driver_creates_from_the_drivers_side(): void
    {
        $this->expectException(AuthorizationException::class);
        app(CreateTripByDriverAction::class)->execute($this->admin, $this->driverData(), $this->image());
    }

    // ------------------------------------------------------------ quantity (ج10 / ج11)

    public function test_the_quantity_defaults_to_the_returning_containers_capacity(): void
    {
        $trip = $this->createAwaiting();

        $this->assertSame('3.750', $trip->quantity_ton);
        $this->assertSame('12.000', $trip->quantity_cbm);
    }

    public function test_an_embedded_container_gives_its_own_capacity(): void
    {
        $tank = \App\Models\AssetCapacityCategory::factory()->create(['applies_to' => 'tank', 'capacity_cbm' => 20, 'capacity_ton' => 6.25]);
        $truck = Vehicle::factory()->create(['has_embedded_container' => true, 'embedded_container_type' => 'tank', 'embedded_asset_capacity_category_id' => $tank->id]);

        $trip = $this->createAwaiting(['vehicle_id' => $truck->id, 'going_asset_id' => null, 'returning_asset_id' => null]);

        $this->assertSame('6.250', $trip->quantity_ton);
        $this->assertSame('20.000', $trip->quantity_cbm);
    }

    public function test_one_unit_given_computes_the_other_with_the_single_factor(): void
    {
        $byTon = $this->createAwaiting(['quantity_ton' => 2]);
        $this->assertSame('2.000', $byTon->quantity_ton);
        $this->assertSame('6.400', $byTon->quantity_cbm);

        $byCbm = $this->createAwaiting(['quantity_cbm' => 7]);
        $this->assertSame('2.188', $byCbm->quantity_ton);

        $both = $this->createAwaiting(['quantity_ton' => 2, 'quantity_cbm' => 7.5]);
        $this->assertSame('7.500', $both->quantity_cbm, 'Both given: kept as typed (the manual correction).');
    }

    public function test_no_container_means_no_quantity_and_negative_is_refused(): void
    {
        $trip = $this->createAwaiting(['going_asset_id' => null, 'returning_asset_id' => null]);
        $this->assertNull($trip->quantity_ton);

        $this->expectException(ValidationException::class);
        $this->createAwaiting(['quantity_ton' => -1]);
    }

    public function test_changing_the_service_to_supplying_drops_the_quantity(): void
    {
        $supplying = MainService::factory()->supplying()->create();
        PurchaseOrder::factory()->forProject($this->project)->create(); // irrelevant PO, other service
        $trip = $this->createAwaiting();

        $trip = app(UpdateTripAction::class)->execute($trip, $this->admin, [
            'main_service_id' => $supplying->id, 'sub_service_id' => null, 'intermediate_facility_id' => null,
            'requires_purchase_order' => false, 'trip_cost' => 30,
        ]);

        $this->assertNull($trip->quantity_ton);
        $this->assertNull($trip->quantity_cbm);
    }

    // ------------------------------------------------------------ the default approvers (ج6)

    public function test_the_gcm_approver_defaults_to_the_creator_and_must_be_active_staff(): void
    {
        $this->assertSame($this->dataEntry->id, $this->createAwaiting([], $this->dataEntry)->gcm_approver_id);
        $this->assertSame($this->admin->id, $this->createAwaiting(['gcm_approver_id' => $this->admin->id], $this->dataEntry)->gcm_approver_id);

        foreach ([$this->auditor->id, $this->clientManager->id] as $wrong) {
            try {
                $this->createAwaiting(['gcm_approver_id' => $wrong]);
                $this->fail('Only an active system admin or data entry.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('gcm_approver_id', $e->errors());
            }
        }
    }

    public function test_the_client_approver_must_be_able_to_approve_the_project(): void
    {
        $this->assertSame($this->clientAuditor->id, $this->createAwaiting(['client_approver_id' => $this->clientAuditor->id])->client_approver_id);

        $outsider = User::factory()->client(null, 'client_project_manager')->create();

        $this->expectException(ValidationException::class);
        $this->createAwaiting(['client_approver_id' => $outsider->id]);
    }

    public function test_no_eligible_representative_means_no_default_client_approver(): void
    {
        $this->clientManager->forceFill(['status' => 'on_vacation'])->save();

        $this->assertNull($this->createAwaiting()->client_approver_id);
    }

    public function test_reminders_go_to_the_default_approver_or_else_to_every_eligible_account(): void
    {
        $resolver = app(ClientApproverResolver::class);
        $trip = $this->createAwaiting();

        $this->assertSame([$this->clientManager->id], $resolver->reminderRecipients($trip)->modelKeys());

        $this->clientManager->forceFill(['status' => 'deactivated'])->save();
        $assigned = User::factory()->client($this->company, 'client_project_auditor', false)->create();
        DB::table('project_user')->insert(['project_id' => $this->project->id, 'user_id' => $assigned->id]);
        User::factory()->client($this->company, 'client_project_auditor', false)->create(); // sees no project

        $this->assertEqualsCanonicalizing([$this->clientAuditor->id, $assigned->id], $resolver->reminderRecipients($trip->fresh())->modelKeys());
    }

    // ------------------------------------------------------------ tenant isolation

    public function test_ids_of_another_tenant_are_refused(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);
        $foreign = [
            'project_id' => Project::factory()->create()->id,
            'driver_id' => Driver::factory()->create()->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'main_service_id' => MainService::factory()->create()->id,
        ];
        $foreignPo = PurchaseOrder::factory()->create();
        app()->instance('tenant', $this->tenant);

        foreach ($foreign as $field => $id) {
            try {
                $this->createAwaiting([$field => $id] + ($field === 'project_id' ? ['company_id' => null] : []));
                $this->fail("{$field} of another tenant must be refused.");
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }

        try {
            $this->createAwaiting(['purchase_order_id' => $foreignPo->id]);
            $this->fail('A PO of another tenant must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('purchase_order_id', $e->errors());
        }

        $this->assertSame(0, Trip::count());
    }

    public function test_trips_of_another_tenant_are_invisible(): void
    {
        $this->createAwaiting();
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app()->instance('tenant', $other);

        $this->assertSame(0, Trip::count());
        $this->assertSame(0, \App\Models\TripEvent::count());
    }
}
