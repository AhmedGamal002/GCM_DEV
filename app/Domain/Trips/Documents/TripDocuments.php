<?php

namespace App\Domain\Trips\Documents;

use App\Domain\Trips\Support\TripFieldVisibility;
use App\Models\Trip;
use App\Models\User;

/**
 * A trip's generated documents (FRD V01.14 §1.14 "الوثائق المولدة تلقائياً", part 8.1):
 *
 *  - the delivery note (DN-100001) — proof the trip was served, with the client's signature;
 *  - the waste manifest (M-100001) — the trip from the waste-transport angle (generator /
 *    transporter / receiver);
 *  - the combined PDF (staff only): delivery note → waste manifest → recycle receipt (the
 *    uploaded photo), leaving out what the trip doesn't have (د10 (3)).
 *
 * When (د3): only once the trip is COMPLETED (the final approval), generated on request from the
 * trip's current data — no stored copy, so editing a completed trip updates its documents; only the
 * number is fixed at the first generation (TripDocumentNumber, د2).
 *
 * Our reading (change it here if corrected): a SUPPLYING trip has no waste manifest — it carries no
 * waste (ج11), the manifest lists only the waste sub-services and capacities (the FRD's manifest has no
 * supplying list), and it documents a waste-transport chain (generator → transporter → receiving
 * facility) a water delivery doesn't have. Its delivery note shows no manifest number.
 */
final class TripDocuments
{
    public const DELIVERY_NOTE = 'delivery-note';

    public const WASTE_MANIFEST = 'waste-manifest';

    public const RECYCLE_RECEIPT = 'recycle-receipt';

    /** kind => number prefix (د2). */
    public const PREFIXES = [
        self::DELIVERY_NOTE => 'DN',
        self::WASTE_MANIFEST => 'M',
    ];

    /**
     * The generated documents the trip has, whoever asks.
     *
     * @return list<string>
     */
    public static function available(Trip $trip): array
    {
        if (! $trip->isCompleted()) {
            return [];
        }

        $trip->loadMissing('mainService');

        return $trip->mainService?->isSupplying()
            ? [self::DELIVERY_NOTE]
            : [self::DELIVERY_NOTE, self::WASTE_MANIFEST];
    }

    /**
     * The generated documents `$user` may open on `$trip` (the trip itself must be visible to them —
     * the controller looks it up through Trip::scopeVisibleTo).
     *
     * @return list<string>
     */
    public static function for(User $user, Trip $trip): array
    {
        return array_values(array_intersect(self::available($trip), TripFieldVisibility::documentKinds($user)));
    }

    /** Whether `$user` may download the combined PDF of `$trip`. */
    public static function canExport(User $user, Trip $trip): bool
    {
        return TripFieldVisibility::exportsDocuments($user) && self::available($trip) !== [];
    }

    /** "DN-100001" / "M-100001", or null before the number is reserved. */
    public static function code(Trip $trip, string $kind): ?string
    {
        return $trip->document_number === null ? null : self::PREFIXES[$kind].'-'.$trip->document_number;
    }

    /** One generated document as PDF bytes (reserves the trip's number on its first generation). */
    public static function render(Trip $trip, string $kind): string
    {
        TripDocumentNumber::reserve($trip);

        return TripDocumentRenderer::pdf($trip, [$kind]);
    }

    /** The combined PDF: delivery note → waste manifest → recycle receipt, each only if the trip has it. */
    public static function renderBundle(Trip $trip): string
    {
        TripDocumentNumber::reserve($trip);

        return TripDocumentRenderer::pdf($trip, [...self::available($trip), self::RECYCLE_RECEIPT]);
    }
}
