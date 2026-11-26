<?php

namespace App\Domain\Reports;

use App\Domain\Trips\Documents\TripDocuments;
use App\Domain\Trips\Support\TripCapacity;
use App\Domain\Trips\Support\TripFieldVisibility;
use App\Models\Trip;
use App\Models\User;

/**
 * The trips summary (FRD V01.14 "تقرير ملخص الرحلات (Trips Summary)", part 8.4): one row per trip with the FRD's columns
 * — No., trip No., client company, project, date, time, delivery note No., manifest No., sub-service, container
 * capacity, trip status. The page's table and the Excel both build their rows here.
 *
 * The DN / manifest columns are the trip's GENERATED document numbers (DN-100001 / M-100001 — the ones printed on the
 * delivery note and the waste manifest the client opens), not the paper numbers staff type into the form, which a
 * client may not see (ج18). The number is reserved when the trip is completed (CompleteTrip), so a trip not yet
 * completed shows "—". Readings of ours: a supply trip has
 * no manifest (8.1); the viewer must be allowed the document kind (TripFieldVisibility::documentKinds).
 */
final class TripsSummary
{
    /** What a row reads, eager-loaded so the export of thousands of trips stays a handful of queries. */
    public const RELATIONS = ['company', 'project', 'subService', 'mainService', 'vehicle.embeddedCapacityCategory', 'returningAsset.capacityCategory'];

    /** @return list<string> the untranslated headings */
    public static function headings(): array
    {
        return ['No.', 'Trip No.', 'Client company', 'Project', 'Date', 'Time', 'Delivery Note No.', 'Waste Manifest No.', 'Sub-service', 'Container capacity', 'Trip status'];
    }

    /** @return array<string, ?string> one trip, keyed (the page's table) */
    public static function row(Trip $trip, User $viewer): array
    {
        $kinds = TripFieldVisibility::documentKinds($viewer);
        $available = TripDocuments::available($trip);
        $code = fn (string $kind) => in_array($kind, $kinds, true) && in_array($kind, $available, true) ? TripDocuments::code($trip, $kind) : null;
        $capacity = TripCapacity::of($trip);

        return [
            'id' => (string) $trip->id,
            'code' => $trip->code,
            'company' => $trip->company?->name,
            'project' => $trip->project?->name,
            'date' => $trip->trip_at?->format('Y-m-d'),
            'time' => $trip->trip_at?->format('H:i'),
            'delivery_note' => $code(TripDocuments::DELIVERY_NOTE),
            'waste_manifest' => $code(TripDocuments::WASTE_MANIFEST),
            'sub_service' => $trip->subService?->name,
            'capacity' => $capacity?->name,
            'status' => $trip->status,
            'status_label' => Trip::statusLabel($trip->status),
        ];
    }

    /** @return list<?string> one trip as an Excel line, after its running number */
    public static function line(int $number, Trip $trip, User $viewer): array
    {
        $row = self::row($trip, $viewer);

        return [$number, $row['code'], $row['company'], $row['project'], $row['date'], $row['time'], $row['delivery_note'] ?? '—', $row['waste_manifest'] ?? '—', $row['sub_service'], $row['capacity'], $row['status_label']];
    }
}
