<?php

namespace App\Domain\Vehicles\Actions;

use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateVehicleAction
{
    /**
     * operational_status is deliberately not handled here — it goes
     * exclusively through UpdateVehicleStatusAction.
     *
     * @param  array<string, mixed>  $data  validated, non-file input
     * @param  array{
     *     photo_front?: ?UploadedFile,
     *     photo_back?: ?UploadedFile,
     *     documents?: array<string, ?UploadedFile>,
     *     entry_permits?: array<int, ?UploadedFile>
     * }  $files
     */
    public function execute(Vehicle $vehicle, array $data, array $files = []): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $data, $files) {
            $vehicle->fill([
                'plate_letters' => $data['plate_letters'],
                'plate_numbers' => $data['plate_numbers'],
                'vehicle_category_id' => $data['vehicle_category_id'],
                'has_embedded_container' => $data['has_embedded_container'],
                'embedded_container_type' => $data['has_embedded_container'] ? $data['embedded_container_type'] : null,
                'embedded_asset_capacity_category_id' => $data['has_embedded_container'] ? $data['embedded_asset_capacity_category_id'] : null,
                'additional_data' => $data['additional_data'] ?? null,
            ]);

            if (! empty($files['photo_front'])) {
                $vehicle->photo_front = $files['photo_front']->store('vehicle-photos', 'public');
            }
            if (! empty($files['photo_back'])) {
                $vehicle->photo_back = $files['photo_back']->store('vehicle-photos', 'public');
            }

            $vehicle->save();

            $this->syncSingleDocuments($vehicle, $data['documents'] ?? [], $files['documents'] ?? []);
            $this->syncEntryPermits($vehicle, $data['entry_permits'] ?? [], $files['entry_permits'] ?? []);

            return $vehicle->load(['category', 'embeddedCapacityCategory', 'documents']);
        });
    }

    /**
     * Upsert each single document by type — update in place, keeping the
     * existing attachment when no new file is uploaded.
     *
     * @param  array<string, array{number: string, valid_to: string}>  $documents
     * @param  array<string, ?UploadedFile>  $files
     */
    private function syncSingleDocuments(Vehicle $vehicle, array $documents, array $files): void
    {
        foreach (VehicleDocument::SINGLE_TYPES as $type) {
            if (empty($documents[$type])) {
                continue;
            }

            $attachment = $files[$type] ?? null;
            $existing = $vehicle->documents()->where('type', $type)->first();

            $attributes = [
                'document_number' => $documents[$type]['number'],
                'valid_to' => $documents[$type]['valid_to'],
            ];

            if ($attachment) {
                if ($existing?->attachment_path) {
                    Storage::disk('local')->delete($existing->attachment_path);
                }
                $attributes['attachment_path'] = $attachment->store("vehicle-documents/{$vehicle->id}", 'local');
            }

            if ($existing) {
                $existing->update($attributes);
            } else {
                $vehicle->documents()->create(['type' => $type] + $attributes);
            }
        }
    }

    /**
     * Upsert entry permits by their id (the edit form sends a hidden id
     * for existing rows, empty for new ones). Rows present keep their
     * attachment unless a new file is uploaded; rows dropped from the
     * form are deleted along with their file.
     *
     * @param  array<int, array{id?: ?int, area_name: string, permit_number: string, valid_to: string}>  $permits
     * @param  array<int, ?UploadedFile>  $files
     */
    private function syncEntryPermits(Vehicle $vehicle, array $permits, array $files): void
    {
        $keptIds = [];

        foreach ($permits as $i => $permit) {
            $attachment = $files[$i] ?? null;
            $existing = empty($permit['id'])
                ? null
                : $vehicle->entryPermits()->find($permit['id']);

            $attributes = [
                'area_name' => $permit['area_name'],
                'document_number' => $permit['permit_number'],
                'valid_to' => $permit['valid_to'],
            ];

            if ($attachment) {
                if ($existing?->attachment_path) {
                    Storage::disk('local')->delete($existing->attachment_path);
                }
                $attributes['attachment_path'] = $attachment->store("vehicle-documents/{$vehicle->id}", 'local');
            }

            if ($existing) {
                $existing->update($attributes);
                $keptIds[] = $existing->id;
            } else {
                $keptIds[] = $vehicle->documents()->create(['type' => 'entry_permit'] + $attributes)->id;
            }
        }

        // Anything not in the submitted set is gone.
        $vehicle->entryPermits()
            ->when($keptIds, fn ($q) => $q->whereNotIn('id', $keptIds))
            ->get()
            ->each(function (VehicleDocument $old) {
                if ($old->attachment_path) {
                    Storage::disk('local')->delete($old->attachment_path);
                }
                $old->delete();
            });
    }
}
