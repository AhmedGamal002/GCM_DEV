<?php

namespace App\Domain\Vehicles\Actions;

use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateVehicleAction
{
    /**
     * @param  array<string, mixed>  $data  validated, non-file input
     * @param  array{
     *     photo_front?: ?UploadedFile,
     *     photo_back?: ?UploadedFile,
     *     documents?: array<string, ?UploadedFile>,
     *     entry_permits?: array<int, ?UploadedFile>
     * }  $files
     */
    public function execute(array $data, array $files = []): Vehicle
    {
        return DB::transaction(function () use ($data, $files) {
            // tenant_id is stamped by BelongsToTenant's creating hook.
            $vehicle = new Vehicle([
                'plate_letters' => $data['plate_letters'],
                'plate_numbers' => $data['plate_numbers'],
                'vehicle_category_id' => $data['vehicle_category_id'],
                'has_embedded_container' => $data['has_embedded_container'],
                'embedded_container_type' => $data['has_embedded_container'] ? $data['embedded_container_type'] : null,
                'embedded_asset_capacity_category_id' => $data['has_embedded_container'] ? $data['embedded_asset_capacity_category_id'] : null,
                // Contractor affiliation isn't selectable yet (no Contractor
                // entity until Week 5) — always 'gcm' for now.
                'affiliation' => 'gcm',
                'additional_data' => $data['additional_data'] ?? null,
            ]);

            // operational_status is outside $fillable — set explicitly.
            $vehicle->operational_status = $data['operational_status'] ?? 'active';
            $vehicle->updated_by = auth()->id();

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

            $vehicle->documents()->create([
                'type' => $type,
                'document_number' => $documents[$type]['number'],
                'valid_to' => $documents[$type]['valid_to'],
                'attachment_path' => $attachment
                    ? $attachment->store("vehicle-documents/{$vehicle->id}", 'local')
                    : null,
            ]);
        }
    }

    /**
     * @param  array<int, array{area_name: string, permit_number: string, valid_to: string}>  $permits
     * @param  array<int, ?UploadedFile>  $files
     */
    private function syncEntryPermits(Vehicle $vehicle, array $permits, array $files): void
    {
        foreach ($permits as $i => $permit) {
            $attachment = $files[$i] ?? null;

            $vehicle->documents()->create([
                'type' => 'entry_permit',
                'area_name' => $permit['area_name'],
                'document_number' => $permit['permit_number'],
                'valid_to' => $permit['valid_to'],
                'attachment_path' => $attachment
                    ? $attachment->store("vehicle-documents/{$vehicle->id}", 'local')
                    : null,
            ]);
        }
    }
}
