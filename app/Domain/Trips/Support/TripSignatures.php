<?php

namespace App\Domain\Trips\Support;

use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * The signature and stamp snapshots a trip carries for its documents (8.1):
 *
 *  - the client's (decision د6): taken when a client account approves —
 *    its saved signature, or one drawn at that moment (saved to the account
 *    too only if it asks), and the stamp of THAT account (empty if none);
 *  - the client's when the trip completes without them (decision د4): GCM
 *    staff upload an image of the client's signature (and optionally the
 *    stamp, with the signer's name) at the completing step — a snapshot on
 *    the trip, never put on any client account;
 *  - GCM's representative (decision د5): whoever completes the trip signs —
 *    saved signature or drawn now ("keep it on my profile" optional), with
 *    their own stamp if they have one.
 *
 * Everything is copied into the trip's own files (TripFiles), so later
 * changes on an account don't touch an approved trip.
 */
final class TripSignatures
{
    /**
     * The approving client account's snapshot.
     *
     * The stamp: one uploaded on the approval page (the FRD form's optional stamp file — this trip only),
     * else the account's own (د6), else none.
     *
     * @param  array{signature?: ?UploadedFile, use_saved_signature?: bool, save_signature?: bool, stamp?: ?UploadedFile}  $input
     */
    public static function fromClientApproval(Trip $trip, User $client, array $input, TripFiles $files): void
    {
        self::signerSignature($trip, $client, $input['signature'] ?? null, (bool) ($input['use_saved_signature'] ?? false), (bool) ($input['save_signature'] ?? false), 'client_signature_path', 'signature', $files);

        if (($input['stamp'] ?? null) instanceof UploadedFile) {
            $files->put($trip, 'client_stamp_path', $input['stamp'], 'stamp');
        } else {
            self::copyStamp($trip, $client, 'client_stamp_path', $files);
        }

        $trip->client_signer_name = $client->name;
    }

    /**
     * The completing step (final approval, rejecting the objection, creating a completed trip):
     * the client's signature uploaded by staff when the trip has none (د4), then GCM's representative's (د5).
     *
     * @param  array{client_signature?: ?UploadedFile, client_stamp?: ?UploadedFile, client_signer_name?: ?string,
     *               gcm_signature?: ?UploadedFile, use_saved_gcm_signature?: bool, save_gcm_signature?: bool}  $input
     */
    public static function forCompletion(Trip $trip, User $staff, array $input, TripFiles $files): void
    {
        if (! $trip->hasClientSignature() && ! empty($input['client_signature'])) {
            $files->put($trip, 'client_signature_path', $input['client_signature'], 'client_signature');

            if (! empty($input['client_stamp'])) {
                $files->put($trip, 'client_stamp_path', $input['client_stamp'], 'client_stamp');
            }

            $trip->client_signer_name = self::cleanName($input['client_signer_name'] ?? null);
            $trip->save();

            TripHistory::record($trip, TripEvent::CLIENT_SIGNATURE_UPLOADED, $staff, [
                'reason' => $trip->client_signer_name,
            ]);
        }

        self::signerSignature($trip, $staff, $input['gcm_signature'] ?? null, (bool) ($input['use_saved_gcm_signature'] ?? false), (bool) ($input['save_gcm_signature'] ?? false), 'gcm_signature_path', 'gcm_signature', $files);
        self::copyStamp($trip, $staff, 'gcm_stamp_path', $files);
    }

    /** The signer's signature: their saved one, or the drawn upload (kept on their profile too when asked). */
    private static function signerSignature(Trip $trip, User $signer, ?UploadedFile $drawn, bool $useSaved, bool $saveToProfile, string $column, string $field, TripFiles $files): void
    {
        if ($drawn !== null) {
            $files->put($trip, $column, $drawn, $field);

            // "Save my signature" (د5 / د6): it also becomes the account's saved signature — the same
            // place StoreUserImages keeps it; the old one goes only once the action has committed.
            if ($saveToProfile && $signer->canHaveSignatureStamp()) {
                $old = $signer->signature_image;
                $signer->signature_image = $files->storeOutside($drawn, 'user-signatures/'.$signer->id);
                $signer->save();

                if ($old !== null) {
                    $files->deleteAfterCommit($old);
                }
            }

            return;
        }

        if ($useSaved && $signer->signature_image !== null) {
            $files->copy($trip, $column, $signer->signature_image);

            return;
        }

        throw ValidationException::withMessages([
            $field => __('A signature is required: use your saved signature or sign now.'),
        ]);
    }

    private static function copyStamp(Trip $trip, User $signer, string $column, TripFiles $files): void
    {
        if ($signer->stamp_image !== null) {
            $files->copy($trip, $column, $signer->stamp_image);
        } else {
            $files->clear($trip, $column);
        }
    }

    private static function cleanName(?string $name): ?string
    {
        $name = trim(strip_tags((string) $name));

        return $name === '' ? null : mb_substr($name, 0, 255);
    }
}
