<?php

namespace App\Domain\Trips\Support;

use App\Domain\Trips\Documents\TripDocuments;
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
 * the client's comment is the client's and staff's; the default CLIENT
 * approver is the client's and staff's (a column of the FRD's client list);
 * the client opens the live photo and its own attachment; the additional
 * data, GCM's approver, who approved / created, the signatures and the
 * trip's history are staff only (ج8 for the history).
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

    /**
     * The trip's default client approver — a column of the client's trips list (FRD "المسؤول الافتراضي عن
     * اعتماد الرحلة", part 7.5), so the client sees it too; the field side doesn't.
     */
    public static function seesClientApprover(User $user): bool
    {
        return self::isStaff($user) || $user->isClient();
    }

    /**
     * The trip files (TripFiles kinds) the user may open. Staff: all. The client (FRD client details page,
     * part 7.5): the live photo and the client's own attachment — not the signatures, the stamps or the
     * staff's documents (the generated documents come with 8.1). The field side opens theirs in 7.6 / 7.7.
     *
     * @return list<string>
     */
    public static function fileKinds(User $user): array
    {
        return match (true) {
            self::isStaff($user) => array_column(TripFiles::COLUMNS, 0),
            $user->isClient() => ['live-photo', 'client-attachment'],
            default => [],
        };
    }

    /**
     * The generated documents (part 8.1) the user may open — decision د10 and the plan's visibility line:
     * GCM staff and the client's accounts (manager AND auditor — د10 (1)) the delivery note and the waste
     * manifest, the driver and the contractor user the delivery note only. Whether a trip HAS them (completed,
     * not supplying) is TripDocuments::available().
     *
     * @return list<string> TripDocuments kinds
     */
    public static function documentKinds(User $user): array
    {
        return match (true) {
            self::isStaff($user), $user->isClient() => [TripDocuments::DELIVERY_NOTE, TripDocuments::WASTE_MANIFEST],
            self::isFieldSide($user) => [TripDocuments::DELIVERY_NOTE],
            default => [],
        };
    }

    /** The one combined PDF of a trip's documents — GCM staff only; the client previews, never exports (د10 (2)). */
    public static function exportsDocuments(User $user): bool
    {
        return self::isStaff($user);
    }

    /** Additional data, GCM's approver, signatures, who created / approved, the history (ج8). */
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
