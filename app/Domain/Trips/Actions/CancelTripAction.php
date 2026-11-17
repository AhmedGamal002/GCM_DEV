<?php

namespace App\Domain\Trips\Actions;

use App\Domain\Trips\Quota\TripQuota;
use App\Domain\Trips\Support\TripActors;
use App\Domain\Trips\Support\TripAssetMovement;
use App\Domain\Trips\Support\TripFiles;
use App\Domain\Trips\Support\TripHistory;
use App\Domain\Trips\Support\TripLock;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;

/**
 * FRD §1.14 — the system admin or data entry cancels a trip at any stage,
 * even after the final approval (decision ج2), and it becomes "as if it never
 * was" for the PO and the moved resources: its quota is given back (ج1) and
 * the containers it moved go back where they were, when still where the
 * trip put them (ج3). The reason is optional (ج2) and shown in the trip's
 * details. A cancelled trip is final. OTP on the route (7.3).
 */
class CancelTripAction
{
    public const FROM = [Trip::STATUS_AWAITING_CLIENT_APPROVAL, Trip::STATUS_CLIENT_APPROVED, Trip::STATUS_CLIENT_ISSUE, Trip::STATUS_COMPLETED];

    public function __construct(private readonly TripQuota $quota) {}

    public function execute(Trip $trip, User $actor, ?string $reason = null): Trip
    {
        TripActors::assertStaff($actor);

        return TripFiles::transaction(function () use ($trip, $actor, $reason) {
            $trip = TripLock::for($trip, 'cancel', self::FROM);
            $from = $trip->status;
            $reason = trim((string) $reason);

            $trip->status = Trip::STATUS_CANCELLED;
            $trip->cancelled_by = $actor->id;
            $trip->cancelled_at = now();
            $trip->cancellation_reason = $reason === '' ? null : $reason;
            $trip->updated_by = $actor->id;
            $trip->save();

            TripHistory::record($trip, TripEvent::CANCELLED, $actor, [
                'from' => $from,
                'to' => Trip::STATUS_CANCELLED,
                'reason' => $trip->cancellation_reason,
            ]);

            $this->quota->release($trip, $actor);
            TripAssetMovement::revert($trip, $actor);

            return $trip;
        });
    }
}
