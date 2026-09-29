<?php

namespace App\Domain\Companies\Actions;

use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores a company's uploads and points the model at them (the caller
 * saves). A replaced file is deleted so re-uploading doesn't leave
 * orphans behind.
 *
 *  - logo → public disk (shown in the UI, like user photos)
 *  - contract / cr / tax attachments → private `local` disk, served only
 *    through the authorised download route.
 */
final class StoreCompanyFiles
{
    /**
     * @param  array{logo?: ?UploadedFile, contract?: ?UploadedFile, cr?: ?UploadedFile, tax?: ?UploadedFile}  $files
     */
    public static function into(Company $company, array $files): void
    {
        if (! empty($files['logo'])) {
            self::forget('public', $company->logo);
            $company->logo = $files['logo']->store('company-logos', 'public');
        }

        foreach (Company::DOCUMENTS as $key => $column) {
            if (empty($files[$key])) {
                continue;
            }

            self::forget('local', $company->{$column});
            $company->{$column} = $files[$key]->store("company-documents/{$company->id}", 'local');
        }
    }

    private static function forget(string $disk, ?string $path): void
    {
        if ($path !== null) {
            Storage::disk($disk)->delete($path);
        }
    }
}
