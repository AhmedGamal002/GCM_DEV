<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Trips\Documents\TripDocuments;
use App\Domain\Trips\Support\TripFieldVisibility;
use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * A trip's generated documents (FRD V01.14 §1.14, part 8.1):
 *
 *  - GET /trips/{trip}/documents/{delivery-note|waste-manifest} — one document, INLINE (the browser's
 *    viewer previews it; the client may only preview, د10 (2)). GCM staff and the client's accounts open
 *    both; the driver and the contractor user the delivery note only (TripFieldVisibility::documentKinds).
 *  - GET /trips/{trip}/documents/export — the combined PDF (delivery note → waste manifest → recycle
 *    receipt), as a download, for GCM staff only. Its path ends in /export, so an account on vacation is
 *    refused (EnsureTenant) and the export rate limit applies.
 *
 * The trip is looked up among those the user sees (Trip::scopeVisibleTo) — any other is a 404. Documents
 * exist only once the trip is completed (د3): before that the answer is 409; a supplying trip has no waste
 * manifest (TripDocuments) — 404. The first generation reserves the trip's document number (د2).
 */
class TripDocumentController extends Controller
{
    public function show(Request $request, int $trip, string $kind): Response
    {
        abort_unless(array_key_exists($kind, TripDocuments::PREFIXES), 404);

        $model = TripController::find($request, $trip);
        Gate::authorize('viewDocument', [$model, $kind]);
        $this->ensureAvailable($model, $kind);

        $pdf = TripDocuments::render($model, $kind);

        return $this->pdf($pdf, TripDocuments::code($model, $kind).'.pdf', 'inline');
    }

    public function export(Request $request, int $trip): Response
    {
        $model = TripController::find($request, $trip);
        Gate::authorize('exportDocuments', $model);
        $this->ensureAvailable($model, TripDocuments::DELIVERY_NOTE);

        $pdf = TripDocuments::renderBundle($model);

        return $this->pdf($pdf, $model->code.'-documents.pdf', 'attachment');
    }

    private function ensureAvailable(Trip $trip, string $kind): void
    {
        $available = TripDocuments::available($trip);

        abort_if($available === [], 409, __('The trip\'s documents are generated once the trip is completed.'));
        abort_unless(in_array($kind, $available, true), 404, __('This trip has no waste manifest.'));
    }

    private function pdf(string $bytes, string $filename, string $disposition): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
