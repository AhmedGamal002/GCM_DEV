<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\Actions\AcceptClientObjectionAction;
use App\Domain\Trips\Actions\AttachRecycleReceiptByDriverAction;
use App\Domain\Trips\Actions\CancelTripAction;
use App\Domain\Trips\Actions\ClientApproveTripAction;
use App\Domain\Trips\Actions\ClientRejectTripAction;
use App\Domain\Trips\Actions\CreateTripByStaffAction;
use App\Domain\Trips\Actions\FinalApproveTripAction;
use App\Domain\Trips\Actions\RejectClientObjectionAction;
use App\Domain\Trips\Actions\UpdateTripAction;
use App\Domain\Trips\Exceptions\TripTransitionNotAllowedException;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The FRD's transition table, cell by cell: every status × every action —
 * the allowed ones land on the right status, the others throw
 * TripTransitionNotAllowedException and change nothing. Then who may act.
 */
class TripTransitionsTest extends TripTestCase
{
    /** action => [the statuses it starts from, the status it ends in (null = unchanged)] */
    private const TABLE = [
        'update' => [[Trip::STATUS_AWAITING_CLIENT_APPROVAL, Trip::STATUS_CLIENT_APPROVED, Trip::STATUS_COMPLETED], null],
        'client_approve' => [[Trip::STATUS_AWAITING_CLIENT_APPROVAL], Trip::STATUS_CLIENT_APPROVED],
        'client_reject' => [[Trip::STATUS_AWAITING_CLIENT_APPROVAL], Trip::STATUS_CLIENT_ISSUE],
        'accept_objection' => [[Trip::STATUS_CLIENT_ISSUE], Trip::STATUS_AWAITING_CLIENT_APPROVAL],
        'reject_objection' => [[Trip::STATUS_CLIENT_ISSUE], Trip::STATUS_COMPLETED],
        'final_approve' => [[Trip::STATUS_CLIENT_APPROVED, Trip::STATUS_AWAITING_CLIENT_APPROVAL], Trip::STATUS_COMPLETED],
        'cancel' => [[Trip::STATUS_AWAITING_CLIENT_APPROVAL, Trip::STATUS_CLIENT_APPROVED, Trip::STATUS_CLIENT_ISSUE, Trip::STATUS_COMPLETED], Trip::STATUS_CANCELLED],
        'attach_recycle_receipt' => [[Trip::STATUS_AWAITING_CLIENT_APPROVAL, Trip::STATUS_CLIENT_APPROVED, Trip::STATUS_CLIENT_ISSUE], null],
    ];

    public static function cells(): array
    {
        $cells = [];

        foreach (array_keys(self::TABLE) as $action) {
            foreach (Trip::STATUSES as $status) {
                $cells["{$action} from {$status}"] = [$status, $action];
            }
        }

        return $cells;
    }

    #[DataProvider('cells')]
    public function test_the_transition_table(string $status, string $action): void
    {
        $trip = $this->tripIn($status);
        [$from, $to] = self::TABLE[$action];
        $events = TripEvent::where('trip_id', $trip->id)->count();

        if (! in_array($status, $from, true)) {
            try {
                $this->run_action($action, $trip);
                $this->fail("{$action} must be refused while the trip is {$status}.");
            } catch (TripTransitionNotAllowedException $e) {
                $this->assertSame($status, $e->status);
            }

            $this->assertSame($status, $trip->fresh()->status);
            $this->assertSame($events, TripEvent::where('trip_id', $trip->id)->count(), 'A refused action writes no history.');

            return;
        }

        $result = $this->run_action($action, $trip);

        $this->assertSame($to ?? $status, $result->status);
        $this->assertSame($to ?? $status, $trip->fresh()->status);
    }

