<?php

namespace App\Domain\Trips\Support;

use App\Models\Trip;
use App\Models\User;

/**
 * Who sees which field of a trip — decision ج18 (2026-11-10) in one place.
 * TripResource, the trips export and anything later that prints a trip
 * (notifications, documents, reports — 8.x) ask here; a field a role may not
 * see is not sent at all (not hidden by the page).
 *
 *  - GCM staff (System Admin, Data Entry, Auditor) see everything.
 *  - Trip cost and ancillary cost: nobody else (client manager / auditor,
 *    driver, contractor user).
 *  - PO number: the client's project manager only (besides staff). The
 *    client auditor, the driver and the contractor user see whether the
 *    trip is linked to a PO, not which.
 *  - Intermediate facility: the driver and the contractor user (not the client).
 *  - Document numbers: the driver and the contractor user see the delivery
 *    note's only; the client sees none.
 *
 * Our readings for what ج18 doesn't name (change them here if corrected):
 * the client's comment is the client's and staff's; the additional data, the
 * default approvers, who approved / created, the signatures and the trip's
 * history are staff only (ج8 for the history).
 */
final class TripFieldVisibility
{
    public static function isStaff(User $user): bool
    {
        return $user->hasAnyRole(Trip::STAFF_VIEW_ROLES);
    }

    public static function seesCosts(User $user): bool
    {
        return self::isStaff($user);
    }

    public static function seesPurchaseOrderNumber(User $user): bool
    {
        return self::isStaff($user) || $user->hasRole('client_project_manager');
    }

    public static function seesFacility(User $user): bool
    {
        return self::isStaff($user) || self::isFieldSide($user);
    }

    /** @return list<string> the document-number columns the user sees */
    public static function documentNumbers(User $user): array
    {
        return match (true) {
            self::isStaff($user) => ['delivery_note_number', 'waste_manifest_number', 'recycle_receipt_number'],
            self::isFieldSide($user) => ['delivery_note_number'],
            default => [],
        };
    }

    public static function seesClientComment(User $user): bool
    {
        return self::isStaff($user) || $user->isClient();
    }

    /** Additional data, approvers, signatures, files, who created / approved, the history (ج8). */
    public static function seesInternals(User $user): bool
    {
        return self::isStaff($user);
    }

    /** The driver and the contractor user — the field side of the trip. */
    private static function isFieldSide(User $user): bool
    {
        return $user->hasRole('driver') || $user->isContractorUser();
    }
}
