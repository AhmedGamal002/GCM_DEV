<?php

namespace App\Domain\Trips\Actions;

use App\Domain\Trips\Support\TripActors;
use App\Domain\Trips\Support\TripChanges;
use App\Domain\Trips\Support\TripFiles;
use App\Domain\Trips\Support\TripHistory;
use App\Domain\Trips\Support\TripLock;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * FRD §1.4 (the driver's role) — the one change a driver may make to a trip
 * after creating it: attach the photo of the recycle receipt to their OWN
 * trip. Decision ج13: they may replace it until the trip is completed;
 * after that only GCM staff change it (and a cancelled trip is final).
 */
class AttachRecycleReceiptByDriverAction
{
    public const FROM = [Trip::STATUS_AWAITING_CLIENT_APPROVAL, Trip::STATUS_CLIENT_APPROVED, Trip::STATUS_CLIENT_ISSUE];

    public function execute(Trip $trip, User $driver, UploadedFile $receipt): Trip
    {
        TripActors::assertDriverOf($driver, $trip);

        return TripFiles::transaction(function (TripFiles $files) use ($trip, $driver, $receipt) {
            $trip = TripLock::for($trip, 'attach_recycle_receipt', self::FROM);
            $before = TripChanges::snapshot($trip);

            $files->put($trip, 'recycle_receipt_path', $receipt, 'recycle_receipt');
            $trip->updated_by = $driver->id;
            $trip->save();

            TripHistory::record($trip, TripEvent::RECYCLE_RECEIPT_ATTACHED, $driver, [
                'changes' => TripChanges::diff($before, TripChanges::snapshot($trip)),
            ]);

            return $trip;
        });
    }
}
