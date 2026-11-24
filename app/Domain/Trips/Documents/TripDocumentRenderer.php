<?php

namespace App\Domain\Trips\Documents;

use App\Models\Trip;
use App\Support\ArabicText;

/**
 * Draws a trip's documents into ONE PDF (DomPDF) from the templates in
 * resources/views/tenant/trips/documents — each kind its own page(s), in the
 * order asked. The templates are English (FRD: "جميع الملفات باللغة
 * الانجليزية"); the Arabic footer lines and any Arabic typed into the data
 * (a company, project or person's name, the notes) go through
 * ArabicText::forPdf() — DomPDF neither joins Arabic letters nor orders RTL.
 *
 * The page partials (_delivery-note, _waste-manifest, _recycle-receipt) take
 * the same `$doc` array (TripDocumentData) so the sustainability report (8.5),
 * which may append a trip's documents under each sub-service, can include
 * them in its own PDF.
 */
final class TripDocumentRenderer
{
    /** @param  list<string>  $kinds  TripDocuments kinds, in page order */
    public static function pdf(Trip $trip, array $kinds): string
    {
        // The middle pages of a long list are drawn on the canvas (TripDocumentListPages): the view registers
        // them while it renders, the callback draws them while DomPDF renders.
        TripDocumentListPages::reset();
        $wrapper = app('dompdf.wrapper');
        $wrapper->getDomPDF()->setCallbacks(TripDocumentListPages::callbacks());

        return $wrapper
            ->setPaper('a4')
            ->loadView('tenant.trips.documents.pdf', ['pages' => [self::pages($trip, $kinds)]])
            ->output();
    }

    /**
     * One trip's pages: its data and the kinds that will actually print (a recycle receipt without an
     * uploaded image is left out — د10 (3)).
     *
     * @param  list<string>  $kinds
     * @return array{doc: array<string, mixed>, kinds: list<string>}
     */
    public static function pages(Trip $trip, array $kinds): array
    {
        $doc = TripDocumentData::for($trip);

        $kinds = array_values(array_filter($kinds, fn (string $kind) => match ($kind) {
            TripDocuments::RECYCLE_RECEIPT => $doc['receipt']['image'] !== null,
            default => true,
        }));

        return ['doc' => $doc, 'kinds' => $kinds];
    }

    /** Text for a cell: Arabic shaped and ordered, everything escaped by the caller (Blade {{ }}). */
    public static function text(?string $value): string
    {
        return ArabicText::forPdf($value ?? '');
    }

    /**
     * Free text over several lines: wrapped by words BEFORE shaping (shaped Arabic is in visual order,
     * so DomPDF must never wrap it itself — the lines are printed nowrap).
     *
     * @return list<string>
     */
    public static function lines(?string $value, int $width = 100): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $out = [];

        foreach (preg_split('/\R/u', $value) ?: [] as $paragraph) {
            $line = '';

            foreach (preg_split('/\s+/u', trim($paragraph)) ?: [] as $word) {
                if ($line !== '' && mb_strlen($line.' '.$word) > $width) {
                    $out[] = $line;
                    $line = $word;
                } else {
                    $line = $line === '' ? $word : $line.' '.$word;
                }
            }

            if ($line !== '') {
                $out[] = $line;
            }
        }

        return array_map(fn (string $l) => ArabicText::forPdf($l), $out);
    }

    /**
     * The size (points) a receipt photo is drawn at: the image scaled down — never up — to fit the
     * configured box, keeping its proportions.
     *
     * @param  array{width: int, height: int}  $image
     * @return array{width: int, height: int}
     */
    public static function fit(array $image): array
    {
        $box = config('trip_documents.receipt_box');
        $scale = min(1, $box['width'] / max(1, $image['width']), $box['height'] / max(1, $image['height']));

        return ['width' => (int) floor($image['width'] * $scale), 'height' => (int) floor($image['height'] * $scale)];
    }
}
