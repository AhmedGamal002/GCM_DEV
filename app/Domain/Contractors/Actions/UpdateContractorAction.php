<?php

namespace App\Domain\Contractors\Actions;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * FRD V01.14 §1.10.4: every contractor field is editable by (System Admin /
 * Data Entry) except the short name. A file input left empty keeps the
 * stored file; picking a new one replaces it. operational_status has its own
 * action. The certified services are replaced by whatever list is sent (an
 * empty list = "supports everything").
 */
class UpdateContractorAction
{
    /**
     * @param  array<string, mixed>  $data  validated, non-file input
     * @param  array{logo?: ?UploadedFile, contract?: ?UploadedFile, cr?: ?UploadedFile, tax?: ?UploadedFile}  $files
     */
    public function execute(Contractor $contractor, array $data, array $files, User $actor): Contractor
    {
        return DB::transaction(function () use ($contractor, $data, $files, $actor) {
            $contractor->fill($data);
            $contractor->updated_by = $actor->id;

            StoreContractorFiles::into($contractor, $files);

            $contractor->save();

            if (array_key_exists('main_service_ids', $data)) {
                $contractor->certifiedServices()->sync($data['main_service_ids'] ?? []);
            }

            return $contractor->load(['updatedBy', 'certifiedServices']);
        });
    }
}
