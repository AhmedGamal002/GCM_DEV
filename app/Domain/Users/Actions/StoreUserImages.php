<?php

namespace App\Domain\Users\Actions;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A client account's signature and operational stamp (FRD V01.14 §1.4) —
 * they end up on the trip documents the client approves, so unlike the
 * profile photo they go on the private `local` disk and are served only
 * through the Gate-checked route (UserController::downloadImage). A replaced
 * file is deleted so re-uploading doesn't leave orphans behind.
 *
 * Sets the columns on the model; the caller saves.
 */
final class StoreUserImages
{
    /** URL segment => column, for the download route. */
    public const COLUMNS = [
        'signature' => 'signature_image',
        'stamp' => 'stamp_image',
    ];

    /**
     * @param  array{signature?: ?UploadedFile, stamp?: ?UploadedFile}  $files
     */
    public static function into(User $user, array $files): void
    {
        foreach (self::COLUMNS as $key => $column) {
            if (empty($files[$key])) {
                continue;
            }

            if ($user->{$column} !== null) {
                Storage::disk('local')->delete($user->{$column});
            }

            $user->{$column} = $files[$key]->store("user-{$key}s/{$user->id}", 'local');
        }
    }
}
