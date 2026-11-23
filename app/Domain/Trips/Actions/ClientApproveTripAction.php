<?php

namespace App\Domain\Trips\Actions;

use App\Domain\Trips\Options\TripCompatibility;
use App\Domain\Trips\Options\TripOptions;
use App\Domain\Trips\Support\TripActors;
use App\Domain\Trips\Support\TripChanges;
use App\Domain\Trips\Support\TripFiles;
use App\Domain\Trips\Support\TripHistory;
use App\Domain\Trips\Support\TripLock;
use App\Domain\Trips\Support\TripSignatures;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * FRD §1.14 — the client approves a trip that awaits their approval: any
 * manager or auditor of the project's company who sees the project (ج6).
 *
 * On the way they may change the sub-service under the same main service
 * (the FRD; the quota is on the main service, so nothing is re-charged).
 * If the trip's facility doesn't serve the new sub-service it is cleared,
 * and GCM picks a fitting one before the final approval — the client's
 * approval isn't blocked by it (decision ج7); the history records the
 * change. The new sub-service must suit the trip's container like on the
 * staff form (TripOptions::subServices — the FRD's "تتناسب مع نوع الحاوية
 * وسعتها"). They sign — their saved signature or one drawn now, saved to
 * their profile only if they ask — and their own stamp (if any) is copied
 * with it (د6), unless they upload a stamp on the page (the FRD form's
 * optional "الختم التشغيلي" file: a snapshot on this trip only, never put
 * on the account); an optional comment and attachment go with the approval.
 * The OTP check (7.3) sits on the route, before this.
 */
class ClientApproveTripAction
{
    /**
     * @param  array{sub_service_id?: mixed, comment?: ?string, attachment?: ?UploadedFile,
     *               signature?: ?UploadedFile, use_saved_signature?: bool, save_signature?: bool,
     *               stamp?: ?UploadedFile}  $input
     */
    public function execute(Trip $trip, User $client, array $input = []): Trip
    {
        return TripFiles::transaction(function (TripFiles $files) use ($trip, $client, $input) {
            $trip = TripLock::for($trip, 'client_approve', [Trip::STATUS_AWAITING_CLIENT_APPROVAL]);
            TripActors::assertClientApprover($client, $trip);

            $before = TripChanges::snapshot($trip);

            if (filled($input['sub_service_id'] ?? null) && (int) $input['sub_service_id'] !== (int) $trip->sub_service_id) {
                $this->changeSubService($trip, $input['sub_service_id']);
            }

            $trip->client_comment = self::text($input['comment'] ?? null);

            if (($input['attachment'] ?? null) instanceof UploadedFile) {
                $files->put($trip, 'client_attachment_path', $input['attachment'], 'attachment');
            }

            TripSignatures::fromClientApproval($trip, $client, $input, $files);

            $trip->status = Trip::STATUS_CLIENT_APPROVED;
            $trip->client_approved_by = $client->id;
            $trip->client_approved_at = now();
            $trip->updated_by = $client->id;
            $trip->save();

            TripHistory::record($trip, TripEvent::CLIENT_APPROVED, $client, [
                'from' => Trip::STATUS_AWAITING_CLIENT_APPROVAL,
                'to' => Trip::STATUS_CLIENT_APPROVED,
                'reason' => $trip->client_comment,
                'changes' => TripChanges::diff($before, TripChanges::snapshot($trip)),
            ]);

            return $trip;
        });
    }

    private function changeSubService(Trip $trip, mixed $id): void
    {
        $sub = is_numeric($id) ? self::choices($trip)->with('facilities')->find((int) $id) : null;

        if ($sub === null) {
            throw ValidationException::withMessages([
                'sub_service_id' => __('Choose an active sub-service of the trip\'s main service that suits its container.'),
            ]);
        }

        $trip->sub_service_id = $sub->id;

        if ($trip->intermediate_facility_id !== null && ! $sub->facilities->contains('id', (int) $trip->intermediate_facility_id)) {
            $trip->intermediate_facility_id = null;
        }
    }

    /**
     * The sub-services the client may switch to: active, of the trip's main service, suiting the trip's
     * container (type and capacity) — the staff form's list (TripOptions), so both sides offer the same.
     */
    public static function choices(Trip $trip): Builder
    {
        $trip->loadMissing(['vehicle.embeddedCapacityCategory', 'goingAsset.capacityCategory', 'returningAsset.capacityCategory']);

        return TripOptions::subServices(
            (int) $trip->main_service_id,
            TripCompatibility::container($trip->vehicle, $trip->goingAsset, $trip->returningAsset),
        );
    }

    private static function text(?string $text): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : $text;
    }
}
