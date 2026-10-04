<?php

namespace App\Domain\Contractors\Actions;

use App\Models\Contractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores a contractor's uploads and points the model at them (the caller
 * saves). A replaced file is deleted so re-uploading doesn't leave
 * orphans behind.
 *
 *  - logo → public disk (shown in the UI, like user photos)
 *  - contract / cr / tax attachments → private `local` disk, served only
 *    through the authorised download route.
 */
final class StoreContractorFiles
{
    /**
     * @param  array{logo?: ?UploadedFile, contract?: ?UploadedFile, cr?: ?UploadedFile, tax?: ?UploadedFile}  $files
     */
    public static function into(Contractor $contractor, array $files): void
    {
        if (! empty($files['logo'])) {
            self::forget('public', $contractor->logo);
            $contractor->logo = $files['logo']->store('contractor-logos', 'public');
        }

        foreach (Contractor::DOCUMENTS as $key => $column) {
            if (empty($files[$key])) {
                continue;
            }

            self::forget('local', $contractor->{$column});
            $contractor->{$column} = $files[$key]->store("contractor-documents/{$contractor->id}", 'local');
        }
    }

    private static function forget(string $disk, ?string $path): void
    {
        if ($path !== null) {
            Storage::disk($disk)->delete($path);
        }
    }
}
