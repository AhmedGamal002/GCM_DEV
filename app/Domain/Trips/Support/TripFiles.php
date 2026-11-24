<?php

namespace App\Domain\Trips\Support;

use App\Models\Trip;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The files of a trip, on the PRIVATE disk under trips/{id}/{kind}/ with a
 * name the server generates (never the uploaded file's name, never a path
 * from the request) — served later only through a policy-checked route.
 *
 * One instance per action: a file stored during the action is deleted again
 * if the action fails (rollback()), and a file it replaces is deleted only
 * once the transaction has committed — so a failed action leaves neither an
 * orphan nor a trip pointing at a deleted file.
 *
 * Signatures and stamps are snapshots (decisions د4–د6): copies of the
 * account's image, or the image drawn / uploaded at that moment — so a later
 * change on the account never changes a trip that was approved with it.
 */
final class TripFiles
{
    public const DISK = 'local';

    /** column => [folder, kind] — kind picks the type / size limits. */
    public const COLUMNS = [
        'delivery_note_path' => ['delivery-note', 'document'],
        'waste_manifest_path' => ['waste-manifest', 'document'],
        'recycle_receipt_path' => ['recycle-receipt', 'image'], // a photo — a page of the combined PDF (8.1)
        'live_photo_path' => ['live-photo', 'image'],
        'other_attachment_path' => ['other', 'document'],
        'client_attachment_path' => ['client-attachment', 'document'],
        'client_signature_path' => ['client-signature', 'signature'],
        'client_stamp_path' => ['client-stamp', 'signature'],
        'gcm_signature_path' => ['gcm-signature', 'signature'],
        'gcm_stamp_path' => ['gcm-stamp', 'signature'],
    ];

    /** @var list<string> */
    private array $stored = [];

    /** @var list<string> */
    private array $replaced = [];

    /** Stores an uploaded file into `$column` (replacing what was there). `$field` names the input in errors. */
    public function put(Trip $trip, string $column, UploadedFile $file, string $field): void
    {
        $this->assertAcceptable($column, $file, $field);

        [$folder] = self::COLUMNS[$column];
        $path = $file->store("trips/{$trip->id}/{$folder}", self::DISK);

        $this->stored[] = $path;
        $this->replace($trip, $column, $path);
    }

    /** Copies a file the server already holds (an account's signature or stamp) into `$column`. */
    public function copy(Trip $trip, string $column, string $sourcePath): void
    {
        [$folder] = self::COLUMNS[$column];
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'png';
        $path = "trips/{$trip->id}/{$folder}/".bin2hex(random_bytes(20)).'.'.strtolower($extension);

        Storage::disk(self::DISK)->copy($sourcePath, $path);

        $this->stored[] = $path;
        $this->replace($trip, $column, $path);
    }

    /** Stores an upload outside the trip's folder (an account's own signature); removed again if the action fails. */
    public function storeOutside(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, self::DISK);
        $this->stored[] = $path;

        return $path;
    }

    /** A file to delete once the action has committed. */
    public function deleteAfterCommit(string $path): void
    {
        $this->replaced[] = $path;
    }

    /** Empties `$column`; the file goes once the transaction commits. */
    public function clear(Trip $trip, string $column): void
    {
        $this->replace($trip, $column, null);
    }

    /** Call inside the action's transaction: the replaced files are deleted after it commits. */
    public function deleteReplacedAfterCommit(): void
    {
        $replaced = $this->replaced;

        DB::afterCommit(function () use ($replaced) {
            foreach ($replaced as $path) {
                Storage::disk(self::DISK)->delete($path);
            }
        });
    }

    /** Call when the action failed: removes what it stored. */
    public function rollback(): void
    {
        foreach ($this->stored as $path) {
            Storage::disk(self::DISK)->delete($path);
        }

        $this->stored = [];
    }

    /**
     * Runs `$work` in a transaction with this instance; on failure the files
     * it stored are removed, on success the ones it replaced.
     *
     * @template T
     *
     * @param  callable(self): T  $work
     * @return T
     */
    public static function transaction(callable $work): mixed
    {
        $files = new self;

        try {
            return DB::transaction(function () use ($files, $work) {
                $result = $work($files);
                $files->deleteReplacedAfterCommit();

                return $result;
            });
        } catch (\Throwable $e) {
            $files->rollback();

            throw $e;
        }
    }

    private function replace(Trip $trip, string $column, ?string $path): void
    {
        $old = $trip->{$column};

        if ($old !== null && $old !== $path) {
            $this->replaced[] = $old;
        }

        $trip->{$column} = $path;
    }

    /** The FormRequests (7.4) validate first; this is the domain's own floor for type and size. */
    private function assertAcceptable(string $column, UploadedFile $file, string $field): void
    {
        [, $kind] = self::COLUMNS[$column];

        [$mimes, $maxKb] = match ($kind) {
            'signature' => [config('trips.signature_mimes'), config('trips.signature_max_kb')],
            'image' => [['jpg', 'jpeg', 'png'], config('trips.attachment_max_kb')],
            default => [config('trips.attachment_mimes'), config('trips.attachment_max_kb')],
        };

        $extension = strtolower((string) $file->guessExtension());

        if (! $file->isValid() || ! in_array($extension, $mimes, true) || $file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                $field => __('The file must be a :types file of at most :size KB.', ['types' => implode(', ', $mimes), 'size' => $maxKb]),
            ]);
        }
    }
}
