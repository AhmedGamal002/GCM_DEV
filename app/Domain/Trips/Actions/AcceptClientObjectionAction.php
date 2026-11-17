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
 * FRD §1.14 — GCM accepts the client's objection: edits the trip and sends
 * it back to the client ("awaiting the client's approval" again). The FRD
 * clears the client's comment and attachment from the trip at this point;
 * the comment's text stays in the history row (decision ج8 — our reading:
 * the attachment is not kept). Same edit rules as UpdateTripAction.
 */
class AcceptClientObjectionAction
{
    public function __construct(private readonly TripEditor $editor) {}

    /**
     * @param  array<string, mixed>  $data  the corrections (see UpdateTripAction)
     * @param  array<string, ?\Illuminate\Http\UploadedFile>  $files
     */
    public function execute(Trip $trip, User $actor, array $data = [], array $files = []): Trip
    {
        TripActors::assertStaff($actor);

        return TripFiles::transaction(function (TripFiles $store) use ($trip, $actor, $data, $files) {
            $trip = TripLock::for($trip, 'accept_objection', [Trip::STATUS_CLIENT_ISSUE]);
            $objection = $trip->client_comment;

            $trip->client_comment = null;
            $store->clear($trip, 'client_attachment_path');

            $changes = $this->editor->apply($trip, $actor, $data, $files, $store);

            $trip->status = Trip::STATUS_AWAITING_CLIENT_APPROVAL;
            $trip->save();

            TripHistory::record($trip, TripEvent::OBJECTION_ACCEPTED, $actor, [
                'from' => Trip::STATUS_CLIENT_ISSUE,
                'to' => Trip::STATUS_AWAITING_CLIENT_APPROVAL,
                'reason' => $objection,
                'changes' => $changes,
            ]);

            $this->editor->effects($trip, $actor);

            return $trip;
        });
    }
}
