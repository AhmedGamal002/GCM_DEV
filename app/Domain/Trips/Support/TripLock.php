<?php

namespace App\Domain\Trips\Support;

use App\Domain\Trips\Exceptions\TripTransitionNotAllowedException;
use App\Models\Trip;

/**
 * Every trip action starts here, inside its transaction: the trip's row is
 * re-read under a row lock (the latest committed state, not the copy the
 * caller holds) and its status checked against the statuses the action
 * starts from — so two people acting on the same trip at once are applied
 * one after the other, and the second one sees what the first did.
 */
final class TripLock
{
    /** @param  list<string>  $from  the statuses the action may start from */
    public static function for(Trip $trip, string $action, array $from): Trip
    {
        $locked = Trip::whereKey($trip->id)->lockForUpdate()->firstOrFail();

        if (! in_array($locked->status, $from, true)) {
            throw TripTransitionNotAllowedException::for($action, $locked);
        }

        return $locked;
    }
}
