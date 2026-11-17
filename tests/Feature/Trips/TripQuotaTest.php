<?php

namespace Tests\Feature\Trips;

use App\Domain\PurchaseOrders\Actions\UpdatePurchaseOrderStatusAction;
use App\Domain\PurchaseOrders\Quota\QuotaBalances;
use App\Domain\Trips\Actions\AcceptClientObjectionAction;
use App\Domain\Trips\Actions\CreateTripByStaffAction;
use App\Domain\Trips\Actions\UpdateTripAction;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use App\Models\QuotaLedgerEntry;
use App\Models\Trip;
use Illuminate\Validation\ValidationException;

/**
 * The trip side of the quota ledger: charged the moment the trip exists
 * (ج1), even past the balance (ب6), given back on cancel (ج1 / ج2),
 * re-charged when the date, project, service or PO changes (ب7), and the
 * trip keeps its own cost otherwise (ب3).
 */
class TripQuotaTest extends TripTestCase
{
    public function test_creating_a_trip_charges_its_po_line_with_the_lines_cost(): void
    {
        $trip = $this->createAwaiting();

        $this->assertSame($this->line->id, $trip->purchase_order_service_id);
        $this->assertTrue($trip->requires_purchase_order);
        $this->assertSame('100.00', $trip->trip_cost);
        $this->assertSame('0.00', $trip->ancillary_cost);

        $entry = QuotaLedgerEntry::sole();
        $this->assertSame($trip->id, $entry->trip_id);
        $this->assertSame(QuotaLedgerEntry::TYPE_CONSUME, $entry->type);
        $this->assertSame('100.00', $entry->trip_cost_delta);
        $this->assertSame(1, $this->tripsUsed());
    }

    public function test_an_ancillary_line_charges_its_ancillary_cost_too(): void
    {
        $this->line->forceFill(['quota_method' => 'trips_and_ancillary', 'ancillary_cost_per_trip' => 30])->save();

        $trip = $this->createAwaiting();

        $this->assertSame('30.00', $trip->ancillary_cost);
        $this->assertSame(30.0, QuotaBalances::for($this->line)->ancillaryUsed);
    }

    public function test_a_trip_is_charged_even_past_the_balance(): void
    {
        $this->line->forceFill(['max_trips' => 1])->save();

        $this->createAwaiting();
        $this->createAwaiting();

        $balance = QuotaBalances::for($this->line);
        $this->assertSame(-1, $balance->tripsRemaining());
        $this->assertSame(2, Trip::count());
    }

    public function test_every_status_but_cancelled_holds_the_quota(): void
    {
        foreach ([Trip::STATUS_AWAITING_CLIENT_APPROVAL, Trip::STATUS_CLIENT_APPROVED, Trip::STATUS_CLIENT_ISSUE, Trip::STATUS_COMPLETED] as $status) {
            $this->tripIn($status);
        }

        $this->assertSame(4, $this->tripsUsed());

        $this->tripIn(Trip::STATUS_CANCELLED);
        $this->assertSame(4, $this->tripsUsed(), 'A cancelled trip gives its quota back.');
    }

    public function test_cancelling_releases_and_a_second_cancel_is_refused(): void
    {
        $trip = $this->createAwaiting();
        $this->cancel($trip);

        $this->assertSame(0, $this->tripsUsed());
        $this->assertSame(2, QuotaLedgerEntry::count());

        $this->expectException(\App\Domain\Trips\Exceptions\TripTransitionNotAllowedException::class);
        $this->cancel($trip);
    }

    public function test_cancelling_after_the_final_approval_gives_the_quota_back(): void
    {
        $trip = $this->tripIn(Trip::STATUS_COMPLETED);
        $this->assertSame(1, $this->tripsUsed());

        $this->cancel($trip);

        $this->assertSame(0, $this->tripsUsed());
        $this->assertSame(0.0, QuotaBalances::for($this->line)->tripCostUsed);
    }

    // ------------------------------------------------------------ ب3 / ب7

    public function test_an_edit_that_touches_neither_date_project_service_nor_po_keeps_the_cost(): void
    {
        $trip = $this->createAwaiting();
        $this->line->forceFill(['trip_cost' => 150])->save(); // the PO's cost changes later (ب3)

        $trip = app(UpdateTripAction::class)->execute($trip, $this->admin, ['quantity_ton' => 2, 'delivery_note_number' => 'DN-77']);

        $this->assertSame('100.00', $trip->trip_cost);
        $this->assertSame(1, QuotaLedgerEntry::count(), 'No re-charge for a plain edit.');
    }