    private function run_action(string $action, Trip $trip): Trip
    {
        return match ($action) {
            'update' => app(UpdateTripAction::class)->execute($trip, $this->admin, ['quantity_ton' => 2]),
            'client_approve' => $this->clientApprove($trip),
            'client_reject' => $this->clientReject($trip),
            'accept_objection' => app(AcceptClientObjectionAction::class)->execute($trip, $this->admin, ['quantity_ton' => 2]),
            'reject_objection' => app(RejectClientObjectionAction::class)->execute($trip, $this->admin, $this->completion()),
            'final_approve' => $this->finalApprove($trip, $this->completion()),
            'cancel' => $this->cancel($trip),
            'attach_recycle_receipt' => app(AttachRecycleReceiptByDriverAction::class)->execute($trip, $this->driver->user, $this->image('receipt.png')),
        };
    }

    // ------------------------------------------------------------ the paths, end to end

    public function test_the_usual_path_driver_client_final_approval(): void
    {
        $trip = $this->createByDriver(['sub_service_id' => $this->sub->id]);
        $this->assertSame(Trip::STATUS_AWAITING_CLIENT_APPROVAL, $trip->status);
        $this->assertSame(Trip::SOURCE_DRIVER, $trip->source);

        $trip = $this->clientApprove($trip);
        $this->assertSame(Trip::STATUS_CLIENT_APPROVED, $trip->status);
        $this->assertSame($this->clientManager->id, $trip->client_approved_by);

        $trip = $this->finalApprove($trip, [], ['intermediate_facility_id' => $this->facility->id]);
        $this->assertSame(Trip::STATUS_COMPLETED, $trip->status);
        $this->assertSame($this->admin->id, $trip->completed_by);
        $this->assertNotNull($trip->completed_at);
    }

    public function test_the_objection_loop_has_no_limit(): void
    {
        $trip = $this->createAwaiting();

        for ($round = 0; $round < 3; $round++) {
            $trip = $this->clientReject($trip, "Round {$round}");
            $this->assertSame(Trip::STATUS_CLIENT_ISSUE, $trip->status);

            $trip = app(AcceptClientObjectionAction::class)->execute($trip, $this->dataEntry, ['quantity_ton' => 1 + $round]);
            $this->assertSame(Trip::STATUS_AWAITING_CLIENT_APPROVAL, $trip->status);
        }

        $this->assertSame(3, TripEvent::where('trip_id', $trip->id)->where('type', TripEvent::CLIENT_REJECTED)->count());
    }

    public function test_a_trip_that_needs_no_client_approval_is_created_completed(): void
    {
        $trip = app(CreateTripByStaffAction::class)->execute($this->dataEntry, $this->staffData(['requires_client_approval' => false]), [], $this->completion());

        $this->assertSame(Trip::STATUS_COMPLETED, $trip->status);
        $this->assertFalse($trip->requires_client_approval);
        $this->assertSame($this->dataEntry->id, $trip->completed_by);
    }

    public function test_the_final_approval_without_the_client_needs_the_clients_signature_uploaded(): void
    {
        $trip = $this->createAwaiting();

        try {
            $this->finalApprove($trip);
            $this->fail('Completing without the client needs the client\'s signature (د4).');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('client_signature', $e->errors());
        }

        $trip = $this->finalApprove($trip->fresh(), $this->completion());
        $this->assertSame(Trip::STATUS_COMPLETED, $trip->status);
        $this->assertNull($trip->client_approved_by);
        $this->assertSame('Site Supervisor', $trip->client_signer_name);
    }

    public function test_cancelling_a_completed_trip_is_allowed(): void
    {
        $trip = $this->cancel($this->tripIn(Trip::STATUS_COMPLETED), 'Entered twice');

        $this->assertSame(Trip::STATUS_CANCELLED, $trip->status);
        $this->assertSame('Entered twice', $trip->cancellation_reason);
        $this->assertSame($this->admin->id, $trip->cancelled_by);
    }

    public function test_the_cancellation_reason_is_optional(): void
    {
        $trip = $this->cancel($this->createAwaiting(), '   ');

        $this->assertSame(Trip::STATUS_CANCELLED, $trip->status);
        $this->assertNull($trip->cancellation_reason);
    }

    // ------------------------------------------------------------ who may act

