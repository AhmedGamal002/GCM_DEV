<?php

namespace App\Domain\Facilities\Actions;

use App\Domain\Facilities\Exceptions\FacilityInUseException;
use App\Models\IntermediateFacility;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * FRD V01.14 §1.8 edit page: the name and the optional data are editable;
 * the prefix never is. The environmental service and the recycling
 * efficiency (which belongs to the service) are editable only while nothing
 * uses the facility — a client decision beyond the FRD text. Status has its
 * own action / endpoint.
 */
class UpdateFacilityAction
{
    private const OPTIONAL = ['address', 'location_url', 'contract_number', 'contract_start', 'contract_end', 'additional_data'];

    /**
     * @param  array<string, mixed>  $data  validated input
     * @param  array{logo?: ?UploadedFile, contract_attachment?: ?UploadedFile}  $files
     *
     * @throws FacilityInUseException
     */
    public function execute(IntermediateFacility $facility, array $data, User $actor, array $files = []): IntermediateFacility
    {
        return DB::transaction(function () use ($facility, $data, $actor, $files) {
            $this->applyServiceChange($facility, $data);

            $facility->name = $data['name'];
            foreach (self::OPTIONAL as $field) {
                if (array_key_exists($field, $data)) {
                    $facility->{$field} = $data[$field];
                }
            }

            if (! empty($files['logo'])) {
                if ($facility->logo) {
                    Storage::disk('public')->delete($facility->logo);
                }
                $facility->logo = $files['logo']->store('facility-logos', 'public');
            }
            if (! empty($files['contract_attachment'])) {
                if ($facility->contract_attachment_path) {
                    Storage::disk('local')->delete($facility->contract_attachment_path);
                }
                $facility->contract_attachment_path = $files['contract_attachment']->store('facility-contracts', 'local');
            }

            $facility->updated_by = $actor->id;
            $facility->save();

            return $facility->load('updatedBy');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyServiceChange(IntermediateFacility $facility, array $data): void
    {
        $service = $data['environmental_service'] ?? $facility->environmental_service;
        $efficiency = $service === 'recycle'
            ? ($data['recycling_efficiency'] ?? $facility->recycling_efficiency)
            : null;

        $changed = $service !== $facility->environmental_service
            || (float) $efficiency !== (float) $facility->recycling_efficiency
            || ($efficiency === null) !== ($facility->recycling_efficiency === null);

        if (! $changed) {
            return;
        }

        if ($facility->isInUse()) {
            throw new FacilityInUseException;
        }

        $facility->environmental_service = $service;
        $facility->recycling_efficiency = $efficiency;
    }
}
