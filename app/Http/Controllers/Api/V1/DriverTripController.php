<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Trips\Actions\AttachRecycleReceiptByDriverAction;
use App\Domain\Trips\Actions\CreateTripByDriverAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trips\StoreDriverTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The driver's side of the trips (FRD V01.14 §1.14, part 7.6) — what a driver does besides the list and the
 * details he shares with TripController (his own trips only, Trip::scopeVisibleTo):
 *
 *  - POST /driver/trips                          create, with the live location and photo (ج12)
 *  - POST /driver/trips/{trip}/recycle-receipt   add / replace the recycle receipt photo until the trip completes (ج13)
 *
 * There is no edit: after creating, the receipt photo is the only thing a driver changes (FRD). Another driver's
 * trip is a 404 (TripController::find looks only among the trips the user sees).
 */
class DriverTripController extends Controller
{
    public function store(StoreDriverTripRequest $request, CreateTripByDriverAction $action)
    {
        $trip = $action->execute($request->user(), $request->tripData(), $request->file('live_photo'), $request->file('other_attachment'));

        return TripResource::make($this->reload($trip->id))->response()->setStatusCode(201);
    }

    public function attachRecycleReceipt(Request $request, int $trip, AttachRecycleReceiptByDriverAction $action)
    {
        $model = TripController::find($request, $trip);
        Gate::authorize('attachRecycleReceipt', $model);

        $request->validate([
            // A photo (FRD "صورة إيصال التدوير"): it is printed as a page of the trip's combined PDF (8.1) — no PDF.
            'recycle_receipt' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:'.config('trips.attachment_max_kb')],
        ]);

        $action->execute($model, $request->user(), $request->file('recycle_receipt'));

        return TripResource::make($this->reload($model->id));
    }

    private function reload(int $id): Trip
    {
        return Trip::with([
            'company', 'project', 'driver.user', 'driver.contractor', 'vehicle.contractor', 'vehicle.category',
            'vehicle.embeddedCapacityCategory', 'goingAsset.capacityCategory', 'goingAsset.contractor',
            'returningAsset.capacityCategory', 'returningAsset.contractor', 'mainService', 'subService',
            'purchaseOrderService.purchaseOrder', 'facility', 'updatedBy', 'clientApprover',
        ])->findOrFail($id);
    }
}
