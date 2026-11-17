<?php

namespace App\Domain\Trips\Support;

use App\Models\Trip;
use App\Models\User;

/**
 * The one way a trip reaches `completed`, whichever path leads there (the
 * final approval — after the client's approval or without it —, rejecting
 * the client's objection, or creating a trip that needs no client approval):
 *
 *  1. the signatures: the client's uploaded by staff when the trip has none
 *     (د4), then the completing staff member's own (د5) — TripSignatures;
 *  2. the completion requirements (TripCompletionRequirements) — nothing
 *     completes without them;
 *  3. the status, who completed it and when;
 *  4. the containers move (TripAssetMovement, ج3).
 *
 * Runs inside the caller's transaction, on the locked row.
 */
final class CompleteTrip
{
    /**
     * @param  array<string, mixed>  $completion  the signature input (see TripSignatures::forCompletion())
     */
    public static function run(Trip $trip, User $actor, array $completion, TripFiles $files): void
    {
        TripSignatures::forCompletion($trip, $actor, $completion, $files);
        TripCompletionRequirements::assertMet($trip);

        $trip->status = Trip::STATUS_COMPLETED;
        $trip->completed_by = $actor->id;
        $trip->completed_at = now();
        $trip->updated_by = $actor->id;
        $trip->save();

        TripAssetMovement::apply($trip, $actor);
    }
}
