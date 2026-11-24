<?php

namespace App\Domain\Trips\Documents;

use App\Domain\Trips\Support\TripFiles;
use App\Models\AssetCapacityCategory;
use App\Models\SubService;
use App\Models\Trip;
use App\Models\VehicleDocument;
use Illuminate\Support\Facades\Storage;

/**
 * What the generated documents print, read from the trip's CURRENT data (د3) — the FRD's tables
 * "مصدر المعلومات" for the delivery note and the waste manifest, one key per element:
 *
 *  - the service lists (د8): EVERY sub-service under the main services of each type
 *    (non-hazardous / hazardous / supplying) with a mark on the trip's one, and EVERY asset capacity
 *    with a mark on the trip container's — no cap, so a long list runs over more pages. Our reading
 *    of د8 (a): the active ones, plus the trip's own even if it was deactivated since;
 *  - the skip colour codes (manifest): the fixed list of SubService::SKIP_COLORS, the sub-service's marked;
 *  - the client's representative (د6): the name, signature and stamp snapshotted on the trip when it
 *    was approved (or uploaded by staff, د4) — never the account's current ones;
 *  - GCM's representative (د5): whoever gave the final approval — the signature and stamp snapshotted
 *    then, the name and phone from their account;
 *  - the vehicle's permit (د7): the entry permit valid on the trip's date (the latest if several),
 *    empty if none — so the document doesn't change when the permit later expires;
 *  - dates (د9): 2026-11-03, Riyadh time, Latin digits.
 *
 * Anything printed of the trip follows TripFieldVisibility (ج18) — these documents carry no cost, PO
 * number or paper-document number (the generated numbers are the ones printed, د1), and the facility
 * only on the manifest, which the driver and the contractor user don't open.
 */
final class TripDocumentData
{
    /** What the documents read from the trip — loaded once. */
    public const RELATIONS = [
        'company', 'project', 'driver.user', 'vehicle.embeddedCapacityCategory', 'goingAsset.capacityCategory',
        'returningAsset.capacityCategory', 'mainService', 'subService', 'facility', 'completedBy',
    ];

    /** @return array<string, mixed> */
    public static function for(Trip $trip): array
    {
        $trip->loadMissing(self::RELATIONS);
        $date = $trip->trip_at?->copy()->timezone(config('app.timezone'))->format('Y-m-d');
        $selectedSub = $trip->sub_service_id;

        return [
            'trip_code' => $trip->code,
            'delivery_note_number' => TripDocuments::code($trip, TripDocuments::DELIVERY_NOTE),
            'waste_manifest_number' => in_array(TripDocuments::WASTE_MANIFEST, TripDocuments::available($trip), true)
                ? TripDocuments::code($trip, TripDocuments::WASTE_MANIFEST)
                : null,
            'company' => $trip->company?->name,
            'project' => $trip->project?->name,
            'date' => $date,
            'driver' => $trip->driver?->user?->name,
            'vehicle' => $trip->vehicle?->plate(),
            'permit' => self::permit($trip),
            'facility' => $trip->facility?->name,
            'remarks' => self::plainText($trip->additional_data),

            'services' => [
                'non_hazardous' => self::subServices('non_hazardous', $selectedSub),
                'hazardous' => self::subServices('hazardous', $selectedSub),
                'supplying' => self::subServices('supplying', $selectedSub),
            ],
            'capacities' => self::capacities(self::containerCapacityId($trip)),
            'colors' => self::colors($trip->subService?->skip_color_code),

            'client' => [
                'name' => $trip->client_signer_name,
                'signature' => self::image($trip->client_signature_path),
                'stamp' => self::image($trip->client_stamp_path),
            ],
            'gcm' => [
                'name' => $trip->completedBy?->name,
                'phone' => $trip->completedBy?->phone,
                'signature' => self::image($trip->gcm_signature_path),
                'stamp' => self::image($trip->gcm_stamp_path),
            ],
            'receipt' => [
                'number' => $trip->recycle_receipt_number,
                'image' => self::image($trip->recycle_receipt_path, withSize: true),
            ],
        ];
    }

