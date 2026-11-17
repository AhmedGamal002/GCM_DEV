<?php

namespace App\Domain\Trips\Support;

use App\Models\Trip;
use Illuminate\Validation\ValidationException;

/**
 * What a trip must have before it is "completed" — checked whenever it
 * reaches `completed`, by any of its paths (the final approval, with or
 * without the client; rejecting the client's objection; creating a trip
 * that needs no client approval), and again when a completed trip is
 * edited, so a completed trip never stops meeting them:
 *
 *  - a sub-service (the driver may leave it out; GCM completes it), and an
 *    intermediate facility linked to that sub-service unless the main
 *    service is a supplying one — decision ج7: the final approval waits for
 *    a facility that fits (the client changing the sub-service clears an
 *    unfit one);
 *  - the recycle receipt's number AND photo when the sub-service requires a
 *    recycle receipt — decision ج13 (the delivery note and the waste
 *    manifest stay optional);
 *  - the trip cost when the trip is "without PO" (the FRD: required then —
 *    a driver's trip without PO has none until GCM enters it);
 *  - the client's signature — their approval's snapshot, or uploaded by
 *    staff when the trip completes without them (decision د4);
 *  - the signature of GCM's representative, whoever completes it (د5).
 *
 * Errors carry the input's field name, so a form shows them where they belong.
 */
final class TripCompletionRequirements
{
    /** @return array<string, string> field => message, empty when the trip can complete */
    public static function missing(Trip $trip): array
    {
        $trip->loadMissing(['mainService', 'subService.facilities']);
        $missing = [];
        $sub = $trip->subService;

        if ($sub === null) {
            $missing['sub_service_id'] = __('Choose the sub-service before completing the trip.');
        }

        if (! $trip->mainService->isSupplying()) {
            if ($trip->intermediate_facility_id === null) {
                $missing['intermediate_facility_id'] = __('Choose the intermediate facility before completing the trip.');
            } elseif ($sub !== null && ! $sub->facilities->contains('id', $trip->intermediate_facility_id)) {
                $missing['intermediate_facility_id'] = __('The intermediate facility does not serve the trip\'s sub-service.');
            }
        }

        if ($sub?->requires_recycle_receipt) {
            if (blank($trip->recycle_receipt_number)) {
                $missing['recycle_receipt_number'] = __('This sub-service requires the recycle receipt number.');
            }

            if ($trip->recycle_receipt_path === null) {
                $missing['recycle_receipt'] = __('This sub-service requires the recycle receipt photo.');
            }
        }

        if ($trip->isWithoutPurchaseOrder() && $trip->trip_cost === null) {
            $missing['trip_cost'] = __('Enter the trip cost: the trip has no purchase order.');
        }

        if (! $trip->hasClientSignature()) {
            $missing['client_signature'] = __('Upload the client\'s signature: the trip is completed without the client\'s approval.');
        }

        if ($trip->gcm_signature_path === null) {
            $missing['gcm_signature'] = __('A signature is required: use your saved signature or sign now.');
        }

        return $missing;
    }

    public static function assertMet(Trip $trip): void
    {
        $missing = self::missing($trip);

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }
    }
}
