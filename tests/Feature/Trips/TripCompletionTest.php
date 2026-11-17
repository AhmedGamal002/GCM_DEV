<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\Actions\AttachRecycleReceiptByDriverAction;
use App\Domain\Trips\Actions\CreateTripByStaffAction;
use App\Domain\Trips\Actions\RejectClientObjectionAction;
use App\Domain\Trips\Actions\UpdateTripAction;
use App\Models\IntermediateFacility;
use App\Models\MainService;
use App\Models\SubService;
use App\Models\Trip;
use App\Models\TripEvent;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * What a trip needs before it is completed (ج7, ج13, د4, د5) and the
 * signature / stamp snapshots it keeps (د4–د6).
 */
class TripCompletionTest extends TripTestCase
{
    private function errorsOf(callable $attempt): array
    {
        try {
            $attempt();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('A validation error was expected.');
    }

    // ------------------------------------------------------------ ج13

    public function test_a_sub_service_that_requires_a_receipt_needs_its_number_and_photo(): void
    {
        $trip = $this->clientApprove($this->createAwaiting(['sub_service_id' => $this->receiptSub->id]));

        $errors = $this->errorsOf(fn () => $this->finalApprove($trip));
        $this->assertArrayHasKey('recycle_receipt_number', $errors);
        $this->assertArrayHasKey('recycle_receipt', $errors);
        $this->assertSame(Trip::STATUS_CLIENT_APPROVED, $trip->fresh()->status);

        // The number alone is not enough: the photo too.
        $errors = $this->errorsOf(fn () => $this->finalApprove($trip->fresh(), [], ['recycle_receipt_number' => 'RC-55']));
        $this->assertSame(['recycle_receipt'], array_keys($errors));
        $this->assertNull($trip->fresh()->recycle_receipt_number, 'The failed approval rolled its edit back.');
    }

    public function test_the_receipt_completes_the_requirement_and_the_other_documents_stay_optional(): void
    {
        $trip = $this->clientApprove($this->createAwaiting(['sub_service_id' => $this->receiptSub->id]));
        app(AttachRecycleReceiptByDriverAction::class)->execute($trip, $this->driver->user, $this->image('receipt.png'));

        $trip = app(\App\Domain\Trips\Actions\FinalApproveTripAction::class)->execute(
            $trip, $this->admin, ['gcm_signature' => $this->image()], ['recycle_receipt_number' => 'RC-55'],
        );

        $this->assertSame(Trip::STATUS_COMPLETED, $trip->status);
        $this->assertNull($trip->delivery_note_number);
        $this->assertNull($trip->waste_manifest_path);
    }

    public function test_the_driver_replaces_the_receipt_until_completion_then_only_staff(): void
    {
        $trip = $this->createAwaiting(['sub_service_id' => $this->receiptSub->id]);
        $attach = app(AttachRecycleReceiptByDriverAction::class);

        $attach->execute($trip, $this->driver->user, $this->image('first.png'));
        $first = $trip->fresh()->recycle_receipt_path;
        $attach->execute($trip, $this->driver->user, $this->image('second.png'));
        $second = $trip->fresh()->recycle_receipt_path;

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        $trip = app(UpdateTripAction::class)->execute($trip, $this->admin, ['recycle_receipt_number' => 'RC-1']);
        $trip = $this->finalApprove($trip, $this->completion());

        $this->expectException(\App\Domain\Trips\Exceptions\TripTransitionNotAllowedException::class);
        $attach->execute($trip, $this->driver->user, $this->image('third.png'));
    }

    // ------------------------------------------------------------ ج7

    public function test_the_client_changing_the_sub_service_clears_an_unfit_facility_and_the_final_approval_waits(): void
    {
        $other = SubService::factory()->forService($this->service)->create(['name' => 'Concrete collection']);
        $otherFacility = IntermediateFacility::factory()->create(['name' => 'Crusher']);
        $other->facilities()->attach($otherFacility);

        $trip = $this->clientApprove($this->createAwaiting(), ['sub_service_id' => $other->id]);

        $this->assertSame($other->id, $trip->sub_service_id);
        $this->assertNull($trip->intermediate_facility_id);
        $changes = TripEvent::where('type', TripEvent::CLIENT_APPROVED)->sole()->changes;
        $this->assertSame(['from' => 'General collection', 'to' => 'Concrete collection'], $changes['sub_service']);

        $this->assertArrayHasKey('intermediate_facility_id', $this->errorsOf(fn () => $this->finalApprove($trip)));

        $trip = $this->finalApprove($trip->fresh(), [], ['intermediate_facility_id' => $otherFacility->id]);
        $this->assertSame(Trip::STATUS_COMPLETED, $trip->status);
    }

    public function test_the_client_keeps_a_facility_that_still_fits(): void
    {
        $trip = $this->clientApprove($this->createAwaiting(), ['sub_service_id' => $this->receiptSub->id]);

        $this->assertSame($this->facility->id, $trip->intermediate_facility_id);
    }

    public function test_the_client_can_only_pick_a_sub_service_of_the_same_main_service(): void
    {
        $foreign = SubService::factory()->create();

        $this->assertArrayHasKey('sub_service_id', $this->errorsOf(fn () => $this->clientApprove($this->createAwaiting(), ['sub_service_id' => $foreign->id])));
    }

    public function test_a_facility_that_does_not_serve_the_sub_service_is_refused(): void
    {
        $stranger = IntermediateFacility::factory()->create();

        $this->assertArrayHasKey('intermediate_facility_id', $this->errorsOf(fn () => $this->createAwaiting(['intermediate_facility_id' => $stranger->id])));
    }

    public function test_a_drivers_trip_needs_a_sub_service_and_a_facility_before_completion(): void
    {
        $trip = $this->clientApprove($this->createByDriver());

        $errors = $this->errorsOf(fn () => $this->finalApprove($trip));
        $this->assertArrayHasKey('sub_service_id', $errors);
        $this->assertArrayHasKey('intermediate_facility_id', $errors);
    }

    public function test_a_supplying_trip_needs_no_facility_and_has_no_quantity(): void
    {
        $supplying = MainService::factory()->supplying()->create(['name' => 'Water supply']);
        $supplySub = SubService::factory()->forService($supplying)->create(['asset_type' => 'tank']);

        $trip = app(CreateTripByStaffAction::class)->execute($this->admin, $this->staffData([
            'main_service_id' => $supplying->id, 'sub_service_id' => $supplySub->id, 'intermediate_facility_id' => null,
            'requires_purchase_order' => false, 'purchase_order_id' => null, 'trip_cost' => 40,
            'requires_client_approval' => false, 'quantity_ton' => 9,
        ]), [], $this->completion());

        $this->assertSame(Trip::STATUS_COMPLETED, $trip->status);
        $this->assertNull($trip->quantity_ton);
        $this->assertNull($trip->quantity_cbm);
    }

    public function test_a_trip_without_po_needs_its_cost_before_completion(): void
    {
        $this->po->forceFill(['operational_status' => 'deactivated'])->save();
        $trip = $this->clientApprove($this->createByDriver(['sub_service_id' => $this->sub->id]));

        $this->assertArrayHasKey('trip_cost', $this->errorsOf(fn () => $this->finalApprove($trip, [], ['intermediate_facility_id' => $this->facility->id])));

        $trip = $this->finalApprove($trip->fresh(), [], ['intermediate_facility_id' => $this->facility->id, 'trip_cost' => 55]);
        $this->assertSame('55.00', $trip->trip_cost);
    }

    public function test_an_edit_cannot_leave_a_completed_trip_incomplete_and_keeps_its_status(): void
    {
        $trip = $this->tripIn(Trip::STATUS_COMPLETED);

        $this->assertArrayHasKey('recycle_receipt_number', $this->errorsOf(
            fn () => app(UpdateTripAction::class)->execute($trip, $this->admin, ['sub_service_id' => $this->receiptSub->id]),
        ));

        $trip = app(UpdateTripAction::class)->execute($trip->fresh(), $this->admin, ['quantity_ton' => 1.2]);
        $this->assertSame(Trip::STATUS_COMPLETED, $trip->status);
        $this->assertSame('1.200', $trip->quantity_ton);
    }

    // ------------------------------------------------------------ د4 / د5 / د6

    public function test_the_client_approval_keeps_a_copy_of_the_saved_signature_and_stamp(): void
    {
        $this->clientManager->forceFill([
            'signature_image' => $this->image('sig.png')->store('user-signatures/'.$this->clientManager->id, 'local'),
            'stamp_image' => $this->image('stamp.png')->store('user-stamps/'.$this->clientManager->id, 'local'),
        ])->save();

        $trip = app(\App\Domain\Trips\Actions\ClientApproveTripAction::class)->execute($this->createAwaiting(), $this->clientManager, ['use_saved_signature' => true]);

        $this->assertStringStartsWith("trips/{$trip->id}/client-signature/", $trip->client_signature_path);
        $this->assertStringStartsWith("trips/{$trip->id}/client-stamp/", $trip->client_stamp_path);
        $this->assertSame('Client Manager', $trip->client_signer_name);

        // The account changes its signature later: the trip keeps the one it was approved with.
        Storage::disk('local')->delete($this->clientManager->signature_image);
        Storage::disk('local')->assertExists($trip->client_signature_path);
    }

    public function test_a_drawn_signature_goes_to_the_profile_only_when_asked(): void
    {
        $trip = $this->clientApprove($this->createAwaiting());
        $this->assertNull($this->clientManager->fresh()->signature_image);
        $this->assertNull($trip->client_stamp_path, 'No stamp on the account: none on the trip.');

        $this->clientApprove($this->createAwaiting(), ['save_signature' => true]);
        $saved = $this->clientManager->fresh()->signature_image;
        $this->assertNotNull($saved);
        Storage::disk('local')->assertExists($saved);
    }

    public function test_the_client_must_sign(): void
    {
        $this->assertArrayHasKey('signature', $this->errorsOf(
            fn () => app(\App\Domain\Trips\Actions\ClientApproveTripAction::class)->execute($this->createAwaiting(), $this->clientManager, ['use_saved_signature' => true]),
        ));
    }

    public function test_the_completing_staff_member_signs_with_the_saved_signature_and_own_stamp(): void
    {
        $this->dataEntry->forceFill([
            'signature_image' => $this->image('sig.png')->store('user-signatures/'.$this->dataEntry->id, 'local'),
            'stamp_image' => $this->image('stamp.png')->store('user-stamps/'.$this->dataEntry->id, 'local'),
        ])->save();
        $trip = $this->clientApprove($this->createAwaiting());

        $this->assertArrayHasKey('gcm_signature', $this->errorsOf(
            fn () => app(\App\Domain\Trips\Actions\FinalApproveTripAction::class)->execute($trip, $this->dataEntry, []),
        ));

        $trip = app(\App\Domain\Trips\Actions\FinalApproveTripAction::class)->execute($trip->fresh(), $this->dataEntry, ['use_saved_gcm_signature' => true]);

        $this->assertStringStartsWith("trips/{$trip->id}/gcm-signature/", $trip->gcm_signature_path);
        $this->assertStringStartsWith("trips/{$trip->id}/gcm-stamp/", $trip->gcm_stamp_path);
        $this->assertSame($this->dataEntry->id, $trip->completed_by);
    }

    public function test_the_staff_member_can_draw_and_keep_the_signature(): void
    {
        $trip = $this->finalApprove($this->clientApprove($this->createAwaiting()), ['save_gcm_signature' => true]);

        $this->assertNotNull($trip->gcm_signature_path);
        $this->assertNotNull($this->admin->fresh()->signature_image);
        $this->assertNull($trip->gcm_stamp_path);
    }

    public function test_rejecting_the_objection_needs_the_clients_signature_uploaded_and_logs_it(): void
    {
        $trip = $this->clientReject($this->createAwaiting());

        $this->assertArrayHasKey('client_signature', $this->errorsOf(
            fn () => app(RejectClientObjectionAction::class)->execute($trip, $this->admin, ['gcm_signature' => $this->image()]),
        ));

        $trip = app(RejectClientObjectionAction::class)->execute($trip->fresh(), $this->admin, $this->completion(['client_stamp' => $this->image('stamp.png')]));

        $this->assertSame(Trip::STATUS_COMPLETED, $trip->status);
        $this->assertSame('Wrong quantity', $trip->client_comment, 'The client\'s objection stays visible.');
        $this->assertNotNull($trip->client_stamp_path);
        $this->assertSame('Site Supervisor', TripEvent::where('type', TripEvent::CLIENT_SIGNATURE_UPLOADED)->sole()->reason);
    }

    public function test_an_uploaded_client_signature_is_ignored_when_the_client_signed(): void
    {
        $trip = $this->clientApprove($this->createAwaiting());
        $signed = $trip->client_signature_path;

        $trip = $this->finalApprove($trip, ['client_signature' => $this->image('other.png')]);

        $this->assertSame($signed, $trip->client_signature_path);
        $this->assertSame(0, TripEvent::where('type', TripEvent::CLIENT_SIGNATURE_UPLOADED)->count());
    }

    public function test_files_are_stored_privately_under_a_server_name_and_types_are_checked(): void
    {
        $trip = app(UpdateTripAction::class)->execute($this->createAwaiting(), $this->admin, [], [
            'delivery_note' => \Illuminate\Http\UploadedFile::fake()->create('my note.pdf', 100, 'application/pdf'),
        ]);

        $this->assertStringStartsWith("trips/{$trip->id}/delivery-note/", $trip->delivery_note_path);
        $this->assertStringNotContainsString('my note', $trip->delivery_note_path);
        Storage::disk('local')->assertExists($trip->delivery_note_path);

        $this->assertArrayHasKey('waste_manifest', $this->errorsOf(fn () => app(UpdateTripAction::class)->execute($trip, $this->admin, [], [
            'waste_manifest' => \Illuminate\Http\UploadedFile::fake()->create('page.html', 5, 'text/html'),
        ])));
    }
}
