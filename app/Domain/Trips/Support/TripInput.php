<?php

namespace App\Domain\Trips\Support;

use App\Domain\Trips\Quota\TripPurchaseOrderLine;
use App\Domain\Trips\Quota\TripQuota;
use App\Models\Asset;
use App\Models\Driver;
use App\Models\IntermediateFacility;
use App\Models\MainService;
use App\Models\Project;
use App\Models\SubService;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Puts a trip action's input on the trip and checks that the result hangs
 * together — the server re-checks every choice, the form's lists are no
 * guarantee (security checklist §1.3):
 *
 *  - the ids exist in this tenant (every model here is tenant-scoped);
 *  - the project belongs to the chosen company, and a trip's project only
 *    ever moves to another project of the SAME company (the trip number is
 *    the company's — our reading of ج14's "the number never changes");
 *  - the sub-service belongs to the main service, the facility serves the
 *    sub-service;
 *  - the PO is one of the project's, not deactivated, and covers the trip's
 *    main service (TripPurchaseOrderLine) — or the trip is "without PO" with
 *    a hand-entered cost;
 *  - a supplying trip carries no quantity (ج11), a waste trip's quantity is
 *    completed from the unit given (ج10) and defaults to the container's
 *    capacity (TripCapacity);
 *  - the default approvers are a client account that can approve the
 *    project's trips (ج6) and an active system admin / data entry.
 *
 * Which resources are AVAILABLE for a new trip (active, the contractor not
 * deactivated, the container not busy, compatible with the vehicle…) is the
 * cascading-choice logic of part 7.2 (TripResourceAvailability), not this.
 *
 * Only the keys present in `$data` change the trip (an edit may send one
 * field). Nothing is saved here.
 */
final class TripInput
{
    /** The trip's plain data (Trip::$fillable). */
    public const DATA = [
        'trip_at', 'driver_id', 'vehicle_id', 'going_asset_id', 'returning_asset_id',
        'main_service_id', 'sub_service_id', 'quantity_ton', 'quantity_cbm', 'intermediate_facility_id',
        'delivery_note_number', 'waste_manifest_number', 'recycle_receipt_number',
        'location_url', 'additional_data', 'client_approver_id', 'gcm_approver_id',
    ];

    /** Upload input => trip column. */
    public const FILES = [
        'delivery_note' => 'delivery_note_path',
        'waste_manifest' => 'waste_manifest_path',
        'recycle_receipt' => 'recycle_receipt_path',
        'live_photo' => 'live_photo_path',
        'other_attachment' => 'other_attachment_path',
    ];

    private const IDS = [
        'driver_id' => Driver::class,
        'vehicle_id' => Vehicle::class,
        'going_asset_id' => Asset::class,
        'returning_asset_id' => Asset::class,
        'main_service_id' => MainService::class,
        'sub_service_id' => SubService::class,
        'intermediate_facility_id' => IntermediateFacility::class,
        'client_approver_id' => User::class,
        'gcm_approver_id' => User::class,
    ];

    /**
     * The trip's data, project and default approvers (not the PO — see pricing()).
     *
     * @param  list<string>  $allowed  the keys of DATA this caller may set
     */
    public static function apply(Trip $trip, array $data, bool $creating, array $allowed = self::DATA): void
    {
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        self::project($trip, $data, $creating);

        foreach (array_intersect($allowed, array_keys($data)) as $key) {
            $trip->{$key} = $key === 'trip_at' ? self::dateTime($data[$key]) : $data[$key];
        }

        self::assertExists($trip);
        self::assertServices($trip);
        self::assertLocation($trip);
        self::quantity($trip, $data, $creating);
        self::approvers($trip, $data, $creating);
    }

