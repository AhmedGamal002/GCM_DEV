<?php

namespace App\Domain\Trips\Actions;

use App\Domain\Trips\Quota\TripQuota;
use App\Domain\Trips\Support\CompleteTrip;
use App\Domain\Trips\Support\TripActors;
use App\Domain\Trips\Support\TripFiles;
use App\Domain\Trips\Support\TripHistory;
use App\Domain\Trips\Support\TripInput;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * FRD §1.14 — the system admin or data entry creates a trip with all the
 * data they have: date and time, company and project, driver and vehicle,
 * the containers, the main and sub-service, the PO it is charged to (or
 * "doesn't need a PO" with a hand-entered cost), quantity, facility, the
 * paper documents and the default approvers (the client's prefilled with
 * the project's representative, GCM's with whoever creates it).
 *
 * "The trip needs the client's approval" — yes (the default): it waits for
 * the client. No: it is created COMPLETED straight away (no OTP, decision
 * ج15), which means meeting every completion requirement now, with the
 * client's signature uploaded by staff (د4) and the creator's own (د5) —
 * `$completion` carries them (see TripSignatures::forCompletion()).
 *
 * The trip is charged to its PO line the moment it exists (ج1), even past
 * the line's balance (ب6).
 */
class CreateTripByStaffAction
{
    public function __construct(private readonly TripQuota $quota) {}

    /**
     * @param  array<string, mixed>  $data  TripInput::DATA + company_id, project_id, requires_client_approval,
     *                                      requires_purchase_order, purchase_order_id, trip_cost, ancillary_cost
     * @param  array<string, ?\Illuminate\Http\UploadedFile>  $files  TripInput::FILES inputs
     * @param  array<string, mixed>  $completion  the signatures, when the trip is created completed
     */
    public function execute(User $actor, array $data, array $files = [], array $completion = []): Trip
    {
        TripActors::assertStaff($actor);

        if (blank($data['trip_at'] ?? null)) {
            throw ValidationException::withMessages(['trip_at' => __('validation.required', ['attribute' => __('validation.attributes.trip_at')])]);
        }

        $needsClient = array_key_exists('requires_client_approval', $data)
            ? filter_var($data['requires_client_approval'], FILTER_VALIDATE_BOOLEAN)
            : true;

        return TripFiles::transaction(function (TripFiles $store) use ($actor, $data, $files, $completion, $needsClient) {
            $trip = new Trip;
            $trip->source = Trip::SOURCE_STAFF;
            $trip->status = Trip::STATUS_AWAITING_CLIENT_APPROVAL;
            $trip->requires_client_approval = $needsClient;
            $trip->created_by = $actor->id;
            $trip->updated_by = $actor->id;

            TripInput::apply($trip, $data, true);

            // The FRD: GCM's default approver is prefilled with the trip's creator.
            if (! array_key_exists('gcm_approver_id', $data)) {
                $trip->gcm_approver_id = $actor->id;
            }

            TripInput::pricing($trip, $data, true);
            $trip->save();

            TripInput::files($trip, $files, $store);
            $trip->save();

            $this->quota->charge($trip, $actor);

            TripHistory::record($trip, TripEvent::CREATED, $actor, [
                'to' => $needsClient ? Trip::STATUS_AWAITING_CLIENT_APPROVAL : Trip::STATUS_COMPLETED,
            ]);

            if (! $needsClient) {
                CompleteTrip::run($trip, $actor, $completion, $store);
            }

            return $trip;
        });
    }
}