    /**
     * The trip container whose capacity is marked: the vehicle's embedded one, else the returning
     * container, else the going one (a supplying trip has no waste quantity — ج11 — but still a tank).
     */
    private static function containerCapacityId(Trip $trip): ?int
    {
        if ($trip->vehicle?->has_embedded_container) {
            return $trip->vehicle->embedded_asset_capacity_category_id;
        }

        return $trip->returningAsset?->asset_capacity_category_id ?? $trip->goingAsset?->asset_capacity_category_id;
    }

    /** @return list<array{label: string, checked: bool}> */
    private static function subServices(string $type, ?int $selected): array
    {
        return SubService::query()
            ->join('main_services', 'main_services.id', '=', 'sub_services.main_service_id')
            ->where('main_services.service_type', $type)
            ->where(fn ($q) => $q->where('sub_services.operational_status', 'active')
                ->when($selected !== null, fn ($q) => $q->orWhere('sub_services.id', $selected)))
            ->orderBy('main_services.name')
            ->orderBy('sub_services.name')
            ->orderBy('sub_services.id')
            ->get(['sub_services.id', 'sub_services.name'])
            ->map(fn (SubService $s) => ['label' => $s->name, 'checked' => $s->id === $selected])
            ->all();
    }

    /** @return list<array{label: string, checked: bool}> every capacity, smallest first */
    private static function capacities(?int $selected): array
    {
        return AssetCapacityCategory::query()
            ->orderBy('capacity_cbm')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (AssetCapacityCategory $c) => ['label' => $c->name, 'checked' => $c->id === $selected])
            ->all();
    }

    /** @return list<array{label: string, hex: ?string, checked: bool}> — the documents are English: the labels untranslated */
    private static function colors(?string $selected): array
    {
        return collect(SubService::SKIP_COLORS)
            ->map(fn (array $color, string $slug) => ['label' => $color[0], 'hex' => $color[1], 'checked' => $slug === $selected])
            ->values()
            ->all();
    }

    /** د7: the vehicle's entry permit valid on the trip's date — the latest-ending if several, null if none. */
    private static function permit(Trip $trip): ?string
    {
        if ($trip->vehicle === null || $trip->trip_at === null) {
            return null;
        }

        return VehicleDocument::query()
            ->where('vehicle_id', $trip->vehicle_id)
            ->where('type', 'entry_permit')
            ->where('valid_to', '>=', $trip->trip_at->copy()->timezone(config('app.timezone'))->format('Y-m-d'))
            ->orderByDesc('valid_to')
            ->orderByDesc('id')
            ->value('document_number');
    }

    /**
     * A file of the trip (private disk) as a data: URI DomPDF embeds — never a path or URL it would fetch.
     * Only images: a PDF (allowed for some uploads) can't be drawn on a page, so it is left out.
     *
     * @return array{src: string, width: int, height: int}|string|null
     */
    private static function image(?string $path, bool $withSize = false): array|string|null
    {
        $disk = Storage::disk(TripFiles::DISK);

        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        $bytes = $disk->get($path);
        $info = @getimagesizefromstring($bytes);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            return null;
        }

        $src = 'data:'.$info['mime'].';base64,'.base64_encode($bytes);

        return $withSize ? ['src' => $src, 'width' => $info[0], 'height' => $info[1]] : $src;
    }

    /** The trip's notes (Quill rich text, already sanitized) as plain lines. */
    private static function plainText(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = preg_replace('#<\s*br\s*/?>#i', "\n", $html) ?? $html;
        $text = preg_replace('#</\s*(p|li|h[1-6]|blockquote|pre)\s*>#i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), fn ($l) => $l !== ''));

        return $lines === [] ? null : implode("\n", $lines);
    }
}