    public function test_a_new_date_releases_and_charges_again_at_the_lines_current_cost(): void
    {
        $trip = $this->createAwaiting();
        $this->line->forceFill(['trip_cost' => 150])->save();

        $trip = app(UpdateTripAction::class)->execute($trip, $this->admin, ['trip_at' => '2026-02-20 08:00']);

        $this->assertSame('150.00', $trip->trip_cost);
        $this->assertSame(['consume', 'release', 'consume'], QuotaLedgerEntry::orderBy('id')->pluck('type')->all());
        $this->assertSame(1, $this->tripsUsed());
        $this->assertSame(150.0, QuotaBalances::for($this->line)->tripCostUsed);
    }

    public function test_moving_the_trip_to_another_po_moves_its_quota(): void
    {
        $trip = $this->createAwaiting();
        $other = PurchaseOrder::factory()->forProject($this->project)->between('2026-06-01', '2026-12-31')->create(['number' => 'ALN-1300']);
        $otherLine = PurchaseOrderService::factory()->create(['purchase_order_id' => $other->id, 'main_service_id' => $this->service->id, 'trip_cost' => 80]);

        $trip = app(UpdateTripAction::class)->execute($trip, $this->admin, ['purchase_order_id' => $other->id]);

        $this->assertSame($otherLine->id, $trip->purchase_order_service_id);
        $this->assertSame('80.00', $trip->trip_cost);
        $this->assertSame(0, $this->tripsUsed());
        $this->assertSame(1, $this->tripsUsed($otherLine));
    }

