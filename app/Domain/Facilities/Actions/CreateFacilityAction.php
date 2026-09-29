<?php

namespace App\Domain\Facilities\Actions;

use App\Models\IntermediateFacility;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateFacilityAction
{
    /**
     * @param  array<string, mixed>  $data  validated input
     * @param  array{logo?: ?UploadedFile, contract_attachment?: ?UploadedFile}  $files
     */
    public function execute(array $data, User $actor, array $files = []): IntermediateFacility
    {
        return DB::transaction(function () use ($data, $actor, $files) {
            // tenant_id is stamped by BelongsToTenant's creating hook.
            $facility = new IntermediateFacility([
                'name' => $data['name'],
                'prefix' => $data['prefix'],
                'environmental_service' => $data['environmental_service'],
                // Only meaningful for recycling; the request already rejects it otherwise.
                'recycling_efficiency' => $data['environmental_service'] === 'recycle'
                    ? $data['recycling_efficiency']
                    : null,
                'address' => $data['address'] ?? null,
                'location_url' => $data['location_url'] ?? null,
                'contract_number' => $data['contract_number'] ?? null,
                'contract_start' => $data['contract_start'] ?? null,
                'contract_end' => $data['contract_end'] ?? null,
                'additional_data' => $data['additional_data'] ?? null,
            ]);

            // operational_status is outside $fillable — set explicitly.
            $facility->operational_status = $data['operational_status'] ?? 'active';
            $facility->updated_by = $actor->id;

            if (! empty($files['logo'])) {
                $facility->logo = $files['logo']->store('facility-logos', 'public');
            }
            if (! empty($files['contract_attachment'])) {
                // Private: served only through the gated download endpoint.
                $facility->contract_attachment_path = $files['contract_attachment']->store('facility-contracts', 'local');
            }

            $facility->save();

            return $facility->load('updatedBy');
        });
    }
}
