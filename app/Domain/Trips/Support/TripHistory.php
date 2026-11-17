<?php

namespace App\Domain\Trips\Support;

use App\Domain\Trips\Events\TripEventRecorded;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;

/**
 * Writes one row of a trip's history (decision ج8) and announces it once
 * the transaction commits (TripEventRecorded). Every trip action calls it
 * inside its own transaction, so the history can't disagree with the trip.
 */
final class TripHistory
{
    /**
     * @param  array{from?: ?string, to?: ?string, reason?: ?string, changes?: ?array}  $details
     */
    public static function record(Trip $trip, string $type, ?User $actor, array $details = []): TripEvent
    {
        $event = TripEvent::create([
            'trip_id' => $trip->id,
            'type' => $type,
            'actor_id' => $actor?->id,
            'from_status' => $details['from'] ?? null,
            'to_status' => $details['to'] ?? null,
            'reason' => $details['reason'] ?? null,
            'changes' => ($details['changes'] ?? null) ?: null,
            'created_at' => now(),
        ]);

        TripEventRecorded::dispatch($event);

        return $event;
    }
}