    /**
     * The PO side of a staff create / edit: "needs a PO" + the chosen PO → the
     * line and its costs; "no PO" → the hand-entered costs (ب6 / ب7).
     */
    public static function pricing(Trip $trip, array $data, bool $creating): void
    {
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        // An edit that touches neither the PO nor what it hangs on keeps the trip's line and costs as they
        // are — even when that PO has been deactivated since (the trip was charged to it legitimately).
        $touched = $creating
            || array_intersect(['requires_purchase_order', 'purchase_order_id', 'trip_cost', 'ancillary_cost'], array_keys($data)) !== []
            || $trip->isDirty(TripQuota::RECHARGE_ON);

        if (! $touched) {
            return;
        }

        $requires = array_key_exists('requires_purchase_order', $data)
            ? filter_var($data['requires_purchase_order'], FILTER_VALIDATE_BOOLEAN)
            : ($creating ? true : $trip->requires_purchase_order);

        $trip->requires_purchase_order = $requires;
        $project = Project::findOrFail($trip->project_id);

        if (! $requires) {
            $wasWithout = ! $creating && $trip->getOriginal('purchase_order_service_id') === null;
            $trip->purchase_order_service_id = null;
            $trip->trip_cost = array_key_exists('trip_cost', $data) ? self::money($data['trip_cost'], 'trip_cost', true) : ($wasWithout ? $trip->trip_cost : null);
            $trip->ancillary_cost = array_key_exists('ancillary_cost', $data) ? (self::money($data['ancillary_cost'], 'ancillary_cost', true) ?? 0) : ($wasWithout ? $trip->ancillary_cost : 0);

            return;
        }

        if (($data['purchase_order_id'] ?? null) !== null) {
            $line = TripPurchaseOrderLine::resolve($project, (int) $trip->main_service_id, $data['purchase_order_id']);

            if ($creating || (int) $line->id !== (int) $trip->getOriginal('purchase_order_service_id') || TripQuota::needsRecharge($trip)) {
                TripQuota::priceFrom($trip, $line);
            }

            return;
        }

        $current = $trip->purchaseOrderService()->with('purchaseOrder')->first();
        $stillFits = $current !== null
            && (int) $current->purchaseOrder->project_id === (int) $trip->project_id
            && (int) $current->main_service_id === (int) $trip->main_service_id;

        if (! $stillFits) {
            throw ValidationException::withMessages(
                TripPurchaseOrderLine::projectHasAny($project, (int) $trip->main_service_id)
                    ? ['purchase_order_id' => __('Choose the purchase order the trip is charged to.')]
                    : ['requires_purchase_order' => __('The project has no purchase order for this service: mark the trip as not needing a purchase order.')]
            );
        }

        // ب7: a new date (project / service can't keep the old line) re-prices from the line as it is now.
        if (TripQuota::needsRecharge($trip)) {
            TripQuota::priceFrom($trip, $current);
        }
    }

    /** @param  array<string, ?UploadedFile>  $files  input name => upload (absent / null = unchanged) */
    public static function files(Trip $trip, array $files, TripFiles $store): void
    {
        foreach (self::FILES as $input => $column) {
            if (($files[$input] ?? null) instanceof UploadedFile) {
                $store->put($trip, $column, $files[$input], $input);
            }
        }
    }

    private static function project(Trip $trip, array $data, bool $creating): void
    {
        if (! array_key_exists('project_id', $data) && ! $creating) {
            return;
        }

        $project = is_numeric($data['project_id'] ?? null) ? Project::find((int) $data['project_id']) : null;

        if ($project === null) {
            throw ValidationException::withMessages(['project_id' => __('Choose the trip\'s project.')]);
        }

        $company = $creating ? ($data['company_id'] ?? $project->company_id) : $trip->company_id;

        if ((int) $project->company_id !== (int) $company) {
            throw ValidationException::withMessages([
                'project_id' => $creating
                    ? __('The project does not belong to the chosen client company.')
                    : __('A trip can only move to another project of the same client company.'),
            ]);
        }

        if ($creating) {
            $trip->company_id = $project->company_id;
        }

        $trip->project_id = $project->id;
        $trip->setRelation('project', $project);
    }

    private static function assertExists(Trip $trip): void
    {
        foreach (self::IDS as $key => $model) {
            $id = $trip->{$key};

            if ($id === null || ! $trip->isDirty($key)) {
                continue;
            }

            if (! is_numeric($id) || ! $model::whereKey((int) $id)->exists()) {
                throw ValidationException::withMessages([$key => __('The selected :attribute is invalid.', ['attribute' => __('validation.attributes.'.$key)])]);
            }
        }

        foreach (['driver_id', 'vehicle_id', 'main_service_id'] as $required) {
            if ($trip->{$required} === null) {
                throw ValidationException::withMessages([$required => __('validation.required', ['attribute' => __('validation.attributes.'.$required)])]);
            }
        }
    }

