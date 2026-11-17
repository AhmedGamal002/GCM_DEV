<?php

namespace App\Domain\Trips\Actions;

use App\Domain\Trips\Support\CompleteTrip;
use App\Domain\Trips\Support\TripActors;
use App\Domain\Trips\Support\TripEditor;
use App\Domain\Trips\Support\TripFiles;
use App\Domain\Trips\Support\TripHistory;
use App\Domain\Trips\Support\TripLock;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;

/**
 * FRD §1.14 — the final approval by the system admin or data entry: they
 * review the whole trip, complete what is missing, and approve it — it is
 * "completed" and its documents can be produced.
 *
 * From "approved by the client" (the usual path), and also straight from
 * "awaiting the client's approval": the FRD lets GCM "approve the trip
 * without the client's consent" (and decision ج6 relies on it when no client
 * account can act). Without the client the trip has no client signature, so
 * staff upload it (د4). Either way the completing staff member signs (د5)
 * and the completion requirements apply (CompleteTrip). OTP on the route (7.3).
 */
class FinalApproveTripAction
{
    public const FROM = [Trip::STATUS_CLIENT_APPROVED, Trip::STATUS_AWAITING_CLIENT_APPROVAL];

    public function __construct(private readonly TripEditor $editor) {}

    /**
     * @param  array<string, mixed>  $completion  the signatures (TripSignatures::forCompletion())
     * @param  array<string, mixed>  $data  optional last corrections (see UpdateTripAction)
     * @param  array<string, ?\Illuminate\Http\UploadedFile>  $files
     */
    public function execute(Trip $trip, User $actor, array $completion, array $data = [], array $files = []): Trip
    {
        TripActors::assertStaff($actor);

        return TripFiles::transaction(function (TripFiles $store) use ($trip, $actor, $completion, $data, $files) {
            $trip = TripLock::for($trip, 'final_approve', self::FROM);
            $from = $trip->status;

            $changes = $this->editor->apply($trip, $actor, $data, $files, $store);

            TripHistory::record($trip, TripEvent::FINAL_APPROVED, $actor, [
                'from' => $from,
                'to' => Trip::STATUS_COMPLETED,
                'changes' => $changes,
            ]);

            $this->editor->effects($trip, $actor);
            CompleteTrip::run($trip, $actor, $completion, $store);

            return $trip;
        });
    }
}
