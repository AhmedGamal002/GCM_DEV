<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\Actions\AcceptClientObjectionAction;
use App\Domain\Trips\Actions\UpdateTripAction;
use App\Domain\Trips\Events\TripEventRecorded;
use App\Domain\Trips\Exceptions\TripHistoryIsAppendOnlyException;
use App\Models\Company;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderService;
use App\Models\Trip;
use App\Models\TripEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/**
 * The trip number (ج14) and the trip's permanent history (ج8).
 */
class TripNumberingAndHistoryTest extends TripTestCase
{
    // ------------------------------------------------------------ ج14

    public function test_numbers_run_per_company_from_one(): void
    {
        $this->assertSame('ALN-T0001', $this->createAwaiting()->code);
        $this->assertSame('ALN-T0002', $this->createByDriver()->code);

        $other = Company::factory()->create(['prefix' => 'XYZ']);
        $otherProject = Project::factory()->for($other)->create();

        $trip = $this->createAwaiting(['company_id' => $other->id, 'project_id' => $otherProject->id, 'requires_purchase_order' => false, 'purchase_order_id' => null, 'trip_cost' => 10, 'returning_asset_id' => null]);
        $this->assertSame('XYZ-T0001', $trip->code);
        $this->assertSame('ALN-T0003', $this->createAwaiting()->code);
    }

    public function test_a_cancelled_trips_number_is_never_handed_out_again(): void
    {
        $this->cancel($this->createAwaiting());

        $this->assertSame('ALN-T0002', $this->createAwaiting()->code);
    }

    public function test_the_number_has_at_least_four_digits_and_grows_past_them(): void
    {
        Trip::factory()->forProject($this->project)->create(['sequence' => 9999]);

        $this->assertSame('ALN-T10000', $this->createAwaiting()->code);
    }

    public function test_the_number_and_the_company_never_change(): void
    {
        $trip = $this->createAwaiting();
        $trip->forceFill(['code' => 'HACK', 'sequence' => 77, 'company_id' => Company::factory()->create()->id])->save();

        $trip->refresh();
        $this->assertSame('ALN-T0001', $trip->code);
        $this->assertSame(1, $trip->sequence);
        $this->assertSame($this->company->id, $trip->company_id);
    }

