<?php

namespace App\Domain\Companies\Actions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateCompanyAction
{
    /**
     * @param  array<string, mixed>  $data  validated, non-file input
     * @param  array{logo?: ?UploadedFile, contract?: ?UploadedFile, cr?: ?UploadedFile, tax?: ?UploadedFile}  $files
     */
    public function execute(array $data, array $files, User $actor): Company
    {
        return DB::transaction(function () use ($data, $files, $actor) {
            // tenant_id and code are stamped by BelongsToTenant / Company::booted().
            $company = new Company($data);

            // operational_status is outside $fillable — set explicitly.
            $company->operational_status = $data['operational_status'] ?? 'active';
            $company->updated_by = $actor->id;
            $company->save();

            // The attachments are stored under the company's id, which
            // only exists after the first save.
            StoreCompanyFiles::into($company, $files);
            $company->save();

            return $company->load('updatedBy');
        });
    }
}
