<?php

namespace App\Domain\Trips\Support;

use App\Models\Asset;
use App\Models\Driver;
use App\Models\IntermediateFacility;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrderService;
use App\Models\SubService;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Str;

/**
 * "What changed" for the trip's history (decision ج8: who edited it, when,
 * and every field from → to). snapshot() before the action, snapshot()
 * after, diff() the two: only the fields that changed, as readable values
 * taken at that moment (a name, a code, a plate, a PO number — not ids), so
 * the history still reads right after a service is renamed or a PO edited.
 * Files show as attached / replaced / removed, never their paths.
 */
final class TripChanges
{
    /** attribute => label key in `changes` + how to read it. */
    private const TRACKED = [
        'trip_at' => ['trip_at', 'datetime'],
        'project_id' => ['project', Project::class],
        'driver_id' => ['driver', Driver::class],
        'vehicle_id' => ['vehicle', Vehicle::class],
        'going_asset_id' => ['going_asset', Asset::class],
        'returning_asset_id' => ['returning_asset', Asset::class],
        'main_service_id' => ['main_service', MainService::class],
        'sub_service_id' => ['sub_service', SubService::class],
        'requires_purchase_order' => ['requires_purchase_order', 'bool'],
        'purchase_order_service_id' => ['purchase_order', PurchaseOrderService::class],
        'trip_cost' => ['trip_cost', 'scalar'],
        'ancillary_cost' => ['ancillary_cost', 'scalar'],
        'quantity_ton' => ['quantity_ton', 'scalar'],
        'quantity_cbm' => ['quantity_cbm', 'scalar'],
        'intermediate_facility_id' => ['intermediate_facility', IntermediateFacility::class],
        'delivery_note_number' => ['delivery_note_number', 'scalar'],
        'waste_manifest_number' => ['waste_manifest_number', 'scalar'],
        'recycle_receipt_number' => ['recycle_receipt_number', 'scalar'],
        'location_url' => ['location_url', 'scalar'],
        'additional_data' => ['additional_data', 'text'],
        'client_approver_id' => ['client_approver', User::class],
        'gcm_approver_id' => ['gcm_approver', User::class],
        'delivery_note_path' => ['delivery_note', 'file'],
        'waste_manifest_path' => ['waste_manifest', 'file'],
        'recycle_receipt_path' => ['recycle_receipt', 'file'],
        'live_photo_path' => ['live_photo', 'file'],
        'other_attachment_path' => ['other_attachment', 'file'],
        'client_attachment_path' => ['client_attachment', 'file'],
    ];

    /** @return array<string, mixed> attribute => comparable raw value */
    public static function snapshot(Trip $trip): array
    {
        $out = [];

        foreach (self::TRACKED as $attribute => [, $kind]) {
            $value = $trip->getAttribute($attribute);

            $out[$attribute] = match (true) {
                $value === null => null,
                $kind === 'datetime' => $value->format('Y-m-d H:i'),
                $kind === 'bool' => (bool) $value,
                self::isModel($kind) => (int) $value,
                default => (string) $value,
            };
        }

        return $out;
    }

    /** @return array<string, array{from: mixed, to: mixed}> label => from / to, only what changed */
    public static function diff(array $before, array $after): array
    {
        $changes = [];

        foreach (self::TRACKED as $attribute => [$label, $kind]) {
            $from = $before[$attribute] ?? null;
            $to = $after[$attribute] ?? null;

            if ($from === $to) {
                continue;
            }

            $changes[$label] = match (true) {
                $kind === 'file' => ['from' => $from === null ? null : 'attached', 'to' => $to === null ? 'removed' : ($from === null ? 'attached' : 'replaced')],
                $kind === 'text' => ['from' => self::text($from), 'to' => self::text($to)],
                self::isModel($kind) => ['from' => self::label($kind, $from), 'to' => self::label($kind, $to)],
                default => ['from' => $from, 'to' => $to],
            };
        }

        return $changes;
    }

    /** A model class, not one of the plain kinds (class_exists('datetime') is true — DateTime). */
    private static function isModel(string $kind): bool
    {
        return str_starts_with($kind, 'App\\Models\\');
    }

    private static function label(string $model, ?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        return match ($model) {
            Project::class => Project::whereKey($id)->value('code'),
            Driver::class => Driver::with('user')->find($id)?->user?->name,
            Vehicle::class => Vehicle::find($id)?->plate(),
            PurchaseOrderService::class => PurchaseOrderService::with('purchaseOrder')->find($id)?->purchaseOrder?->number,
            default => $model::whereKey($id)->value('name'),
        };
    }

    private static function text(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html))));

        return $text === '' ? null : Str::limit($text, 200);
    }
}
