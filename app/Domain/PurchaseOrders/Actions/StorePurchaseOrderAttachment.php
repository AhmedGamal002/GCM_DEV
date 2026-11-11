<?php

namespace App\Domain\PurchaseOrders\Actions;

use App\Models\PurchaseOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores a PO's single attachment on the PRIVATE `local` disk (it is served
 * only through the authorised download route of part 6.2) and points the
 * model at it; the caller saves. A replaced file is deleted so re-uploading
 * leaves no orphan behind.
 */
final class StorePurchaseOrderAttachment
{
    public static function into(PurchaseOrder $po, ?UploadedFile $file): void
    {
        if ($file === null) {
            return;
        }

        if ($po->attachment_path !== null) {
            Storage::disk('local')->delete($po->attachment_path);
        }

        $po->attachment_path = $file->store("purchase-order-attachments/{$po->id}", 'local');
    }
}