    private static function assertServices(Trip $trip): void
    {
        $sub = $trip->sub_service_id === null ? null : SubService::with('facilities')->find($trip->sub_service_id);

        if ($sub !== null && (int) $sub->main_service_id !== (int) $trip->main_service_id) {
            throw ValidationException::withMessages([
                'sub_service_id' => __('The sub-service does not belong to the trip\'s main service.'),
            ]);
        }

        if ($trip->intermediate_facility_id !== null && ($trip->isDirty('intermediate_facility_id') || $trip->isDirty('sub_service_id'))) {
            if ($sub === null || ! $sub->facilities->contains('id', (int) $trip->intermediate_facility_id)) {
                throw ValidationException::withMessages([
                    'intermediate_facility_id' => __('The intermediate facility does not serve the trip\'s sub-service.'),
                ]);
            }
        }
    }

    /** The trip's location is shown as a link: http(s) only (no javascript: URLs); the paper numbers fit their columns. */
    private static function assertLocation(Trip $trip): void
    {
        $url = $trip->location_url;

        if ($url !== null && (mb_strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            throw ValidationException::withMessages(['location_url' => __('The trip location must be a web link (http or https).')]);
        }

        foreach (['delivery_note_number', 'waste_manifest_number', 'recycle_receipt_number'] as $number) {
            if ($trip->{$number} !== null && mb_strlen((string) $trip->{$number}) > 100) {
                throw ValidationException::withMessages([$number => __('validation.max.string', ['attribute' => __('validation.attributes.'.$number), 'max' => 100])]);
            }
        }
    }

    private static function quantity(Trip $trip, array $data, bool $creating): void
    {
        $mainService = MainService::find($trip->main_service_id);

        if ($mainService->isSupplying()) {
            $trip->quantity_ton = null;
            $trip->quantity_cbm = null;

            return;
        }

        $given = array_key_exists('quantity_ton', $data) || array_key_exists('quantity_cbm', $data);

        if ($given) {
            $quantity = WasteQuantityConverter::complete(
                self::number($data['quantity_ton'] ?? null, 'quantity_ton'),
                self::number($data['quantity_cbm'] ?? null, 'quantity_cbm'),
            );
        } elseif ($creating || $trip->getOriginal('quantity_ton') === null) {
            // No quantity yet (a new trip, or one that was a supplying trip): the container's capacity.
            $trip->unsetRelation('mainService')->unsetRelation('vehicle')->unsetRelation('returningAsset');
            $quantity = TripCapacity::defaultQuantity($trip);
        } else {
            return;
        }

        $trip->quantity_ton = $quantity['ton'];
        $trip->quantity_cbm = $quantity['cbm'];
    }

    private static function approvers(Trip $trip, array $data, bool $creating): void
    {
        $project = $trip->project;
        $resolver = app(ClientApproverResolver::class);

        if (array_key_exists('client_approver_id', $data) && $trip->client_approver_id !== null) {
            if (! $resolver->canApproveFor(User::find($trip->client_approver_id), $project)) {
                throw ValidationException::withMessages([
                    'client_approver_id' => __('The default client approver must be an active account of the client that sees the project.'),
                ]);
            }
        } elseif ($creating || $trip->isDirty('project_id')) {
            // Prefilled with the project's representative (FRD), again when the project changes.
            $trip->client_approver_id = $resolver->defaultFor($project)?->id;
        }

        if (array_key_exists('gcm_approver_id', $data) && $trip->gcm_approver_id !== null) {
            $user = User::find($trip->gcm_approver_id);

            if ($user === null || $user->status !== 'active' || ! TripActors::isStaff($user)) {
                throw ValidationException::withMessages([
                    'gcm_approver_id' => __('The default GCM approver must be an active system admin or data entry account.'),
                ]);
            }
        }
    }

    private static function dateTime(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['trip_at' => __('The trip date and time is not valid.')]);
        }
    }

    private static function number(mixed $value, string $field): ?float
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value) || (float) $value < 0) {
            throw ValidationException::withMessages([$field => __('Enter a quantity of zero or more.')]);
        }

        return (float) $value;
    }

    private static function money(mixed $value, string $field, bool $nullable): ?float
    {
        if ($value === null && $nullable) {
            return null;
        }

        if (! is_numeric($value) || (float) $value < 0) {
            throw ValidationException::withMessages([$field => __('Enter an amount of zero or more.')]);
        }

        return round((float) $value, 2);
    }
}