    public function test_a_new_project_needs_a_po_of_that_project_and_moves_the_quota(): void
    {
        $trip = $this->createAwaiting();
        $second = Project::factory()->for($this->company)->create(['name' => 'Jeddah Mall']);
        $secondPo = PurchaseOrder::factory()->forProject($second)->between('2026-01-01', '2026-12-31')->create(['number' => 'ALN-1400']);
        $secondLine = PurchaseOrderService::factory()->create(['purchase_order_id' => $secondPo->id, 'main_service_id' => $this->service->id, 'trip_cost' => 90]);

        try {
            app(UpdateTripAction::class)->execute($trip, $this->admin, ['project_id' => $second->id, 'returning_asset_id' => null]);
            $this->fail('The old PO belongs to the old project: a new one must be chosen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('purchase_order_id', $e->errors());
        }

        $trip = app(UpdateTripAction::class)->execute($trip->fresh(), $this->admin, [
            'project_id' => $second->id, 'purchase_order_id' => $secondPo->id, 'returning_asset_id' => null,
        ]);

        $this->assertSame($second->id, $trip->project_id);
        $this->assertSame(0, $this->tripsUsed());
        $this->assertSame(1, $this->tripsUsed($secondLine));
        $this->assertSame('90.00', $trip->trip_cost);
    }

    public function test_a_po_of_another_project_or_without_the_service_or_deactivated_is_refused(): void
    {
        $foreignPo = PurchaseOrder::factory()->forProject(Project::factory()->for($this->company)->create())->create();
        PurchaseOrderService::factory()->create(['purchase_order_id' => $foreignPo->id, 'main_service_id' => $this->service->id]);
        $noService = PurchaseOrder::factory()->forProject($this->project)->between('2026-06-01', '2026-12-31')->create();
        PurchaseOrderService::factory()->create(['purchase_order_id' => $noService->id]);
        $inactive = PurchaseOrder::factory()->forProject($this->project)->between('2027-01-01', '2027-06-30')->create(['operational_status' => 'deactivated']);
        PurchaseOrderService::factory()->create(['purchase_order_id' => $inactive->id, 'main_service_id' => $this->service->id]);

        foreach ([$foreignPo->id, $noService->id, $inactive->id, 999999] as $poId) {
            try {
                $this->createAwaiting(['purchase_order_id' => $poId]);
                $this->fail("PO {$poId} must be refused.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('purchase_order_id', $e->errors());
            }
        }

        $this->assertSame(0, Trip::count());
        $this->assertSame(0, QuotaLedgerEntry::count());
    }

    public function test_a_trip_on_a_po_deactivated_later_stays_editable_and_approvable(): void
    {
        $trip = $this->tripIn(Trip::STATUS_CLIENT_APPROVED);
        app(UpdatePurchaseOrderStatusAction::class)->execute($this->po, 'deactivated', $this->admin);

        $trip = app(UpdateTripAction::class)->execute($trip, $this->admin, ['quantity_ton' => 3]);
        $trip = $this->finalApprove($trip);

        $this->assertSame(Trip::STATUS_COMPLETED, $trip->status);
        $this->assertSame($this->line->id, $trip->purchase_order_service_id);
    }

    // ------------------------------------------------------------ without PO

    public function test_a_trip_without_po_takes_a_hand_entered_cost_and_writes_no_ledger_row(): void
    {
        $trip = $this->createAwaiting(['requires_purchase_order' => false, 'purchase_order_id' => null, 'trip_cost' => 75.5, 'ancillary_cost' => 10]);

        $this->assertNull($trip->purchase_order_service_id);
        $this->assertFalse($trip->requires_purchase_order);
        $this->assertSame('75.50', $trip->trip_cost);
        $this->assertSame('10.00', $trip->ancillary_cost);
        $this->assertSame(0, QuotaLedgerEntry::count());
    }

    public function test_a_project_without_po_for_the_service_must_be_marked_as_not_needing_one(): void
    {
        $this->po->forceFill(['operational_status' => 'deactivated'])->save();

        try {
            $this->createAwaiting(['purchase_order_id' => null]);
            $this->fail('No PO for the service: the trip must be marked "doesn\'t need a PO".');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('requires_purchase_order', $e->errors());
        }
    }

    public function test_switching_a_trip_to_without_po_releases_and_back_charges(): void
    {
        $trip = $this->createAwaiting();

        $trip = app(UpdateTripAction::class)->execute($trip, $this->admin, ['requires_purchase_order' => false, 'trip_cost' => 60]);
        $this->assertNull($trip->purchase_order_service_id);
        $this->assertSame('60.00', $trip->trip_cost);
        $this->assertSame(0, $this->tripsUsed());

        $trip = app(UpdateTripAction::class)->execute($trip, $this->admin, ['requires_purchase_order' => true, 'purchase_order_id' => $this->po->id]);
        $this->assertSame('100.00', $trip->trip_cost);
        $this->assertSame(1, $this->tripsUsed());
    }

    public function test_negative_costs_are_refused(): void
    {
        $this->expectException(ValidationException::class);
        $this->createAwaiting(['requires_purchase_order' => false, 'trip_cost' => -5]);
    }

    // ------------------------------------------------------------ the driver's trip

    public function test_a_drivers_trip_is_charged_to_the_newest_po_with_balance(): void
    {
        $newer = PurchaseOrder::factory()->forProject($this->project)->between('2026-02-01', '2026-08-31')->create(['number' => 'ALN-1300']);
        $newerLine = PurchaseOrderService::factory()->create(['purchase_order_id' => $newer->id, 'main_service_id' => $this->service->id, 'max_trips' => 1, 'trip_cost' => 70]);

        $first = $this->createByDriver();
        $this->assertSame($newerLine->id, $first->purchase_order_service_id);
        $this->assertSame('70.00', $first->trip_cost);

        // The newer PO has no trips left: the next one goes to the older PO that still has balance.
        $second = $this->createByDriver();
        $this->assertSame($this->line->id, $second->purchase_order_service_id);
    }

    public function test_when_every_po_ran_out_the_driver_trip_still_goes_to_the_newest(): void
    {
        $this->line->forceFill(['max_trips' => 1])->save();
        $this->createByDriver();

        $trip = $this->createByDriver();

        $this->assertSame($this->line->id, $trip->purchase_order_service_id);
        $this->assertSame(-1, QuotaBalances::for($this->line)->tripsRemaining());
    }

    public function test_a_drivers_trip_on_a_project_without_po_is_without_po_and_has_no_cost_yet(): void
    {
        $this->po->forceFill(['operational_status' => 'deactivated'])->save();

        $trip = $this->createByDriver();

        $this->assertNull($trip->purchase_order_service_id);
        $this->assertFalse($trip->requires_purchase_order);
        $this->assertNull($trip->trip_cost);
        $this->assertSame(0, QuotaLedgerEntry::count());
    }

    public function test_the_client_rejecting_keeps_the_quota_and_accepting_the_objection_too(): void
    {
        $trip = $this->clientReject($this->createAwaiting());
        $this->assertSame(1, $this->tripsUsed());

        app(AcceptClientObjectionAction::class)->execute($trip, $this->admin, ['quantity_ton' => 2]);
        $this->assertSame(1, $this->tripsUsed());
    }

    public function test_a_failed_create_leaves_no_trip_no_ledger_row_and_no_file(): void
    {
        try {
            app(CreateTripByStaffAction::class)->execute(
                $this->admin,
                $this->staffData(['requires_client_approval' => false]),
                ['delivery_note' => $this->image('dn.png')],
                ['gcm_signature' => $this->image()], // no client signature → completion fails
            );
            $this->fail('Completion without the client\'s signature must fail.');
        } catch (ValidationException) {
        }

        $this->assertSame(0, Trip::count());
        $this->assertSame(0, QuotaLedgerEntry::count());
        $this->assertSame([], \Illuminate\Support\Facades\Storage::disk('local')->allFiles());
    }
}