    public function test_only_staff_drive_the_staff_actions(): void
    {
        $trip = $this->createAwaiting();

        foreach ([$this->auditor, $this->driver->user, $this->clientManager] as $who) {
            foreach ([
                fn () => app(UpdateTripAction::class)->execute($trip, $who, ['quantity_ton' => 1]),
                fn () => app(FinalApproveTripAction::class)->execute($trip, $who, $this->completion()),
                fn () => app(CancelTripAction::class)->execute($trip, $who),
                fn () => app(CreateTripByStaffAction::class)->execute($who, $this->staffData()),
            ] as $attempt) {
                try {
                    $attempt();
                    $this->fail('Only the system admin or data entry may do this.');
                } catch (AuthorizationException) {
                }
            }
        }

        $this->assertSame(Trip::STATUS_AWAITING_CLIENT_APPROVAL, $trip->fresh()->status);
    }

    public function test_data_entry_has_the_same_trip_powers_as_the_admin(): void
    {
        $trip = $this->createAwaiting([], $this->dataEntry);
        $trip = app(FinalApproveTripAction::class)->execute($trip, $this->dataEntry, $this->completion());

        $this->assertSame(Trip::STATUS_COMPLETED, $trip->status);
        $this->assertSame(Trip::STATUS_CANCELLED, app(CancelTripAction::class)->execute($trip, $this->dataEntry)->status);
    }

    public function test_both_client_roles_of_the_project_may_approve_or_reject(): void
    {
        $this->assertSame(Trip::STATUS_CLIENT_APPROVED, $this->clientApprove($this->createAwaiting(), [], $this->clientAuditor)->status);
        $this->assertSame(
            Trip::STATUS_CLIENT_ISSUE,
            app(ClientRejectTripAction::class)->execute($this->createAwaiting(), $this->clientAuditor, 'No')->status,
        );
    }

    public function test_a_client_who_does_not_see_the_project_cannot_approve_or_reject(): void
    {
        $trip = $this->createAwaiting();
        $otherProjectOnly = User::factory()->client($this->company, 'client_project_manager', false)->create();
        $otherCompany = User::factory()->client(null, 'client_project_manager')->create();
        $deactivated = User::factory()->client($this->company, 'client_project_manager')->create(['status' => 'deactivated']);
        $onVacation = User::factory()->client($this->company, 'client_project_auditor')->create(['status' => 'on_vacation']);

        foreach ([$otherProjectOnly, $otherCompany, $deactivated, $onVacation, $this->admin, $this->driver->user] as $who) {
            foreach ([
                fn () => app(ClientApproveTripAction::class)->execute($trip, $who, ['signature' => $this->image()]),
                fn () => app(ClientRejectTripAction::class)->execute($trip, $who, 'No'),
            ] as $attempt) {
                try {
                    $attempt();
                    $this->fail('Only a client account that sees the project may approve or reject.');
                } catch (AuthorizationException) {
                }
            }
        }

        $this->assertSame(Trip::STATUS_AWAITING_CLIENT_APPROVAL, $trip->fresh()->status);
    }

    public function test_a_client_assigned_to_the_project_by_name_may_approve(): void
    {
        $assigned = User::factory()->client($this->company, 'client_project_manager', false)->create();
        $assigned->accessibleProjects(); // no-op, the pivot decides
        \Illuminate\Support\Facades\DB::table('project_user')->insert(['project_id' => $this->project->id, 'user_id' => $assigned->id]);

        $this->assertSame(Trip::STATUS_CLIENT_APPROVED, $this->clientApprove($this->createAwaiting(), [], $assigned)->status);
    }

    public function test_the_driver_may_only_attach_the_receipt_to_their_own_trip(): void
    {
        $trip = $this->createAwaiting();
        $other = \App\Models\Driver::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(AttachRecycleReceiptByDriverAction::class)->execute($trip, $other->user, $this->image('receipt.png'));
    }

    public function test_the_transition_exception_renders_409(): void
    {
        $e = new TripTransitionNotAllowedException('cancel', Trip::STATUS_CANCELLED);
        $response = $e->render();

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('trip_transition_not_allowed', $response->getData(true)['code']);
    }
}
