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
 * FRD §1.14 — GCM rejects the client's objection and completes the trip
 * without the client's approval. Like every path to `completed`
 * (CompleteTrip): the completion requirements, the client's signature
 * uploaded by staff (the client never signed — د4) and the acting staff
 * member's own (د5); optional last corrections on the way. The client's
 * comment stays on the trip. OTP on the route (7.3).
 */
class RejectClientObjectionAction
{
    public function __construct(private readonly TripEditor $editor) {}

    /**
     * @param  array<string, mixed>  $completion  the signatures (TripSignatures::forCompletion())
     * @param  array<string, mixed>  $data  optional corrections (see UpdateTripAction)
     * @param  array<string, ?\Illuminate\Http\UploadedFile>  $files
     */
    public function execute(Trip $trip, User $actor, array $completion, array $data = [], array $files = []): Trip
    {
        TripActors::assertStaff($actor);

        return TripFiles::transaction(function (TripFiles $store) use ($trip, $actor, $completion, $data, $files) {
            $trip = TripLock::for($trip, 'reject_objection', [Trip::STATUS_CLIENT_ISSUE]);

            $changes = $this->editor->apply($trip, $actor, $data, $files, $store);

            TripHistory::record($trip, TripEvent::OBJECTION_REJECTED, $actor, [
                'from' => Trip::STATUS_CLIENT_ISSUE,
                'to' => Trip::STATUS_COMPLETED,
                'reason' => $trip->client_comment,
                'changes' => $changes,
            ]);

            $this->editor->effects($trip, $actor);
            CompleteTrip::run($trip, $actor, $completion, $store);

            return $trip;
        });
    }
}
