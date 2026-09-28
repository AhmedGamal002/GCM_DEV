<?php

namespace App\Domain\Companies\Actions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * FRD V01.14 §1.11.4: every company field is editable by (System Admin /
 * Data Entry). A file input left empty keeps the stored file; picking a
 * new one replaces it. operational_status has its own action.
 */
class UpdateCompanyAction
{
    /**
     * @param  array<string, mixed>  $data  validated, non-file input
     * @param  array{logo?: ?UploadedFile, contract?: ?UploadedFile, cr?: ?UploadedFile, tax?: ?UploadedFile}  $files
     */
    public function execute(Company $company, array $data, array $files, User $actor): Company
    {
        return DB::transaction(function () use ($company, $data, $files, $actor) {
            $company->fill($data);
            $company->updated_by = $actor->id;

            StoreCompanyFiles::into($company, $files);

            $company->save();

            return $company->load('updatedBy');
        });
    }
}
