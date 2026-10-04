<?php

namespace App\Domain\Contractors\Actions;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateContractorAction
{
    /**
     * @param  array<string, mixed>  $data  validated, non-file input
     * @param  array{logo?: ?UploadedFile, contract?: ?UploadedFile, cr?: ?UploadedFile, tax?: ?UploadedFile}  $files
     */
    public function execute(array $data, array $files, User $actor): Contractor
    {
        return DB::transaction(function () use ($data, $files, $actor) {
            // tenant_id and code are stamped by BelongsToTenant / Contractor::booted().
            $contractor = new Contractor($data);

            // operational_status is outside $fillable — set explicitly.
            $contractor->operational_status = $data['operational_status'] ?? 'active';
            $contractor->updated_by = $actor->id;
            $contractor->save();

            // The attachments are stored under the contractor's id, which
            // only exists after the first save.
            StoreContractorFiles::into($contractor, $files);
            $contractor->save();

            $contractor->certifiedServices()->sync($data['main_service_ids'] ?? []);

            return $contractor->load(['updatedBy', 'certifiedServices']);
        });
    }
}
