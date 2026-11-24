<?php

namespace App\Http\Resources;

use App\Domain\Trips\Actions\CancelTripAction;
use App\Domain\Trips\Actions\FinalApproveTripAction;
use App\Domain\Trips\Actions\UpdateTripAction;
use App\Domain\Trips\Documents\TripDocuments;
use App\Domain\Trips\Support\TripCapacity;
use App\Domain\Trips\Support\TripFieldVisibility;
use App\Domain\Trips\Support\TripFiles;
use App\Domain\Trips\Support\WasteQuantityConverter;
use App\Models\Asset;
use App\Models\AssetCapacityCategory;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/**
 * A trip as the API returns it (part 7.4). The fields each role sees follow
 * decision ج18 (TripFieldVisibility) — a field a role may not see is absent
 * from the JSON, not hidden by the page. The history (`events`, ج8) is sent
 * on the details endpoint only, to GCM staff only.
 *
 * `actions` tells a staff page which buttons apply to the trip's status now;
 * the actions themselves re-check (409 when the status moved meanwhile).
 *
 * @mixin Trip
 */
class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $staff = TripFieldVisibility::isStaff($user);
        $internals = TripFieldVisibility::seesInternals($user);
        $documents = TripFieldVisibility::documentNumbers($user);
        $po = $this->purchaseOrderService?->purchaseOrder;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'status_label' => Trip::statusLabel($this->status),
            'source' => $this->source,
            'trip_at' => $this->trip_at?->toIso8601String(),
            'requires_client_approval' => (bool) $this->requires_client_approval,
            'requires_purchase_order' => (bool) $this->requires_purchase_order,

            'company' => $this->company ? ['id' => $this->company->id, 'name' => $this->company->name, 'code' => $this->company->code] : null,
            'project' => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'code' => $this->project->code,
                'location_url' => $this->project->location_url,
                // ج18: "the field officer in the project" = the project's representative.
                'representative' => $this->project->relationLoaded('representative') ? $this->person($this->project->representative) : new MissingValue,
            ] : null,

            'driver' => $this->driver ? [
                'id' => $this->driver->id,
                'name' => $this->driver->user?->name,
                'code' => $this->driver->user?->code,
                'phone' => $this->driver->user?->phone,
                ...$this->affiliation($this->driver->contractor_id === null ? 'gcm' : 'contractor', $this->driver->contractor),
            ] : null,
            'vehicle' => $this->vehicle ? [
                'id' => $this->vehicle->id,
                'plate' => $this->vehicle->plate(),
                'category' => $this->vehicle->category ? ['id' => $this->vehicle->category->id, 'name' => $this->vehicle->category->name()] : null,
                'has_embedded_container' => (bool) $this->vehicle->has_embedded_container,
                'embedded_container_type' => $this->vehicle->embedded_container_type,
                ...$this->affiliation($this->vehicle->affiliation, $this->vehicle->contractor),
            ] : null,
            'going_asset' => $this->asset($this->goingAsset),
            'returning_asset' => $this->asset($this->returningAsset),
            'capacity' => $this->capacity(TripCapacity::of($this->resource)),

            'main_service' => $this->mainService ? ['id' => $this->mainService->id, 'name' => $this->mainService->name, 'service_type' => $this->mainService->service_type] : null,
            'sub_service' => $this->subService ? [
                'id' => $this->subService->id,
                'name' => $this->subService->name,
                'requires_recycle_receipt' => (bool) $this->subService->requires_recycle_receipt,
            ] : null,
            'quantity_ton' => $this->quantity_ton === null ? null : (float) $this->quantity_ton,
            'quantity_cbm' => $this->quantity_cbm === null ? null : (float) $this->quantity_cbm,
            'quantity_converter' => WasteQuantityConverter::toArray(),

            // ج18: everyone sees whether the trip is linked to a PO; which one only staff and the client's manager.
            'linked_to_purchase_order' => $this->purchase_order_service_id !== null,
            'purchase_order' => $this->when(
                TripFieldVisibility::seesPurchaseOrderNumber($user),
                fn () => $po ? ['id' => $po->id, 'number' => $po->number] : null,
            ),
            'trip_cost' => $this->when(TripFieldVisibility::seesCosts($user), fn () => $this->trip_cost === null ? null : (float) $this->trip_cost),
            'ancillary_cost' => $this->when(TripFieldVisibility::seesCosts($user), fn () => (float) $this->ancillary_cost),
            'facility' => $this->when(
                TripFieldVisibility::seesFacility($user),
                fn () => $this->facility ? ['id' => $this->facility->id, 'name' => $this->facility->name] : null,
            ),
            'delivery_note_number' => $this->when(in_array('delivery_note_number', $documents, true), $this->delivery_note_number),
            'waste_manifest_number' => $this->when(in_array('waste_manifest_number', $documents, true), $this->waste_manifest_number),
            'recycle_receipt_number' => $this->when(in_array('recycle_receipt_number', $documents, true), $this->recycle_receipt_number),

            'location_url' => $this->location_url,
            'has_live_photo' => $this->live_photo_path !== null,
            'client_comment' => $this->when(TripFieldVisibility::seesClientComment($user), $this->client_comment),
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'client_approved_at' => $this->client_approved_at?->toIso8601String(),

            'additional_data' => $this->when($internals, $this->additional_data),
            'client_approver' => $this->detail(TripFieldVisibility::seesClientApprover($user), 'clientApprover'),
            'gcm_approver' => $this->detail($internals, 'gcmApprover'),
            'client_approved_by' => $this->detail($internals, 'clientApprovedBy'),
            'client_signer_name' => $this->when($internals, $this->client_signer_name),
            'completed_by' => $this->detail($internals, 'completedBy'),
            'cancelled_by' => $this->detail($internals, 'cancelledBy'),
            'created_by' => $this->detail($internals, 'createdBy'),
            'files' => $this->when(TripFieldVisibility::fileKinds($user) !== [], fn () => $this->files(TripFieldVisibility::fileKinds($user))),
            // The generated documents (8.1) the user may open — once the trip is completed (د3).
            'documents' => $this->when(TripFieldVisibility::documentKinds($user) !== [], fn () => $this->documents($user)),

            // "Last updated by … — date and time" (FRD details page, every role).
            'updated_by' => $this->person($this->updatedBy),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'actions' => $this->when($staff, fn () => $this->actions($user)),
            // The client's buttons (part 7.5): approve / reject while the trip awaits them and the account may (ج6).
            'client_actions' => $this->when($user->isClient(), fn () => $this->clientActions($user)),
            'events' => $this->when(
                $internals && $this->relationLoaded('events'),
                fn () => $this->events->map(fn (TripEvent $e) => [
                    'id' => $e->id,
                    'type' => $e->type,
                    'type_label' => TripEvent::typeLabel($e->type),
                    'actor' => $this->person($e->actor),
                    'from_status' => $e->from_status,
                    'to_status' => $e->to_status,
                    'reason' => $e->reason,
                    'changes' => $e->changes,
                    'created_at' => $e->created_at?->toIso8601String(),
                ])->values(),
                new MissingValue,
            ),
        ];
    }

    /** What a staff page may offer for the trip now (the actions re-check). */
    private function actions(User $user): array
    {
        $manages = $user->can('manage', Trip::class);
        $status = $this->status;

        return [
            'update' => $manages && in_array($status, UpdateTripAction::FROM, true),
            'final_approve' => $manages && in_array($status, FinalApproveTripAction::FROM, true),
            'accept_objection' => $manages && $status === Trip::STATUS_CLIENT_ISSUE,
            'reject_objection' => $manages && $status === Trip::STATUS_CLIENT_ISSUE,
            'cancel' => $manages && in_array($status, CancelTripAction::FROM, true),
            'remind_client' => $manages && $status === Trip::STATUS_AWAITING_CLIENT_APPROVAL,
        ];
    }

    /** What the client's details page may offer — the approve / reject page (the action re-checks). */
    private function clientActions(User $user): array
    {
        $may = false;

        if ($this->status === Trip::STATUS_AWAITING_CLIENT_APPROVAL) {
            // Whether the account may approve depends on the project only — asked once per project and request
            // (no query per list row).
            $memo = request()->attributes->get('trip.client_review', []);
            $memo[$this->project_id] ??= $user->can('clientReview', $this->resource);
            request()->attributes->set('trip.client_review', $memo);
            $may = $memo[$this->project_id];
        }

        return ['approve' => $may, 'reject' => $may];
    }

    /**
     * @param  list<string>  $kinds  the kinds the user may open (TripFieldVisibility::fileKinds)
     * @return array<string, ?string> file kind => inline URL (null = none)
     */
    private function files(array $kinds): array
    {
        $out = [];

        foreach (TripFiles::COLUMNS as $column => [$kind]) {
            if (! in_array($kind, $kinds, true)) {
                continue;
            }

            $out[$kind] = $this->{$column} === null ? null : url("/api/v1/trips/{$this->id}/files/{$kind}");
        }

        return $out;
    }

    /**
     * The trip's generated documents for `$user` (part 8.1): each one they may open with its number
     * (null until the first generation reserves it — د2) and inline URL, and the combined PDF's URL for
     * GCM staff. Empty lists before the trip is completed (د3).
     *
     * @return array{delivery_note?: array{number: ?string, url: string}, waste_manifest?: array{number: ?string, url: string}, export_url: ?string}
     */
    private function documents(User $user): array
    {
        $out = [];

        foreach (TripDocuments::for($user, $this->resource) as $kind) {
            $out[str_replace('-', '_', $kind)] = [
                'number' => TripDocuments::code($this->resource, $kind),
                'url' => url("/api/v1/trips/{$this->id}/documents/{$kind}"),
            ];
        }

        $out['export_url'] = TripDocuments::canExport($user, $this->resource) ? url("/api/v1/trips/{$this->id}/documents/export") : null;

        return $out;
    }

    private function asset(?Asset $asset): ?array
    {
        return $asset === null ? null : [
            'id' => $asset->id,
            'name' => $asset->name,
            'asset_type' => $asset->asset_type,
            'capacity' => $this->capacity($asset->capacityCategory),
            ...$this->affiliation($asset->affiliation, $asset->contractor),
        ];
    }

    private function capacity(?AssetCapacityCategory $capacity): ?array
    {
        return $capacity === null ? null : [
            'id' => $capacity->id,
            'name' => $capacity->name,
            'capacity_ton' => (float) $capacity->capacity_ton,
            'capacity_cbm' => (float) $capacity->capacity_cbm,
        ];
    }

    private function affiliation(?string $affiliation, $contractor): array
    {
        return [
            'affiliation' => $affiliation,
            'entity_name' => match ($affiliation) {
                'gcm' => app('tenant')->name,
                'contractor' => $contractor?->name,
                default => null,
            },
        ];
    }

    /** A person shown on the details page only (the list doesn't load them — no query per row). */
    private function detail(bool $sees, string $relation): mixed
    {
        return $sees && $this->relationLoaded($relation) ? $this->person($this->{$relation}) : new MissingValue;
    }

    private function person(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
    }
}
