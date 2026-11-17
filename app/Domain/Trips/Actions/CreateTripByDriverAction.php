<?php

namespace App\Domain\Trips\Actions;

use App\Domain\Trips\Quota\TripPurchaseOrderLine;
use App\Domain\Trips\Quota\TripQuota;
use App\Domain\Trips\Support\TripActors;
use App\Domain\Trips\Support\TripFiles;
use App\Domain\Trips\Support\TripHistory;
use App\Domain\Trips\Support\TripInput;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * FRD §1.14 — the driver creates a trip "with the minimum of data", and it
 * waits for the client's approval. The driver is the one acting (shown, not
 * chosen), the date and time are the moment of creation (not editable), and
 * the driver picks the company, project, vehicle (the default one
 * prefilled), main service, optionally the sub-service and the two
 * containers, and must attach the live location and a live photo
 * (decision ج12 — no gallery upload, no hand-typed link: the page enforces
 * the "live" part; here both are simply required).
 *
 * The driver picks no PO: the trip is charged to the newest PO of the
 * project that still has balance for the service (ب5, TripPurchaseOrderLine),
 * with that line's costs; a project with no PO for the service gives a trip
 * "without PO" whose cost GCM enters later. The quantity defaults to the
 * container's capacity. After this the driver can't edit the trip — only
 * attach the recycle receipt photo (AttachRecycleReceiptByDriverAction).
 */
class CreateTripByDriverAction
{
    /** What the driver's form may send (plus company_id / project_id). */
    public const FIELDS = ['vehicle_id', 'going_asset_id', 'returning_asset_id', 'main_service_id', 'sub_service_id', 'location_url', 'additional_data'];

    public function __construct(private readonly TripQuota $quota) {}

    public function execute(User $actor, array $data, ?UploadedFile $livePhoto, ?UploadedFile $otherAttachment = null): Trip
    {
        TripActors::assertDriver($actor);

        $missing = array_filter([
            'location_url' => blank($data['location_url'] ?? null) ? __('The trip\'s live location is required.') : null,
            'live_photo' => $livePhoto === null ? __('A live photo from the site is required.') : null,
        ]);

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }

        return TripFiles::transaction(function (TripFiles $files) use ($actor, $data, $livePhoto, $otherAttachment) {
            $trip = new Trip;
            $trip->source = Trip::SOURCE_DRIVER;
            $trip->status = Trip::STATUS_AWAITING_CLIENT_APPROVAL;
            $trip->requires_client_approval = true;
            $trip->driver_id = $actor->driver->id;
            $trip->trip_at = now();
            $trip->created_by = $actor->id;
            $trip->updated_by = $actor->id;

            TripInput::apply($trip, Arr::only($data, ['company_id', 'project_id', ...self::FIELDS]), true, self::FIELDS);

            $line = TripPurchaseOrderLine::defaultFor($trip->project, (int) $trip->main_service_id, $trip->trip_at);
            $trip->requires_purchase_order = $line !== null;

            if ($line !== null) {
                TripQuota::priceFrom($trip, $line);
            }

            $trip->save();

            $files->put($trip, 'live_photo_path', $livePhoto, 'live_photo');

            if ($otherAttachment !== null) {
                $files->put($trip, 'other_attachment_path', $otherAttachment, 'other_attachment');
            }

            $trip->save();

            $this->quota->charge($trip, $actor);

            TripHistory::record($trip, TripEvent::CREATED, $actor, ['to' => $trip->status]);

            return $trip;
        });
    }
}
