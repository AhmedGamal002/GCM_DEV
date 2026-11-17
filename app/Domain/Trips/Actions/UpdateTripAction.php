<?php

namespace App\Domain\Trips\Actions;

use App\Domain\Trips\Support\TripActors;
use App\Domain\Trips\Support\TripEditor;
use App\Domain\Trips\Support\TripFiles;
use App\Domain\Trips\Support\TripHistory;
use App\Domain\Trips\Support\TripLock;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;

/**
 * GCM's edit of a trip (system admin / data entry): any data, at any time
 * — awaiting the client, approved by them, or completed (decision ج9: no
 * lock on old trips) — WITHOUT changing its status. Changing the date,
 * project, main service or PO gives back the old quota and charges again
 * (ب7); new containers on a completed trip move again (ج3). The history
 * keeps every changed field from → to (ج8).
 *
 * Not here: a trip the client rejected is edited by accepting the
 * objection (AcceptClientObjectionAction — it goes back to the client), and
 * a cancelled trip is final. The driver never edits (FRD).
 */
class UpdateTripAction
{
    public const FROM = [Trip::STATUS_AWAITING_CLIENT_APPROVAL, Trip::STATUS_CLIENT_APPROVED, Trip::STATUS_COMPLETED];

    public function __construct(private readonly TripEditor $editor) {}

    /**
     * @param  array<string, mixed>  $data  only the keys sent change (see CreateTripByStaffAction)
     * @param  array<string, ?\Illuminate\Http\UploadedFile>  $files
     */
    public function execute(Trip $trip, User $actor, array $data, array $files = []): Trip
    {
        TripActors::assertStaff($actor);

        return TripFiles::transaction(function (TripFiles $store) use ($trip, $actor, $data, $files) {
            $trip = TripLock::for($trip, 'update', self::FROM);

            $changes = $this->editor->apply($trip, $actor, $data, $files, $store);

            if ($changes !== []) {
                TripHistory::record($trip, TripEvent::UPDATED, $actor, [
                    'from' => $trip->status, 'to' => $trip->status, 'changes' => $changes,
                ]);
            }

            $this->editor->effects($trip, $actor);

            return $trip;
        });
    }
}
