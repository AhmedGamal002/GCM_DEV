<?php

namespace App\Domain\Trips\Documents;

use App\Models\Tenant;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;

/**
 * The number of a trip's generated documents — decision د2: one number per
 * trip shared by both documents (DN-100001 / M-100001), one sequence per
 * tenant across all its trips, from 100001. Reserved the first time a
 * document of the trip is generated (د3) and never changed after (the column
 * is locked in Trip::booted()); a trip cancelled after that keeps its number,
 * so the sequence may have gaps.
 *
 * Two generations at the same moment (the same trip opened twice, or two
 * trips) are serialized: the trip's row is locked and re-read (a second
 * request sees the number the first one reserved), then the tenant's row,
 * and the highest number read with a locking read — a plain read would see
 * the transaction's older snapshot on MySQL (CLAUDE.md). unique(tenant_id,
 * document_number) is the backstop.
 */
final class TripDocumentNumber
{
    public const FIRST = 100001;

    public static function reserve(Trip $trip): int
    {
        if ($trip->document_number !== null) {
            return (int) $trip->document_number;
        }

        $number = DB::transaction(function () use ($trip) {
            $current = Trip::whereKey($trip->id)->lockForUpdate()->value('document_number');

            if ($current !== null) {
                return (int) $current;
            }

            Tenant::whereKey($trip->tenant_id)->lockForUpdate()->value('id');

            $last = Trip::query()
                ->where('tenant_id', $trip->tenant_id)
                ->lockForUpdate()
                ->max('document_number');

            $next = max(self::FIRST, ((int) $last) + 1);

            // toBase(): reserving the number is not an edit of the trip — no updated_at / updated_by, no history row.
            Trip::whereKey($trip->id)->toBase()->update(['document_number' => $next]);

            return $next;
        });

        $trip->setRawAttributes(array_merge($trip->getAttributes(), ['document_number' => $number]), true);

        return $number;
    }
}
