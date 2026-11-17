<?php

namespace App\Domain\Trips\Actions;

use App\Domain\Trips\Support\TripActors;
use App\Domain\Trips\Support\TripFiles;
use App\Domain\Trips\Support\TripHistory;
use App\Domain\Trips\Support\TripLock;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * FRD §1.14 — the client rejects a trip that awaits their approval ("Client
 * Issue"), with the reasons in the comment and optional attachments. The
 * reason is REQUIRED (decision ج8: the confirmation asks for "the detailed
 * reasons", though the FRD's table marks it optional). A sub-service the
 * client changed on the page is not kept when they reject (ج7). The trip
 * keeps its quota until it is cancelled or completed (ج1). There is no
 * limit on how often a trip goes back and forth (ج8). OTP on the route (7.3).
 */
class ClientRejectTripAction
{
    public function execute(Trip $trip, User $client, ?string $reason, ?UploadedFile $attachment = null): Trip
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('Explain why the trip is rejected.')]);
        }

        return TripFiles::transaction(function (TripFiles $files) use ($trip, $client, $reason, $attachment) {
            $trip = TripLock::for($trip, 'client_reject', [Trip::STATUS_AWAITING_CLIENT_APPROVAL]);
            TripActors::assertClientApprover($client, $trip);

            $trip->client_comment = $reason;

            if ($attachment !== null) {
                $files->put($trip, 'client_attachment_path', $attachment, 'attachment');
            }

            $trip->status = Trip::STATUS_CLIENT_ISSUE;
            $trip->updated_by = $client->id;
            $trip->save();

            TripHistory::record($trip, TripEvent::CLIENT_REJECTED, $client, [
                'from' => Trip::STATUS_AWAITING_CLIENT_APPROVAL,
                'to' => Trip::STATUS_CLIENT_ISSUE,
                'reason' => $reason,
            ]);

            return $trip;
        });
    }
}