    public function test_a_trip_cannot_move_to_another_companys_project(): void
    {
        $trip = $this->createAwaiting();
        $foreign = Project::factory()->for(Company::factory()->create())->create();

        try {
            app(UpdateTripAction::class)->execute($trip, $this->admin, ['project_id' => $foreign->id, 'requires_purchase_order' => false, 'trip_cost' => 5]);
            $this->fail('The trip number is the company\'s: the company is fixed.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('project_id', $e->errors());
        }

        $this->assertSame($this->project->id, $trip->fresh()->project_id);
    }

    public function test_the_project_must_belong_to_the_chosen_company_on_create(): void
    {
        $other = Company::factory()->create();

        $this->expectException(ValidationException::class);
        $this->createAwaiting(['company_id' => $other->id]);
    }

    // ------------------------------------------------------------ ج8

    public function test_every_action_writes_its_history_row(): void
    {
        $trip = $this->createByDriver(['sub_service_id' => $this->sub->id]);
        $this->clientReject($trip, 'The quantity is wrong');
        app(AcceptClientObjectionAction::class)->execute($trip, $this->dataEntry, ['quantity_ton' => 2.5]);
        $this->clientApprove($trip, ['comment' => 'OK now']);
        $this->finalApprove($trip, [], ['intermediate_facility_id' => $this->facility->id]);
        $this->cancel($trip, 'Duplicate');

        $events = TripEvent::where('trip_id', $trip->id)
            ->whereIn('type', [TripEvent::CREATED, TripEvent::CLIENT_REJECTED, TripEvent::OBJECTION_ACCEPTED, TripEvent::CLIENT_APPROVED, TripEvent::FINAL_APPROVED, TripEvent::CANCELLED])
            ->orderBy('id')->get();

        $this->assertSame(
            [
                [TripEvent::CREATED, null, Trip::STATUS_AWAITING_CLIENT_APPROVAL, $this->driver->user->id, null],
                [TripEvent::CLIENT_REJECTED, Trip::STATUS_AWAITING_CLIENT_APPROVAL, Trip::STATUS_CLIENT_ISSUE, $this->clientManager->id, 'The quantity is wrong'],
                [TripEvent::OBJECTION_ACCEPTED, Trip::STATUS_CLIENT_ISSUE, Trip::STATUS_AWAITING_CLIENT_APPROVAL, $this->dataEntry->id, 'The quantity is wrong'],
                [TripEvent::CLIENT_APPROVED, Trip::STATUS_AWAITING_CLIENT_APPROVAL, Trip::STATUS_CLIENT_APPROVED, $this->clientManager->id, 'OK now'],
                [TripEvent::FINAL_APPROVED, Trip::STATUS_CLIENT_APPROVED, Trip::STATUS_COMPLETED, $this->admin->id, null],
                [TripEvent::CANCELLED, Trip::STATUS_COMPLETED, Trip::STATUS_CANCELLED, $this->admin->id, 'Duplicate'],
            ],
            $events->map(fn (TripEvent $e) => [$e->type, $e->from_status, $e->to_status, $e->actor_id, $e->reason])->all(),
        );
    }

    public function test_accepting_the_objection_clears_the_clients_comment_but_the_history_keeps_it(): void
    {
        $trip = $this->createAwaiting();
        app(\App\Domain\Trips\Actions\ClientRejectTripAction::class)->execute($trip, $this->clientManager, 'Wrong container', $this->image('proof.png'));
        $this->assertNotNull($trip->fresh()->client_attachment_path);

        $trip = app(AcceptClientObjectionAction::class)->execute($trip, $this->admin);

        $this->assertNull($trip->client_comment);
        $this->assertNull($trip->client_attachment_path);
        $this->assertSame('Wrong container', TripEvent::where('type', TripEvent::OBJECTION_ACCEPTED)->sole()->reason);
    }

    public function test_the_rejection_reason_is_required(): void
    {
        $trip = $this->createAwaiting();

        $this->expectException(ValidationException::class);
        app(\App\Domain\Trips\Actions\ClientRejectTripAction::class)->execute($trip, $this->clientManager, '  ');
    }

    public function test_an_edit_records_each_changed_field_from_to_with_readable_values(): void
    {
        $trip = $this->createAwaiting();
        $newPo = PurchaseOrder::factory()->forProject($this->project)->between('2026-06-01', '2026-12-31')->create(['number' => 'ALN-1300']);
        PurchaseOrderService::factory()->create(['purchase_order_id' => $newPo->id, 'main_service_id' => $this->service->id]);

        app(UpdateTripAction::class)->execute($trip, $this->admin, [
            'sub_service_id' => $this->receiptSub->id,
            'purchase_order_id' => $newPo->id,
            'quantity_ton' => 2,
            'delivery_note_number' => 'P-881',
        ], ['delivery_note' => $this->image('dn.png')]);

        $changes = TripEvent::where('type', TripEvent::UPDATED)->sole()->changes;

        $this->assertSame(['from' => 'General collection', 'to' => 'Cardboard collection'], $changes['sub_service']);
        $this->assertSame(['from' => 'ALN-1200', 'to' => 'ALN-1300'], $changes['purchase_order']);
        $this->assertSame(['from' => '3.750', 'to' => '2.000'], $changes['quantity_ton']);
        $this->assertSame(['from' => null, 'to' => 'P-881'], $changes['delivery_note_number']);
        $this->assertSame(['from' => null, 'to' => 'attached'], $changes['delivery_note']);
        $this->assertArrayNotHasKey('driver', $changes);
    }

    public function test_an_edit_that_changes_nothing_writes_no_history(): void
    {
        $trip = $this->createAwaiting();

        app(UpdateTripAction::class)->execute($trip, $this->admin, ['sub_service_id' => $this->sub->id]);

        $this->assertSame(0, TripEvent::where('type', TripEvent::UPDATED)->count());
    }

    public function test_the_history_is_append_only(): void
    {
        $this->createAwaiting();
        $event = TripEvent::firstOrFail();

        foreach ([
            fn () => $event->forceFill(['reason' => 'rewritten'])->save(),
            fn () => $event->delete(),
            fn () => TripEvent::query()->update(['reason' => 'x']),
            fn () => TripEvent::query()->delete(),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('The trip history must refuse any change.');
            } catch (TripHistoryIsAppendOnlyException) {
            }
        }

        $this->assertNull($event->fresh()->reason);
    }

    public function test_the_event_reaches_listeners_only_after_commit(): void
    {
        Event::fake([TripEventRecorded::class]);

        $this->createAwaiting();
        Event::assertDispatched(TripEventRecorded::class, fn ($e) => $e->event->type === TripEvent::CREATED);

        Event::fake([TripEventRecorded::class]);

        try {
            DB::transaction(function () {
                $this->createAwaiting();
                throw new \RuntimeException('rolled back');
            });
        } catch (\RuntimeException) {
        }

        Event::assertNotDispatched(TripEventRecorded::class);
    }
}
